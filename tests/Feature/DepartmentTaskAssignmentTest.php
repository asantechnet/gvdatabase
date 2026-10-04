<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A department manager (the employee named in `departments.manager_employee_id`)
 * may assign tasks to the members of the department they head, even when their
 * role does not carry the global `assign_task_members` permission. Their target
 * list stays inside the department; a role grant remains unrestricted.
 */
class DepartmentTaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->syncRoles([$role]);

        return $user;
    }

    /** @return array{0: User, 1: User, 2: Task} */
    private function departmentHeadWithTask(): array
    {
        // A read-only supervisory role: it can view the team's work but holds
        // no task-assignment or edit permission on its own.
        $head = $this->userWithRole('senior_counselor');
        $headEmployee = Employee::factory()->create(['user_id' => $head->id]);
        $department = Department::create([
            'name' => 'Visa Counselors',
            'manager_employee_id' => $headEmployee->id,
            'is_active' => true,
        ]);

        $member = $this->userWithRole('staff');
        Employee::factory()->create([
            'user_id' => $member->id,
            'department_id' => $department->id,
        ]);

        $board = Board::factory()->create();
        $board->users()->sync([$member->id]);

        $task = Task::create([
            'board_id' => $board->id,
            'title' => 'Prepare applicant documents',
            'priority' => 'medium',
            'order' => 0,
        ]);
        $task->users()->sync([$member->id]);

        return [$head->fresh(), $member, $task];
    }

    public function test_department_head_receives_assignment_capability_without_a_role_grant(): void
    {
        [$head] = $this->departmentHeadWithTask();

        $this->assertFalse($head->hasDirectPermission('assign_task_members'));
        $this->assertTrue($head->hasPermission('assign_task_members'));
    }

    public function test_department_head_can_assign_a_task_to_a_department_member(): void
    {
        [$head, $member, $task] = $this->departmentHeadWithTask();

        Sanctum::actingAs($head, ['access']);

        $this->putJson("/api/tasks/{$task->id}", ['assignees' => [$member->id]])
            ->assertOk();

        $this->assertTrue($task->fresh()->users()->whereKey($member->id)->exists());
    }

    public function test_department_head_cannot_assign_a_user_outside_their_department(): void
    {
        [$head, $member, $task] = $this->departmentHeadWithTask();
        $outsider = $this->userWithRole('staff');

        Sanctum::actingAs($head, ['access']);

        $this->putJson("/api/tasks/{$task->id}", ['assignees' => [$outsider->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('assignees');

        $this->assertFalse($task->fresh()->users()->whereKey($outsider->id)->exists());
    }

    public function test_a_role_grant_keeps_assignment_unrestricted(): void
    {
        $manager = $this->userWithRole('manager');
        Employee::factory()->create(['user_id' => $manager->id]);

        $board = Board::factory()->create();
        $task = Task::create([
            'board_id' => $board->id,
            'title' => 'Coordinate branch rollout',
            'priority' => 'high',
            'order' => 0,
        ]);

        $anyone = $this->userWithRole('staff');

        Sanctum::actingAs($manager->fresh(), ['access']);

        $this->putJson("/api/tasks/{$task->id}", ['assignees' => [$anyone->id, $manager->id]])
            ->assertOk();

        $this->assertTrue($task->fresh()->users()->whereKey($anyone->id)->exists());
    }
}
