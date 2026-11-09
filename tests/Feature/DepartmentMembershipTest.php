<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\DepartmentMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $actor = User::factory()->create();
        $actor->syncRoles(['super_admin']);
        Sanctum::actingAs($actor, ['access']);
    }

    public function test_the_member_list_reports_managers_and_count_without_a_primary_member(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $manager = $this->employeeWithRole('manager', ['name' => 'Head Person']);
        app(DepartmentMembership::class)->syncManagers($operations, [$manager->id]);

        $member = Employee::factory()->create(['name' => 'Member Person']);
        $member->syncDepartments([$finance->id, $operations->id]);

        $row = collect($this->getJson('/api/departments?with_members=1')->assertOk()->json('data'))
            ->firstWhere('id', $operations->id);

        $this->assertSame(2, $row['employees_count']);
        $this->assertCount(2, $row['members']);

        $memberRow = collect($row['members'])->firstWhere('id', $member->id);
        $this->assertFalse($memberRow['is_manager']);
        $this->assertArrayNotHasKey('is_primary', $memberRow);
        $this->assertArrayNotHasKey('supervisor_name', $memberRow);

        $managerRow = collect($row['members'])->firstWhere('id', $manager->id);
        $this->assertTrue($managerRow['is_manager']);
        $this->assertSame('manager', $managerRow['role']);
    }

    public function test_the_default_list_has_no_members_and_an_empty_department_lists_none(): void
    {
        $this->department('Empty');

        $this->getJson('/api/departments')->assertOk()->assertJsonMissingPath('data.0.members');

        $this->getJson('/api/departments?with_members=1')
            ->assertOk()
            ->assertJsonPath('data.0.members', [])
            ->assertJsonPath('data.0.employees_count', 0);
    }

    public function test_adding_a_member_keeps_their_other_departments(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $employee = Employee::factory()->create();
        $employee->syncDepartments([$finance->id]);

        $this->putJson("/api/departments/{$operations->id}", [
            'employee_ids' => [$employee->id],
        ])->assertOk()
            ->assertJsonPath('data.employees_count', 1)
            ->assertJsonPath('data.members.0.id', $employee->id);

        $this->assertEqualsCanonicalizing(
            [$operations->id, $finance->id],
            $this->membershipIds($employee),
        );
    }

    public function test_removing_a_member_keeps_their_other_departments(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $employee = Employee::factory()->create();
        $employee->syncDepartments([$operations->id, $finance->id]);

        $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => []])
            ->assertOk()
            ->assertJsonPath('data.employees_count', 0)
            ->assertJsonPath('data.members', []);

        $this->assertSame([$finance->id], $this->membershipIds($employee));
    }

    public function test_the_current_manager_cannot_be_removed_from_the_member_set(): void
    {
        $operations = $this->department('Operations');
        $manager = $this->employeeWithRole('manager', ['name' => 'Head Person']);
        app(DepartmentMembership::class)->syncManagers($operations, [$manager->id]);

        $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => [$manager->id]])->assertOk();

        // The controller re-adds every current manager before syncing the set,
        // so the manager can never be detached by an employee_ids payload.
        $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => []])
            ->assertOk();

        $this->assertDatabaseHas('department_employee', [
            'department_id' => $operations->id,
            'employee_id' => $manager->id,
        ]);
    }

    public function test_an_inactive_employee_cannot_be_added_but_can_be_removed(): void
    {
        $operations = $this->department('Operations');
        $inactive = Employee::factory()->create(['status' => Employee::STATUS_INACTIVE]);

        $refused = $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => [$inactive->id]])
            ->assertStatus(422);
        $this->assertBusinessError($refused, 'employee_ids.0', 'business.employee_inactive');

        $this->assertSame(0, DB::table('department_employee')->where('department_id', $operations->id)->count());

        DB::table('department_employee')->insert([
            'employee_id' => $inactive->id,
            'department_id' => $operations->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => []])->assertOk();

        $this->assertSame(0, DB::table('department_employee')->where('employee_id', $inactive->id)->count());
    }

    public function test_omitting_the_member_set_leaves_members_untouched(): void
    {
        $operations = $this->department('Operations');
        $member = Employee::factory()->create();
        $member->syncDepartments([$operations->id]);

        $this->putJson("/api/departments/{$operations->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.employees_count', 1)
            ->assertJsonPath('data.members.0.id', $member->id);
    }

    public function test_a_caller_without_manage_departments_cannot_change_members(): void
    {
        $operations = $this->department('Operations');
        $employee = Employee::factory()->create();

        $role = Role::create(['name' => 'department_viewer', 'level' => 10]);
        $role->permissions()->sync(
            Permission::query()->where('name', 'view_departments')->pluck('id')->all(),
        );

        $viewer = User::factory()->create();
        $viewer->syncRoles(['department_viewer']);
        Sanctum::actingAs($viewer, ['access']);

        $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => [$employee->id]])
            ->assertForbidden();

        $this->assertSame(0, DB::table('department_employee')->where('department_id', $operations->id)->count());
    }

    /** @return array<int, int> */
    private function membershipIds(Employee $employee): array
    {
        return DB::table('department_employee')
            ->where('employee_id', $employee->id)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
    }

    private function department(string $name): Department
    {
        return Department::create(['name' => $name, 'is_active' => true]);
    }

    /** @param array<string, mixed> $attributes */
    private function employeeWithRole(string $role, array $attributes = []): Employee
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return Employee::factory()->create([...$attributes, 'user_id' => $user->id]);
    }

    private function assertBusinessError(\Illuminate\Testing\TestResponse $response, string $field, string $code): void
    {
        $errors = $response->json('errors');

        $this->assertIsArray($errors);
        $this->assertArrayHasKey($field, $errors);
        $this->assertSame([$code], $errors[$field]);
    }
}
