<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Support\ServiceBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class VisaCaseWorkspaceTest extends TestCase
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

    public function test_a_case_with_service_lines_cannot_be_moved_from_the_case_level_writer(): void
    {
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Lined Case']);
        $caseId = (int) $contract['applicant_id'];

        $historyBefore = ApplicantStageHistory::query()->where('applicant_id', $caseId)->count();
        $stageBefore = (int) Applicant::query()->findOrFail($caseId)->current_stage_id;

        $this->postJson("/api/applicants/{$caseId}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
            'note' => 'Case-level attempt',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stage_id'])
            ->assertJsonPath('errors.stage_id.0', 'business.case_stage_managed_by_service');

        $this->assertSame($historyBefore, ApplicantStageHistory::query()->where('applicant_id', $caseId)->count());
        $this->assertSame($stageBefore, (int) Applicant::query()->findOrFail($caseId)->current_stage_id);
    }

    public function test_an_unlined_case_keeps_the_case_level_writer(): void
    {
        $case = $this->postJson('/api/applicants', ['name' => 'Unlined Case'])->assertCreated()->json('data');

        $this->postJson("/api/applicants/{$case['id']}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
            'note' => 'Moved at intake',
        ])
            ->assertOk()
            ->assertJsonPath('data.current_stage.key', 'consultation');
    }

    public function test_the_line_payload_exposes_money_and_the_timeline_names_its_contract(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $contract = $this->createBuiltInContract('scholarship', [
            'name' => 'Priced Case',
            'fee' => 100,
            'currency_id' => $currency->id,
        ]);

        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->sole();

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('consultation'),
            'note' => 'Moved through the line',
        ])->assertOk();

        $payload = $this->getJson("/api/applicants/{$line->applicant_id}")->assertOk()->json('data');
        $service = collect($payload['services'])->firstWhere('id', $line->id);

        $this->assertNotNull($service);
        $this->assertEqualsWithDelta(100.0, (float) $service['payable_total'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $service['amount_paid'], 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $service['remaining_total'], 0.0001);
        $this->assertSame('unpaid', $service['payment_status']);
        $this->assertSame('primary', $service['contract_role']);
        $this->assertFalse($service['is_extension']);

        $lineHistory = collect($payload['stage_history'])
            ->firstWhere('applicant_contract_id', $line->id);

        $this->assertNotNull($lineHistory);
        $this->assertSame($line->contract->contract_number, $lineHistory['contract_number']);
        $this->assertSame('primary', $lineHistory['contract_role']);
        $this->assertFalse($lineHistory['is_case_level']);
        $this->assertSame('Moved through the line', $lineHistory['note']);

        $caseHistory = collect($payload['stage_history'])->firstWhere('is_case_level', true);
        $this->assertNotNull($caseHistory);
        $this->assertNull($caseHistory['contract_number']);
    }

    public function test_tasks_name_their_service_line_and_report_completion_blockers(): void
    {
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Task Case']);
        $line = ApplicantContract::query()->where('contract_id', $contract['id'])->sole();

        $board = ServiceBoard::firstOrCreateFor('Case workspace test');
        $task = Task::create([
            'board_id' => $board->id,
            'applicant_id' => $line->applicant_id,
            'applicant_contract_id' => $line->id,
            'title' => 'Deliver scholarship — Task Case',
            'priority' => 'high',
            'progress' => 0,
            'order' => 0,
        ]);
        Subtask::create([
            'task_id' => $task->id,
            'user_id' => $this->superAdmin->id,
            'title' => 'Collect transcript',
            'is_completed' => false,
        ]);

        $payload = $this->getJson("/api/applicants/{$line->applicant_id}")->assertOk()->json('data');
        $row = collect($payload['tasks'])->firstWhere('id', $task->id);

        $this->assertNotNull($row);
        $this->assertSame($line->id, $row['applicant_contract_id']);
        $this->assertSame($line->contract->contract_number, $row['applicant_contract']['contract']['contract_number']);
        $this->assertSame(1, $row['subtasks_count']);
        $this->assertSame(1, $row['open_subtasks_count']);
        $this->assertSame(0, $row['incomplete_dependencies_count']);
    }

    private function stageId(string $key, string $service = 'scholarship'): int
    {
        return (int) ApplicantStage::query()
            ->where('key', $key)
            ->where('service', $service)
            ->firstOrFail()
            ->id;
    }
}
