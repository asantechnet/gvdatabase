<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Support\RoleHierarchy;
use App\Support\ServiceBoard;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `create_personal_tasks` is a private-work permission: its holder may create,
 * read, update, delete and reorder tasks on their own Personal board, without
 * gaining access to shared boards or another employee's private work.
 */
class PersonalTaskPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'roles' => [$role],
            'acc_status' => 'active',
        ]);
    }

    private function personalTask(User $owner, array $attributes = []): Task
    {
        $task = Task::factory()->create([
            ...$attributes,
            'board_id' => ServiceBoard::personalFor($owner)->id,
        ]);
        $task->users()->attach($owner->id);

        return $task;
    }

    public function test_every_employee_role_gets_create_personal_tasks_by_default(): void
    {
        $grants = RolePermissionSeeder::grants();

        foreach (RoleHierarchy::LEVELS as $role => $level) {
            if (in_array($role, ['applicant', 'super_admin'], true)) {
                continue;
            }

            $this->assertContains(
                'create_personal_tasks',
                $grants[$role] ?? [],
                "{$role} should have create_personal_tasks by default.",
            );
        }

        $this->assertNotContains('create_personal_tasks', $grants['applicant'] ?? []);
    }

    public function test_holder_can_create_and_read_every_task_on_their_own_personal_board(): void
    {
        $staff = $this->userWithRole('staff');
        $this->assertTrue($staff->hasPermission('create_personal_tasks'));
        $this->assertFalse($staff->hasPermission('create_tasks'));

        Sanctum::actingAs($staff, ['access']);

        $board = ServiceBoard::personalFor($staff);
        $this->postJson('/api/tasks', [
            'board_id' => $board->id,
            'title' => 'Personal follow-up',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Personal follow-up');

        $created = Task::query()->where('title', 'Personal follow-up')->firstOrFail();
        $this->assertSame($board->id, $created->board_id);
        $this->assertTrue($created->users()->whereKey($staff->id)->exists());

        // The owner may read an unassigned card on their private board too.
        $unassigned = Task::factory()->create([
            'board_id' => $board->id,
            'title' => 'Owner-only note',
        ]);

        $boards = $this->getJson('/api/kanban')->assertOk()->json('data');
        $personal = collect($boards)->firstWhere('is_personal', true);

        $this->assertNotNull($personal);
        $this->assertTrue(
            collect($personal['tasks'])->pluck('id')->contains((string) $unassigned->id),
            'The personal-board owner should receive unassigned tasks on that board.',
        );
    }

    public function test_holder_can_update_reorder_and_delete_tasks_on_their_personal_board(): void
    {
        $staff = $this->userWithRole('staff');
        $task = $this->personalTask($staff, ['title' => 'Original personal task']);

        Sanctum::actingAs($staff, ['access']);

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Edited personal task'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Edited personal task');
        $this->assertSame('Edited personal task', $task->fresh()->title);

        $this->putJson("/api/tasks/{$task->id}/move", [
            'board_id' => $task->board_id,
            'order' => 0,
        ])->assertOk();

        $shared = ServiceBoard::firstOrCreateFor('Scholarship');
        $this->putJson("/api/tasks/{$task->id}/move", [
            'board_id' => $shared->id,
            'order' => 0,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
        $this->assertSame($task->board_id, $task->fresh()->board_id);

        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_permission_never_grants_shared_or_foreign_board_actions(): void
    {
        $staff = $this->userWithRole('staff');
        $shared = ServiceBoard::firstOrCreateFor('Scholarship');

        Sanctum::actingAs($staff, ['access']);

        $this->postJson('/api/tasks', [
            'board_id' => $shared->id,
            'title' => 'Shared task',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');

        $sharedTask = Task::factory()->create(['board_id' => $shared->id]);
        $sharedTask->users()->attach($staff->id);

        $this->putJson("/api/tasks/{$sharedTask->id}", ['title' => 'Changed shared task'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
        $this->deleteJson("/api/tasks/{$sharedTask->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');

        $otherOwner = $this->userWithRole('staff');
        $foreignTask = $this->personalTask($otherOwner, ['title' => 'Another employee private work']);

        $this->postJson('/api/tasks', [
            'board_id' => $foreignTask->board_id,
            'title' => 'Intruder task',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
        $this->putJson("/api/tasks/{$foreignTask->id}", ['title' => 'Intruder edit'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
        $this->deleteJson("/api/tasks/{$foreignTask->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
    }

    public function test_without_the_permission_the_owner_cannot_mutate_personal_tasks(): void
    {
        $applicant = $this->userWithRole('applicant');
        $this->assertFalse($applicant->hasPermission('create_personal_tasks'));

        $task = $this->personalTask($applicant, ['title' => 'No permission task']);

        Sanctum::actingAs($applicant, ['access']);

        $this->postJson('/api/tasks', [
            'board_id' => ServiceBoard::personalFor($applicant)->id,
            'title' => 'Forbidden create',
        ])->assertForbidden();

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Forbidden edit'])
            ->assertForbidden();
        $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();
    }

    public function test_board_read_routes_accept_the_personal_permission_alone(): void
    {
        // Strip the staff role down to this one grant so the request cannot
        // accidentally pass through the existing board or service permissions.
        $staffRoleId = DB::table('roles')->where('name', 'staff')->value('id');
        DB::table('role_permissions')->where('role_id', $staffRoleId)->delete();
        DB::table('role_permissions')->insert([
            'role_id' => $staffRoleId,
            'permission_id' => DB::table('permissions')->where('name', 'create_personal_tasks')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staff = $this->userWithRole('staff');
        $this->assertSame(['create_personal_tasks'], $staff->permissionNames());

        Sanctum::actingAs($staff, ['access']);

        $boards = $this->getJson('/api/boards')->assertOk()->json('data');
        $this->assertTrue(collect($boards)->contains(fn ($board) => $board['is_personal'] && $board['is_owned']));

        $kanban = $this->getJson('/api/kanban')->assertOk()->json('data');
        $this->assertTrue(collect($kanban)->contains(fn ($board) => $board['is_personal'] && $board['is_owned']));

        $board = ServiceBoard::personalFor($staff);
        $this->postJson('/api/tasks', [
            'board_id' => $board->id,
            'title' => 'Permission-only task',
        ])->assertCreated();
    }
}
