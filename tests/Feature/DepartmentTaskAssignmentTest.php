<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\DepartmentMembership;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A department manager with `manage_department_tasks` may assign work to the
 * members of the departments they manage. A role-level `assign_task_members`
 * grant remains unrestricted.
 */
class DepartmentTaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_department_manager_can_assign_a_task_to_a_department_member(): void
    {
        [$head, $member, $task] = $this->departmentHeadWithTask();

        $this->assertFalse($head->hasDirectPermission('assign_task_members'));
        $this->assertTrue($head->hasPermission('manage_department_tasks'));

        Sanctum::actingAs($head, ['access']);

        $this->putJson("/api/tasks/{$task->id}", ['assignees' => [$member->id]])
            ->assertOk();

        $this->assertTrue($task->fresh()->users()->whereKey($member->id)->exists());
    }

    public function test_department_manager_cannot_assign_a_user_outside_their_department(): void
    {
        [$head, , $task] = $this->departmentHeadWithTask();
        $outsider = User::factory()->create(['acc_status' => 'active']);

        Sanctum::actingAs($head, ['access']);

        $this->putJson("/api/tasks/{$task->id}", ['assignees' => [$outsider->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('assignees');

        $this->assertFalse($task->fresh()->users()->whereKey($outsider->id)->exists());
    }

    public function test_a_role_grant_keeps_assignment_unrestricted(): void
    {
        $role = Role::create(['name' => 'unrestricted_assigner', 'level' => 10]);
        $role->permissions()->sync(Permission::query()
            ->whereIn('name', [
                'view_all_boards', 'view_all_tasks', 'update_kanban', 'edit_all_tasks', 'assign_task_members',
            ])
            ->pluck('id')
            ->all());

        $manager = User::factory()->create(['acc_status' => 'active']);
        $manager->syncRoles([$role->name]);
        Employee::factory()->create(['user_id' => $manager->id]);

        $board = Board::create([
            'title' => 'Shared Board',
            'order' => 0,
        ]);
        $task = Task::create([
            'board_id' => $board->id,
            'title' => 'Coordinate branch rollout',
            'priority' => 'high',
            'order' => 0,
        ]);
        $anyone = User::factory()->create(['acc_status' => 'active']);

        Sanctum::actingAs($manager, ['access']);

        $this->putJson("/api/tasks/{$task->id}", ['assignees' => [$anyone->id, $manager->id]])
            ->assertOk();

        $this->assertTrue($task->fresh()->users()->whereKey($anyone->id)->exists());
    }

    /** @return array{0: User, 1: User, 2: Task} */
    private function departmentHeadWithTask(): array
    {
        $role = Role::create(['name' => 'department_head', 'level' => 10, 'is_senior' => true]);
        $role->permissions()->sync(Permission::query()
            ->whereIn('name', [
                'view_kanban', 'update_kanban', 'manage_department_tasks',
                'view_assigned_boards', 'view_assigned_tasks', 'view_departments',
            ])
            ->pluck('id')
            ->all());

        $head = User::factory()->create(['acc_status' => 'active']);
        $head->syncRoles([$role->name]);
        $headEmployee = Employee::factory()->create(['user_id' => $head->id]);

        $department = Department::create(['name' => 'Visa Counselors', 'is_active' => true]);
        app(DepartmentMembership::class)->syncManagers($department, [$headEmployee->id]);

        $member = User::factory()->create(['acc_status' => 'active']);
        $member->syncRoles(['staff']);
        $memberEmployee = Employee::factory()->create(['user_id' => $member->id]);
        $memberEmployee->syncDepartments([$department->id]);

        $board = Board::create([
            'department_id' => $department->id,
            'title' => 'Visa Counselors Tasks',
            'order' => 0,
        ]);

        $task = Task::create([
            'board_id' => $board->id,
            'title' => 'Prepare applicant documents',
            'priority' => 'medium',
            'order' => 0,
        ]);
        $task->users()->sync([$member->id]);

        return [$head->fresh(), $member, $task];
    }
}
