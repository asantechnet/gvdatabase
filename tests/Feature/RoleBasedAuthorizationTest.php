<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleBasedAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_staff_only_receives_assigned_board_tasks_and_can_create_own_subtasks(): void
    {
        $staff = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $coworker = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $outsider = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);

        $assignedBoard = Board::factory()->create();
        $hiddenBoard = Board::factory()->create();
        $assignedBoard->users()->attach([$staff->id, $coworker->id]);
        $hiddenBoard->users()->attach($coworker->id);

        $assignedTask = Task::factory()->create(['board_id' => $assignedBoard->id]);
        $hiddenTask = Task::factory()->create(['board_id' => $hiddenBoard->id]);
        $assignedTask->users()->attach([$staff->id, $coworker->id]);
        $hiddenTask->users()->attach($coworker->id);

        Sanctum::actingAs($staff, ['access']);

        // The staff member receives their assigned shared board (never the
        // hidden one) plus their own private personal board, which is theirs
        // alone.
        $staffBoards = $this->getJson('/api/kanban')->assertOk()->json('data');
        $this->assertSame(
            [(string) $assignedBoard->id],
            collect($staffBoards)->where('is_personal', false)->pluck('id')->values()->all()
        );
        $assigned = collect($staffBoards)->firstWhere('id', (string) $assignedBoard->id);
        $this->assertCount(1, $assigned['tasks']);
        $this->assertSame((string) $assignedTask->id, $assigned['tasks'][0]['id']);
        $this->assertTrue(collect($staffBoards)->contains(fn ($board) => $board['is_personal'] && $board['is_owned']));

        $subtaskId = $this->postJson("/api/tasks/{$assignedTask->id}/subtasks", [
            'title' => 'Prepare handoff',
            'category' => 'Delivery',
            'priority' => 'high',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('subtasks', [
            'id' => $subtaskId,
            'task_id' => $assignedTask->id,
            'user_id' => $staff->id,
        ]);

        Sanctum::actingAs($coworker, ['access']);
        $this->deleteJson("/api/tasks/{$assignedTask->id}/subtasks/{$subtaskId}")
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');

        Sanctum::actingAs($outsider, ['access']);
        $outsiderBoards = $this->getJson('/api/kanban')->assertOk()->json('data');
        $this->assertCount(0, collect($outsiderBoards)->where('is_personal', false));
    }

    public function test_manager_can_manage_a_subtask_created_by_an_assignee(): void
    {
        $creator = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $manager = User::factory()->create(['roles' => ['manager'], 'acc_status' => 'active']);
        $board = Board::factory()->create();
        $task = Task::factory()->create(['board_id' => $board->id]);
        $board->users()->attach($creator->id);
        $task->users()->attach($creator->id);
        $subtask = Subtask::factory()->create(['task_id' => $task->id, 'user_id' => $creator->id]);

        Sanctum::actingAs($manager, ['access']);

        $this->deleteJson("/api/tasks/{$task->id}/subtasks/{$subtask->id}")
            ->assertOk();

        $this->assertSoftDeleted('subtasks', ['id' => $subtask->id]);
    }
}
