<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantAttachment;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ServiceCaseIsolationTest extends TestCase
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
        return (int) ApplicantStage::query()
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

    /** A scholarship primary agreement plus one extension line on the same party. */
    private function twoLineCase(): array
    {
        $primary = $this->createBuiltInContract('scholarship', ['name' => 'Two Case Party']);
        $line = ApplicantContract::query()->where('contract_id', $primary['id'])->sole();

        $extension = $this->createBuiltInContract('scholarship', [
            'parent_contract_id' => $primary['id'],
            'fee' => 50,
        ]);
        $sibling = ApplicantContract::query()->where('contract_id', $extension['id'])->sole();

        return [$line, $sibling];
    }

    public function test_the_case_payload_contains_only_the_requested_line(): void
    {
        [$line, $sibling] = $this->twoLineCase();
        $this->assertSame($line->applicant_id, $sibling->applicant_id);

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
            'note' => 'Moved on its own',
        ])->assertOk();

        $payload = $this->getJson("/api/applicant-contracts/{$line->id}")->assertOk()->json('data');

        $this->assertSame($line->id, $payload['id']);
        $this->assertSame($line->applicant_id, $payload['applicant_id']);
        $this->assertSame($line->applicant_id, $payload['applicant']['id']);
        $this->assertSame('scholarship', $payload['built_in_service']);
        $this->assertArrayNotHasKey('services', $payload);
        $this->assertArrayNotHasKey('applicantContracts', $payload);

        // The payload carries this line's creation row and its move; every row
        // belongs to this line and the latest is the requested transition.
        $this->assertCount(2, $payload['stage_history']);
        $this->assertSame(
            [$line->id],
            array_values(array_unique(array_column($payload['stage_history'], 'applicant_contract_id'))),
        );
        $move = collect($payload['stage_history'])
            ->first(fn (array $row) => $row['new_stage']['key'] === 'consultation');
        $this->assertNotNull($move);
        $this->assertSame('Moved on its own', $move['note']);

        // Every delivery task is this line's task; the sibling never leaks in.
        $this->assertNotEmpty($payload['tasks']);
        $this->assertSame(
            [$line->id],
            array_values(array_unique(array_column($payload['tasks'], 'applicant_contract_id'))),
        );

        // Allowed targets come from this service's stage list.
        $this->assertNotEmpty($payload['allowed_next_stages']);
        $this->assertSame(
            ['scholarship'],
            array_values(array_unique(array_column($payload['allowed_next_stages'], 'service'))),
        );

        // The sibling keeps its initial stage and gained no move row.
        $this->assertSame($this->stageId('new'), (int) $sibling->fresh()->current_stage_id);
        $this->assertSame(1, ApplicantStageHistory::query()->where('applicant_contract_id', $sibling->id)->count());
        $this->assertSame(0, ApplicantStageHistory::query()
            ->where('applicant_contract_id', $sibling->id)
            ->where('new_stage_id', $this->stageId('consultation'))
            ->count());
    }

    public function test_a_line_moves_forward_without_any_applicant_document(): void
    {
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'No Documents Line']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->sole();

        $this->assertSame(0, ApplicantAttachment::count());

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
        ])->assertOk()
            ->assertJsonMissingValidationErrors(['stage_id'])
            ->assertJsonPath('data.current_stage.key', 'consultation');

        $this->assertDatabaseHas('applicant_contract', [
            'id' => $line->id,
            'current_stage_id' => $this->stageId('consultation'),
        ]);
        // Contract generation already recorded the line's initial stage; the
        // transition added exactly one further line row.
        $this->assertSame(1, ApplicantStageHistory::query()
            ->where('applicant_contract_id', $line->id)
            ->where('new_stage_id', $this->stageId('consultation'))
            ->count());
    }

    public function test_an_unlined_case_moves_forward_without_any_applicant_document(): void
    {
        $case = $this->postJson('/api/applicants', ['name' => 'Unlined No Documents'])->assertCreated()->json('data');
        $this->assertSame(0, ApplicantAttachment::count());

        $this->postJson("/api/applicants/{$case['id']}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
        ])->assertOk()->assertJsonPath('data.current_stage.key', 'consultation');

        $this->assertSame(1, ApplicantStageHistory::query()
            ->where('applicant_id', $case['id'])
            ->where('new_stage_id', $this->stageId('consultation'))
            ->count());
    }

    public function test_a_terminal_move_requires_and_stores_a_note_without_a_document(): void
    {
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Closing Line']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->sole();

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
        ])->assertOk();

        $historyBefore = ApplicantStageHistory::query()->where('applicant_contract_id', $line->id)->count();

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('cancelled'),
            'note' => '   ',
        ])->assertStatus(422)->assertJsonValidationErrors(['note']);

        $this->assertSame($historyBefore, ApplicantStageHistory::query()->where('applicant_contract_id', $line->id)->count());
        $this->assertDatabaseHas('applicant_contract', [
            'id' => $line->id,
            'current_stage_id' => $this->stageId('consultation'),
        ]);

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('cancelled'),
            'note' => ' Family withdrew ',
        ])->assertOk()->assertJsonPath('data.current_stage.key', 'cancelled');

        $latest = ApplicantStageHistory::query()
            ->where('applicant_contract_id', $line->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('Family withdrew', $latest->note);
        $this->assertSame($this->superAdmin->id, $latest->changed_by);
    }

    public function test_a_lined_party_still_rejects_the_case_level_writer_without_writing(): void
    {
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Lined Writer']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->sole();
        $caseId = (int) $line->applicant_id;

        $historyBefore = ApplicantStageHistory::query()->where('applicant_id', $caseId)->count();
        $applicantStageBefore = (int) Applicant::query()->findOrFail($caseId)->current_stage_id;
        $lineStageBefore = (int) $line->current_stage_id;

        $this->postJson("/api/applicants/{$caseId}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
            'note' => 'Case-level attempt',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stage_id'])
            ->assertJsonPath('errors.stage_id.0', 'business.case_stage_managed_by_service');

        $this->assertSame($historyBefore, ApplicantStageHistory::query()->where('applicant_id', $caseId)->count());
        $this->assertSame($applicantStageBefore, (int) Applicant::query()->findOrFail($caseId)->current_stage_id);
        $this->assertSame($lineStageBefore, (int) $line->fresh()->current_stage_id);
    }

    public function test_case_reads_follow_the_applicant_ownership_scope(): void
    {
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Owned Case']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->sole();

        Applicant::query()->whereKey($line->applicant_id)->update([
            'responsible_employee_id' => Employee::factory()->create()->id,
        ]);

        Sanctum::actingAs($this->viewerWith(['view_applicants']), ['access']);
        $this->getJson("/api/applicant-contracts/{$line->id}")->assertForbidden();

        Sanctum::actingAs($this->viewerWith(['view_applicants', 'view_all_applicants']), ['access']);
        $this->getJson("/api/applicant-contracts/{$line->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $line->id);
    }

    public function test_stage_writes_need_the_grant_and_case_scope(): void
    {
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Grant Case']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->sole();

        Sanctum::actingAs($this->viewerWith(['view_applicants', 'view_all_applicants']), ['access']);
        $this->getJson("/api/applicant-contracts/{$line->id}")->assertOk();
        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
        ])->assertForbidden();

        Sanctum::actingAs($this->viewerWith([
            'view_applicants',
            'view_all_applicants',
            'change_applicant_stage',
        ]), ['access']);
        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
        ])->assertOk()->assertJsonPath('data.current_stage.key', 'consultation');
    }

    public function test_the_pipeline_contains_only_service_case_lines(): void
    {
        [$line, $sibling] = $this->twoLineCase();
        $this->postJson('/api/applicants', ['name' => 'Unlined Party'])->assertCreated();

        $board = $this->getJson('/api/applicants-pipeline?service=scholarship')->assertOk()->json('data');

        $this->assertSame(2, $board['totals']['all']);
        $this->assertSame(2, $board['totals']['active']);
        $this->assertSame(0, $board['totals']['closed']);
        $this->assertSame('service_case', $board['counts']['unit']);
        $this->assertSame(2, $board['counts']['cards']);
        $this->assertSame(array_sum(array_column($board['stages'], 'count')), $board['totals']['all']);
        $this->assertCount(2, $board['cards']);

        foreach ($board['cards'] as $card) {
            $this->assertSame('line', $card['source']);
            $this->assertNotNull($card['line_id']);
        }

        $this->assertEqualsCanonicalizing(
            [$line->id, $sibling->id],
            array_column($board['cards'], 'line_id'),
        );
        $this->assertNotContains('Unlined Party', array_column($board['cards'], 'name'));
    }

    public function test_moving_one_pipeline_line_leaves_its_sibling_untouched(): void
    {
        [$line, $sibling] = $this->twoLineCase();
        $lineHistoryBefore = ApplicantStageHistory::query()->where('applicant_contract_id', $line->id)->count();
        $siblingHistoryBefore = ApplicantStageHistory::query()->where('applicant_contract_id', $sibling->id)->count();

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
            'note' => 'One case moved',
        ])->assertOk();

        $this->assertSame($this->stageId('consultation'), (int) $line->fresh()->current_stage_id);
        $this->assertSame($this->stageId('new'), (int) $sibling->fresh()->current_stage_id);
        $this->assertSame($lineHistoryBefore + 1, ApplicantStageHistory::query()->where('applicant_contract_id', $line->id)->count());
        $this->assertSame($siblingHistoryBefore, ApplicantStageHistory::query()->where('applicant_contract_id', $sibling->id)->count());
    }

    public function test_pipeline_placement_never_reads_the_applicant_mirror(): void
    {
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Placement Case']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->sole();

        ApplicantContract::query()->whereKey($line->id)->update(['current_stage_id' => null]);
        Applicant::query()->whereKey($line->applicant_id)->update([
            'current_stage_id' => $this->stageId('consultation'),
        ]);

        $board = $this->getJson('/api/applicants-pipeline?service=scholarship')->assertOk()->json('data');
        $this->assertSame('new', $board['cards'][0]['current_stage']['key']);
        $this->assertSame(1, collect($board['stages'])->firstWhere('key', 'new')['count']);

        // A stage from another service falls back to this service's start too.
        ApplicantContract::query()->whereKey($line->id)->update([
            'current_stage_id' => $this->stageId('enrolled', 'academy'),
        ]);

        $foreign = $this->getJson('/api/applicants-pipeline?service=scholarship')->assertOk()->json('data');
        $this->assertSame('new', $foreign['cards'][0]['current_stage']['key']);
        $this->assertSame(1, $foreign['counts']['cards']);
    }

    public function test_pipeline_totals_split_closed_cases_by_the_line_stage(): void
    {
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Closed Case']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->sole();

        ApplicantContract::query()->whereKey($line->id)->update([
            'current_stage_id' => $this->stageId('completed'),
        ]);
        Applicant::query()->whereKey($line->applicant_id)->update(['status' => 'closed']);

        $board = $this->getJson('/api/applicants-pipeline?service=scholarship')->assertOk()->json('data');

        $this->assertSame(1, $board['totals']['all']);
        $this->assertSame(1, $board['totals']['closed']);
        $this->assertSame(0, $board['totals']['active']);
        $this->assertSame(1, collect($board['stages'])->firstWhere('key', 'completed')['count']);
    }
}
