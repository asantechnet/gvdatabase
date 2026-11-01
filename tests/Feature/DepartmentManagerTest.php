<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $actor = User::factory()->create();
        $actor->syncRoles(['super_admin']);
        Sanctum::actingAs($actor, ['access']);
    }

    public function test_manager_options_include_only_eligible_employees(): void
    {
        $eligible = $this->employeeWithRole('manager', ['name' => 'Eligible Manager']);
        $noAccount = Employee::factory()->create(['user_id' => null, 'name' => 'No Account']);
        $counselor = $this->employeeWithRole('counselor', ['name' => 'Counselor']);
        $inactiveEmployee = $this->employeeWithRole('manager', [
            'name' => 'Inactive Employee',
            'status' => Employee::STATUS_INACTIVE,
        ]);

        $inactiveUser = User::factory()->create(['acc_status' => 'inactive']);
        $inactiveUser->syncRoles(['manager']);
        $inactiveAccount = Employee::factory()->create([
            'user_id' => $inactiveUser->id,
            'name' => 'Inactive Account',
        ]);

        $ids = collect($this->getJson('/api/departments/manager-options')->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $this->assertContains($eligible->id, $ids);

        foreach ([$noAccount, $counselor, $inactiveEmployee, $inactiveAccount] as $excluded) {
            $this->assertNotContains($excluded->id, $ids);
        }
    }

    public function test_manager_options_exclude_candidates_who_cannot_outrank_current_members(): void
    {
        $department = Department::create(['name' => 'Rank Test', 'is_active' => true]);

        $memberUser = User::factory()->create();
        $memberUser->syncRoles(['admin']);
        Employee::factory()->create([
            'user_id' => $memberUser->id,
            'department_id' => $department->id,
            'name' => 'Admin Member',
        ]);

        $manager = $this->employeeWithRole('manager');
        $admin = $this->employeeWithRole('admin');
        $superAdmin = $this->employeeWithRole('super_admin');

        $ids = collect($this->getJson("/api/departments/manager-options?department_id={$department->id}")
            ->assertOk()
            ->json('data'))->pluck('id')->all();

        $this->assertNotContains($manager->id, $ids);
        $this->assertNotContains($admin->id, $ids);
        $this->assertContains($superAdmin->id, $ids);
    }

    public function test_ineligible_manager_is_rejected_with_a_field_error_and_no_department_is_created(): void
    {
        $counselor = $this->employeeWithRole('counselor');

        $this->postJson('/api/departments', [
            'name' => 'Counseling',
            'manager_employee_id' => $counselor->id,
            'is_active' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.manager_id.0', 'business.department_manager_not_supervisory')
            ->assertJsonPath('messages.manager_id.0', __('api.errors.business.department_manager_not_supervisory'));

        $this->assertDatabaseMissing('departments', ['name' => 'Counseling']);
    }

    public function test_manager_who_does_not_outrank_members_is_rejected_and_the_department_is_unchanged(): void
    {
        $department = Department::create(['name' => 'Rank Guard', 'is_active' => true]);

        $memberUser = User::factory()->create();
        $memberUser->syncRoles(['admin']);
        Employee::factory()->create([
            'user_id' => $memberUser->id,
            'department_id' => $department->id,
        ]);

        $manager = $this->employeeWithRole('manager');

        $this->putJson("/api/departments/{$department->id}", [
            'manager_employee_id' => $manager->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.manager_id.0', 'business.department_manager_rank_invalid');

        $this->assertNull($department->fresh()->manager_employee_id);
    }

    public function test_delete_refusal_returns_the_specific_reason_and_blocking_employees(): void
    {
        $department = Department::create(['name' => 'Populated', 'is_active' => true]);
        $this->employeeWithRole('counselor', [
            'department_id' => $department->id,
            'name' => 'Blocking Person',
        ]);

        $this->deleteJson("/api/departments/{$department->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.department_has_employees')
            ->assertJsonPath('blocking_employee_count', 1)
            ->assertJsonPath('blocking_employees.0', 'Blocking Person');

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'deleted_at' => null]);
    }

    public function test_empty_department_is_deleted(): void
    {
        $department = Department::create(['name' => 'Empty', 'is_active' => true]);

        $this->deleteJson("/api/departments/{$department->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('departments', ['id' => $department->id]);
    }

    public function test_manager_options_do_not_issue_a_per_candidate_role_query(): void
    {
        foreach (range(1, 12) as $index) {
            $this->employeeWithRole('manager', ['name' => "Manager {$index}"]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/departments/manager-options')->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(10, $queryCount, "The options endpoint ran {$queryCount} queries for 12 candidates.");
    }

    /** @param array<string, mixed> $attributes */
    private function employeeWithRole(string $role, array $attributes = []): Employee
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return Employee::factory()->create([...$attributes, 'user_id' => $user->id]);
    }
}
