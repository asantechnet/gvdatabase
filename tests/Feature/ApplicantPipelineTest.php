<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\Country;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ApplicantPipelineTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_legacy_unstaged_applicants_are_assigned_to_a_real_new_stage_and_can_move(): void
    {
        $applicant = Applicant::factory()->create(['current_stage_id' => null]);
        $migration = require database_path('migrations/2026_10_08_000003_repair_new_applicant_stage.php');
        $migration->up();
        $migration->up();
        $new = ApplicantStage::where('key', 'new')->whereNull('country_id')->whereNull('university_id')->firstOrFail();
        $this->assertSame($new->id, $applicant->fresh()->current_stage_id);
        $this->seedApplicantDocuments($applicant->id);
        $this->getJson('/api/applicants-pipeline')->assertOk()->assertJsonFragment(['key' => 'new', 'count' => 1]);
        $consultation = ApplicantStage::where('key', 'consultation')->firstOrFail();
        $this->postJson('/api/applicants/'.$applicant->id.'/change-stage', ['stage_id' => $consultation->id])
            ->assertOk()->assertJsonPath('data.current_stage_id', $consultation->id);
        $this->assertDatabaseHas('applicant_stage_histories', [
            'applicant_id' => $applicant->id, 'old_stage_id' => $new->id, 'new_stage_id' => $consultation->id,
        ]);
    }

    public function test_list_supports_search_filters_and_boards(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);
        $employee = Employee::factory()->create();
        $a = $this->postJson('/api/applicants', ['name' => 'Ahmad Ahmadi', 'country_id' => $iran->id, 'responsible_employee_id' => $employee->id])->assertCreated()->json('data');
        $b = $this->postJson('/api/applicants', ['name' => 'Sara Mohammadi', 'country_id' => $turkey->id])->assertCreated()->json('data');

        // Search by applicant name.
        $this->getJson('/api/applicants?search=Ahmad')->assertOk()->assertJsonPath('data.total', 1)->assertJsonFragment(['id' => $a['id']]);

        // Filter by country and employee.
        $this->getJson("/api/applicants?country_id={$iran->id}")->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson("/api/applicants?responsible_employee_id={$employee->id}")->assertOk()->assertJsonPath('data.total', 1);

        // Operation board holds both active cases.
        $this->getJson('/api/applicants?board=operation')->assertOk()->assertJsonPath('data.total', 2);

        // Move one case to the completed stage directly in storage (terminal
        // stages are not reachable through linear transitions by design).
        $completed = ApplicantStage::query()->where('key', 'completed')->firstOrFail();
        Applicant::query()->whereKey($b['id'])->update(['current_stage_id' => $completed->id, 'status' => 'closed']);

        $this->getJson('/api/applicants?board=operation')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/applicants?board=final')->assertOk()->assertJsonPath('data.total', 1)->assertJsonFragment(['id' => $b['id']]);
        $this->getJson('/api/applicants?status=closed')->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_pipeline_counts_respect_active_filters(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);

        $this->postJson('/api/applicants', ['name' => 'Case 1', 'country_id' => $iran->id])->assertCreated();
        $this->postJson('/api/applicants', ['name' => 'Case 2', 'country_id' => $turkey->id])->assertCreated();

        $all = $this->getJson('/api/applicants-pipeline')->assertOk()->json('data');
        $this->assertSame(2, $all['totals']['all']);
        $this->assertSame(2, $all['totals']['active']);
        $this->assertSame(array_sum(array_column($all['stages'], 'count')), $all['totals']['all']);

        $filtered = $this->getJson("/api/applicants-pipeline?country_id={$iran->id}")->assertOk()->json('data');
        $this->assertSame(1, $filtered['totals']['all']);
        $this->assertSame(1, array_sum(array_column($filtered['stages'], 'count')));
        $this->assertNotEmpty($filtered['stages']);
        $this->assertArrayHasKey('key', $filtered['stages'][0]);
        $this->assertArrayHasKey('allowed_next_keys', $filtered['stages'][0]);
    }

    public function test_pipeline_and_list_require_view_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);
        $this->getJson('/api/applicants')->assertForbidden();
        $this->getJson('/api/applicants-pipeline')->assertForbidden();
    }

    public function test_illegal_stage_jump_is_rejected(): void
    {
        $a = $this->postJson('/api/applicants', ['name' => 'Stage Case'])->assertCreated()->json('data');
        $completed = ApplicantStage::query()->where('key', 'completed')->firstOrFail();

        $this->postJson("/api/applicants/{$a['id']}/change-stage", ['stage_id' => $completed->id])->assertUnprocessable();
    }

    public function test_cancel_withdraw_reject_and_appeal_paths_are_reachable(): void
    {
        $stageId = fn (string $key) => ApplicantStage::query()->where('key', $key)->firstOrFail()->id;
        // Every move carries a note; terminal moves require one.
        $move = fn (int $id, string $key) => $this->postJson("/api/applicants/{$id}/change-stage", ['stage_id' => $stageId($key), 'note' => "Move to {$key}"])->assertOk();

        // Cancel from an early active stage; withdrawn case re-engages.
        $a = $this->postJson('/api/applicants', ['name' => 'Cancel Case'])->assertCreated()->json('data');
        $this->seedApplicantDocuments((int) $a['id']);
        $move($a['id'], 'consultation');
        $move($a['id'], 'cancelled');
        $this->getJson('/api/applicants?board=cancelled')->assertOk()->assertJsonFragment(['id' => $a['id']]);

        $b = $this->postJson('/api/applicants', ['name' => 'Withdraw Case'])->assertCreated()->json('data');
        $this->seedApplicantDocuments((int) $b['id']);
        $move($b['id'], 'consultation');
        $move($b['id'], 'withdrawn');
        $moved = $move($b['id'], 'consultation');
        $this->assertSame('active', $moved->json('data.status'));

        // Walk to a decision stage, record a refusal, then appeal.
        $c = $this->postJson('/api/applicants', ['name' => 'Appeal Case'])->assertCreated()->json('data');
        $this->seedApplicantDocuments((int) $c['id']);
        foreach (['consultation', 'documents_required', 'documents_complete', 'university_selection', 'university_application', 'admission_pending'] as $key) {
            $move($c['id'], $key);
        }
        $rejected = $move($c['id'], 'rejected');
        $this->assertSame('closed', $rejected->json('data.status'));
        $appealed = $move($c['id'], 'visa_preparation');
        $this->assertSame('active', $appealed->json('data.status'));
        // Creation + 6 walk moves + refusal + appeal are all audited.
        $this->assertSame(9, Applicant::query()->whereKey($c['id'])->first()->stageHistory()->count());
    }

    public function test_pipeline_is_scoped_to_one_service_and_moves_the_contract_line(): void
    {
        // A case without a contract line is still on its own service's board.
        $this->postJson('/api/applicants', ['name' => 'Scholarship Case'])->assertCreated();

        // Buying a service creates the line that now owns the workflow.
        $contract = $this->createBuiltInContract('test_offer', ['name' => 'Test Offer Case']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->firstOrFail();
        $this->seedApplicantDocuments((int) $line->applicant_id);

        $offers = $this->getJson('/api/applicants-pipeline?service=test_offer')->assertOk()->json('data');
        $this->assertSame('test_offer', $offers['service']);
        $this->assertSame(1, $offers['totals']['all']);
        $this->assertCount(1, $offers['cards']);
        $this->assertSame('line', $offers['cards'][0]['source']);
        $this->assertSame($line->id, $offers['cards'][0]['line_id']);
        $this->assertSame('new', $offers['cards'][0]['current_stage']['key']);
        $this->assertSame(1, collect($offers['stages'])->firstWhere('key', 'new')['count']);

        // The scholarship board only shows scholarship work.
        $scholarship = $this->getJson('/api/applicants-pipeline')->assertOk()->json('data');
        $this->assertSame('scholarship', $scholarship['service']);
        $this->assertSame(1, $scholarship['totals']['all']);
        $this->assertCount(1, $scholarship['cards']);
        $this->assertSame('applicant', $scholarship['cards'][0]['source']);
        $this->assertSame('Scholarship Case', $scholarship['cards'][0]['name']);

        // Moving the line updates this service's board only; the mirrored
        // applicant stage is a compatibility mirror, not the board's source.
        $next = ApplicantStage::query()->where('service', 'test_offer')->where('key', 'documents_required')->firstOrFail();
        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", ['stage_id' => $next->id])->assertOk();

        $moved = $this->getJson('/api/applicants-pipeline?service=test_offer')->assertOk()->json('data');
        $this->assertSame('documents_required', $moved['cards'][0]['current_stage']['key']);
        $this->assertSame(1, collect($moved['stages'])->firstWhere('key', 'documents_required')['count']);
        $this->assertSame(0, collect($moved['stages'])->firstWhere('key', 'new')['count']);

        // Another service's stage is rejected for this line.
        $academyStage = ApplicantStage::query()->where('service', 'academy')->where('key', 'enrolled')->firstOrFail();
        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", ['stage_id' => $academyStage->id])->assertUnprocessable();
    }

    public function test_line_without_a_stage_is_placed_on_the_service_board_and_can_move(): void
    {
        $contract = $this->createBuiltInContract('academy', ['name' => 'Stageless Case']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->firstOrFail();
        $this->seedApplicantDocuments((int) $line->applicant_id);
        // Seeded/legacy lines can hold no stage at all; the board must still
        // show them on their service's starting stage and let them move.
        ApplicantContract::query()->whereKey($line->id)->update(['current_stage_id' => null]);

        $board = $this->getJson('/api/applicants-pipeline?service=academy')->assertOk()->json('data');
        $this->assertCount(1, $board['cards']);
        $this->assertSame('line', $board['cards'][0]['source']);
        $this->assertSame('new', $board['cards'][0]['current_stage']['key']);
        $this->assertSame(1, collect($board['stages'])->firstWhere('key', 'new')['count']);

        $next = ApplicantStage::query()->where('service', 'academy')->where('key', 'enrolled')->firstOrFail();
        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", ['stage_id' => $next->id])->assertOk();
        $this->assertDatabaseHas('applicant_contract', ['id' => $line->id, 'current_stage_id' => $next->id]);
    }

    public function test_changing_a_case_service_reseats_it_on_that_service_workflow(): void
    {
        $a = $this->postJson('/api/applicants', ['name' => 'Switching Case'])->assertCreated()->json('data');
        $this->seedApplicantDocuments((int) $a['id']);
        $consultation = ApplicantStage::query()->where('service', 'scholarship')->where('key', 'consultation')->firstOrFail();
        $this->postJson("/api/applicants/{$a['id']}/change-stage", ['stage_id' => $consultation->id])->assertOk();

        $new = ApplicantStage::query()->where('service', 'academy')->where('key', 'new')->firstOrFail();
        $this->putJson("/api/applicants/{$a['id']}", ['service' => 'academy'])
            ->assertOk()
            ->assertJsonPath('data.current_stage.key', 'new');
        $this->assertDatabaseHas('applicants', ['id' => $a['id'], 'current_stage_id' => $new->id, 'service' => 'academy']);

        // And the academy board picks it up on its own stage list.
        $academy = $this->getJson('/api/applicants-pipeline?service=academy')->assertOk()->json('data');
        $this->assertSame('new', $academy['cards'][0]['current_stage']['key']);
    }

    public function test_unlined_academy_case_is_on_the_academy_board_only(): void
    {
        $this->postJson('/api/applicants', ['name' => 'Academy Case', 'service' => 'academy'])->assertCreated();

        $academy = $this->getJson('/api/applicants-pipeline?service=academy')->assertOk()->json('data');
        $this->assertSame(1, $academy['totals']['all']);
        $this->assertSame('applicant', $academy['cards'][0]['source']);
        $this->assertSame('new', $academy['cards'][0]['current_stage']['key']);

        $scholarship = $this->getJson('/api/applicants-pipeline')->assertOk()->json('data');
        $this->assertSame(0, $scholarship['totals']['all']);
        $this->assertSame([], $scholarship['cards']);
    }
}
