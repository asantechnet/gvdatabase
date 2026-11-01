<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Board;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\ApplicantTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantTaskIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    private function moveTo(int $applicantId, string $key, int $position = 0): array
    {
        $stage = ApplicantStage::query()->where('key', $key)->firstOrFail();

        // Every case in this suite starts at its initial stage, where the
        // document gate applies; the fixtures carry one labeled document.
        $this->seedApplicantDocuments($applicantId);

        return $this->postJson("/api/applicants/{$applicantId}/change-stage", ['stage_id' => $stage->id, 'note' => "Move to {$key}"])->assertOk()->json('data');
    }

    public function test_entering_documents_required_auto_creates_case_task(): void
    {
        Board::factory()->create();
        $employee = Employee::factory()->create();
        $a = $this->postJson('/api/applicants', ['name' => 'Task Case', 'responsible_employee_id' => $employee->id])->assertCreated()->json('data');

        $this->moveTo($a['id'], 'consultation');
        $this->assertDatabaseMissing('tasks', ['applicant_id' => $a['id']]);

        $this->moveTo($a['id'], 'documents_required');

        $task = Task::query()->where('applicant_id', $a['id'])->firstOrFail();
        $this->assertSame('Collect required applicant documents', $task->title);
        $this->assertSame($a['id'], $task->applicant_id);
        $this->assertSame('high', $task->priority);
        $this->assertNotNull($task->due_date);
        $this->assertTrue($task->users()->whereKey($employee->user_id)->exists());
    }

    public function test_stage_tasks_are_idempotent(): void
    {
        Board::factory()->create();
        $a = $this->postJson('/api/applicants', ['name' => 'Idempotent Case'])->assertCreated()->json('data');

        $this->moveTo($a['id'], 'consultation');

        // Staff already recorded the work manually before the transition.
        Task::create([
            'board_id' => Board::query()->first()->id,
            'applicant_id' => $a['id'],
            'title' => 'Collect required applicant documents',
            'priority' => 'high',
            'order' => 0,
        ]);

        $this->moveTo($a['id'], 'documents_required');

        $this->assertSame(1, Task::query()->where('applicant_id', $a['id'])->where('title', 'Collect required applicant documents')->count());
    }

    public function test_service_covers_every_mapped_stage_without_duplicates(): void
    {
        Board::factory()->create();
        $applicant = Applicant::factory()->create();
        $service = app(ApplicantTaskService::class);

        foreach (['documents_required', 'university_application', 'visa_preparation'] as $key) {
            $stage = ApplicantStage::query()->where('key', $key)->firstOrFail();
            $created = $service->ensureForStage($applicant->fresh()->load('responsibleEmployee'), $stage, null);
            $this->assertNotEmpty($created);
            // Second call for the same stage must not duplicate open work.
            $this->assertSame([], $service->ensureForStage($applicant->fresh()->load('responsibleEmployee'), $stage, null));
        }

        $this->assertSame(3, Task::query()->where('applicant_id', $applicant->id)->count());
    }

    public function test_reading_a_case_never_creates_tasks(): void
    {
        Board::factory()->create();
        $a = $this->postJson('/api/applicants', ['name' => 'Case 1'])->assertCreated()->json('data');

        $this->getJson("/api/applicants/{$a['id']}")->assertOk();
        $this->getJson('/api/applicants')->assertOk();
        $this->getJson('/api/applicants-pipeline')->assertOk();

        $this->assertSame(0, Task::query()->where('applicant_id', $a['id'])->count());
    }

    public function test_task_api_links_the_applicant(): void
    {
        $a = $this->postJson('/api/applicants', ['name' => 'Task Case'])->assertCreated()->json('data');
        $board = Board::factory()->create();

        $this->postJson('/api/tasks', ['board_id' => $board->id, 'title' => 'Call the student', 'applicant_id' => $a['id']])
            ->assertCreated();
        $this->assertDatabaseHas('tasks', ['applicant_id' => $a['id'], 'title' => 'Call the student']);

        // Without an applicant the task is simply unlinked; there is no board
        // or project relationship to inherit from any more.
        $this->postJson('/api/tasks', ['board_id' => $board->id, 'title' => 'Internal task'])
            ->assertCreated();
        $this->assertDatabaseHas('tasks', ['applicant_id' => null, 'title' => 'Internal task']);
    }
}
