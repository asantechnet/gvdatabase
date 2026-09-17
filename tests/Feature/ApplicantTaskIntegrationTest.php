<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Board;
use App\Models\Customer;
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

        return $this->postJson("/api/applicants/{$applicantId}/change-stage", ['stage_id' => $stage->id, 'note' => "Move to {$key}"])->assertOk()->json('data');
    }

    public function test_entering_documents_required_auto_creates_case_task(): void
    {
        Board::factory()->create();
        $employee = Employee::factory()->create();
        $customer = Customer::factory()->create();
        $a = $this->postJson('/api/applicants', ['customer_id' => $customer->id, 'responsible_employee_id' => $employee->id])->assertCreated()->json('data');

        $this->moveTo($a['id'], 'consultation');
        $this->assertDatabaseMissing('tasks', ['applicant_id' => $a['id']]);

        $this->moveTo($a['id'], 'documents_required');

        $task = Task::query()->where('applicant_id', $a['id'])->firstOrFail();
        $this->assertSame('Collect required applicant documents', $task->title);
        $this->assertSame($customer->id, $task->customer_id);
        $this->assertSame('high', $task->priority);
        $this->assertNotNull($task->due_date);
        $this->assertTrue($task->users()->whereKey($employee->user_id)->exists());
    }

    public function test_stage_tasks_are_idempotent(): void
    {
        Board::factory()->create();
        $customer = Customer::factory()->create();
        $a = $this->postJson('/api/applicants', ['customer_id' => $customer->id])->assertCreated()->json('data');

        $this->moveTo($a['id'], 'consultation');

        // Staff already recorded the work manually before the transition.
        Task::create([
            'board_id' => Board::query()->first()->id,
            'customer_id' => $customer->id,
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
        $applicant = Applicant::create(['customer_id' => Customer::factory()->create()->id]);
        $service = app(ApplicantTaskService::class);

        foreach (['documents_required', 'university_application', 'visa_preparation'] as $key) {
            $stage = ApplicantStage::query()->where('key', $key)->firstOrFail();
            $created = $service->ensureForStage($applicant->fresh()->load(['customer', 'responsibleEmployee']), $stage, null);
            $this->assertNotEmpty($created);
            // Second call for the same stage must not duplicate open work.
            $this->assertSame([], $service->ensureForStage($applicant->fresh()->load(['customer', 'responsibleEmployee']), $stage, null));
        }

        $this->assertSame(3, Task::query()->where('applicant_id', $applicant->id)->count());
    }

    public function test_reading_a_case_never_creates_tasks(): void
    {
        Board::factory()->create();
        $a = $this->postJson('/api/applicants', ['customer_id' => Customer::factory()->create()->id])->assertCreated()->json('data');

        $this->getJson("/api/applicants/{$a['id']}")->assertOk();
        $this->getJson('/api/applicants')->assertOk();
        $this->getJson('/api/applicants-pipeline')->assertOk();

        $this->assertSame(0, Task::query()->where('applicant_id', $a['id'])->count());
    }

    public function test_task_api_links_applicants_and_guards_customer_mismatch(): void
    {
        $board = Board::factory()->create();
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $a = $this->postJson('/api/applicants', ['customer_id' => $customer->id])->assertCreated()->json('data');

        // Explicit linkage is accepted.
        $this->postJson('/api/tasks', ['board_id' => $board->id, 'title' => 'Call the student', 'applicant_id' => $a['id'], 'customer_id' => $customer->id])
            ->assertCreated();
        $this->assertDatabaseHas('tasks', ['applicant_id' => $a['id'], 'customer_id' => $customer->id]);

        // Customer defaults from the applicant when omitted.
        $this->postJson('/api/tasks', ['board_id' => $board->id, 'title' => 'Verify documents', 'applicant_id' => $a['id']])
            ->assertCreated();
        $this->assertDatabaseHas('tasks', ['applicant_id' => $a['id'], 'title' => 'Verify documents', 'customer_id' => $customer->id]);

        // Cross-customer attribution is rejected.
        $this->postJson('/api/tasks', ['board_id' => $board->id, 'title' => 'Wrong ledger', 'applicant_id' => $a['id'], 'customer_id' => $other->id])
            ->assertUnprocessable();
    }
}
