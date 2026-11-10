<?php

namespace Tests\Feature;

use App\Models\Board;
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

    public function test_manager_options_include_only_active_senior_account_holders(): void
    {
        $eligible = $this->employeeWithRole('manager', ['name' => 'Eligible Manager']);
        $noAccount = Employee::factory()->create(['user_id' => null, 'name' => 'No Account']);
        $junior = $this->employeeWithRole('counselor', ['name' => 'Junior Counselor']);

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

        foreach ([$noAccount, $junior, $inactiveEmployee, $inactiveAccount] as $excluded) {
            $this->assertNotContains($excluded->id, $ids);
        }
    }

    public function test_multiple_senior_managers_can_share_a_department_and_are_members(): void
    {
        $first = $this->employeeWithRole('manager');
        $second = $this->employeeWithRole('admin');

        $response = $this->postJson('/api/departments', [
            'name' => 'Shared Leadership',
            'manager_employee_ids' => [$first->id, $second->id],
            'is_active' => true,
        ])->assertCreated();

        $departmentId = $response->json('data.id');

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $response->json('data.manager_employee_ids'),
        );
        $this->assertCount(2, $response->json('data.managers'));
        $this->assertSame(2, DB::table('department_manager')->where('department_id', $departmentId)->count());
        $this->assertSame(2, DB::table('department_employee')->where('department_id', $departmentId)->count());
    }

    public function test_department_creation_creates_a_named_task_board_by_default(): void
    {
        $response = $this->postJson('/api/departments', [
            'name' => 'Admissions',
            'is_active' => true,
        ])->assertCreated();

        $board = Board::query()->where('department_id', $response->json('data.id'))->firstOrFail();

        $this->assertSame('Admissions Tasks', $board->title);
        $this->assertSame($board->id, $response->json('data.board_id'));
    }

    public function test_department_creation_can_skip_the_task_board(): void
    {
        $response = $this->postJson('/api/departments', [
            'name' => 'No Board Department',
            'create_task_board' => false,
            'is_active' => true,
        ])->assertCreated();

        $this->assertNull($response->json('data.board_id'));
        $this->assertDatabaseMissing('boards', ['department_id' => $response->json('data.id')]);
    }

    public function test_a_junior_employee_is_rejected_as_a_manager_without_creating_the_department(): void
    {
        $junior = $this->employeeWithRole('counselor');

        $response = $this->postJson('/api/departments', [
            'name' => 'Counseling',
            'manager_employee_ids' => [$junior->id],
            'is_active' => true,
        ])->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertSame(
            ['business.department_manager_not_supervisory'],
            $errors['manager_employee_ids.0'] ?? null,
        );
        $this->assertDatabaseMissing('departments', ['name' => 'Counseling']);
        $this->assertDatabaseMissing('boards', ['title' => 'Counseling Tasks']);
    }

    public function test_delete_refusal_returns_the_specific_reason_and_blocking_employees(): void
    {
        $department = Department::create(['name' => 'Populated', 'is_active' => true]);
        $blocking = Employee::factory()->create(['name' => 'Blocking Person']);
        $blocking->syncDepartments([$department->id]);

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
