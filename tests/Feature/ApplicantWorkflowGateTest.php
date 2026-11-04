<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ApplicantWorkflowGateTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($this->superAdmin, ['access']);
    }

    private function stageId(string $key, string $service = 'scholarship'): int
    {
        return ApplicantStage::query()
            ->where('key', $key)
            ->where('service', $service)
            ->firstOrFail()
            ->id;
    }

    private function viewerWith(array $permissionNames): User
    {
        $role = Role::create(['name' => 'role_'.uniqid()]);

        foreach ($permissionNames as $name) {
            $role->permissions()->attach(
                Permission::firstOrCreate(['name' => $name], ['category' => 'educational_visa']),
            );
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_a_forward_move_out_of_the_initial_stage_needs_no_document(): void
    {
        $case = $this->postJson('/api/applicants', ['name' => 'No Documents'])->assertCreated()->json('data');
        $history = ApplicantStageHistory::query()->where('applicant_id', $case['id'])->count();

        $this->postJson("/api/applicants/{$case['id']}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
            'note' => 'Documents are independent of the stage',
        ])->assertOk()->assertJsonPath('data.current_stage.key', 'consultation');

        $this->assertDatabaseHas('applicants', [
            'id' => $case['id'],
            'current_stage_id' => $this->stageId('consultation'),
        ]);

        // One transition, recorded with its actor and note.
        $latest = ApplicantStageHistory::query()
            ->where('applicant_id', $case['id'])
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('Documents are independent of the stage', $latest->note);
        $this->assertSame($this->superAdmin->id, $latest->changed_by);
        $this->assertSame(1, ApplicantStageHistory::query()
            ->where('applicant_id', $case['id'])
            ->where('new_stage_id', $this->stageId('consultation'))
            ->count());
        $this->assertSame($history + 1, ApplicantStageHistory::query()->where('applicant_id', $case['id'])->count());
    }

    public function test_closing_a_case_requires_a_reason(): void
    {
        $case = $this->postJson('/api/applicants', ['name' => 'Closing Case'])->assertCreated()->json('data');
        $this->postJson("/api/applicants/{$case['id']}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
        ])->assertOk();

        $this->postJson("/api/applicants/{$case['id']}/change-stage", [
            'stage_id' => $this->stageId('cancelled'),
        ])->assertStatus(422)->assertJsonValidationErrors(['note']);

        $this->assertDatabaseHas('applicants', ['id' => $case['id'], 'status' => 'active']);

        $this->postJson("/api/applicants/{$case['id']}/change-stage", [
            'stage_id' => $this->stageId('cancelled'),
            'note' => 'Family withdrew',
        ])->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_a_service_line_needs_only_a_reason_to_close(): void
    {
        $contract = $this->createBuiltInContract('academy', ['name' => 'Academy Line']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->firstOrFail();

        // No applicant attachment exists anywhere in this test.
        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('enrolled', 'academy'),
        ])->assertOk();

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('in_class', 'academy'),
        ])->assertOk();

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('completed', 'academy'),
        ])->assertStatus(422)->assertJsonValidationErrors(['note']);

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('completed', 'academy'),
            'note' => 'Course finished',
        ])->assertOk();
    }

    public function test_the_stage_filter_uses_the_stage_the_row_displays(): void
    {
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Filtered Case']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->sole();

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
        ])->assertOk();

        // Leave the applicant mirror behind on purpose: with a service filter
        // the line is the unit, so the list must read the line's stage.
        Applicant::query()->whereKey($line->applicant_id)->update([
            'current_stage_id' => $this->stageId('new'),
        ]);

        $rows = $this->getJson('/api/applicants?service=scholarship&stage=consultation')
            ->assertOk()
            ->json('data.data');

        $this->assertCount(1, $rows);
        $this->assertSame('consultation', $rows[0]['display_stage']['key']);

        // The stale mirror does not make the case appear in the New column.
        $this->assertCount(0, $this->getJson('/api/applicants?service=scholarship&stage=new')
            ->assertOk()
            ->json('data.data'));
    }

    public function test_payment_status_is_only_exposed_to_viewers_with_finance_access(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $contract = $this->createBuiltInContract('scholarship', [
            'name' => 'Priced Case',
            'fee' => 100,
            'currency_id' => $currency->id,
        ]);
        Applicant::query()->whereKey($contract['applicant_id'])->update([
            'responsible_employee_id' => Employee::factory()->create()->id,
        ]);

        Sanctum::actingAs($this->viewerWith(['view_applicants', 'view_all_applicants']), ['access']);
        $without = $this->getJson('/api/applicants')->assertOk()->json('data.data');
        $this->assertCount(1, $without);
        $this->assertArrayNotHasKey('payment_status', $without[0]);

        Sanctum::actingAs($this->viewerWith(['view_applicants', 'view_all_applicants', 'view_applicant_financials']), ['access']);
        $with = $this->getJson('/api/applicants')->assertOk()->json('data.data');
        $this->assertCount(1, $with);
        $this->assertArrayHasKey('payment_status', $with[0]);
        $this->assertSame('unpaid', $with[0]['payment_status']);
    }
}
