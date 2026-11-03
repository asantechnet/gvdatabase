<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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

    public function test_the_member_list_reports_primary_manager_and_count(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $manager = $this->employeeWithRole('manager', ['name' => 'Head Person']);
        $operations->update(['manager_employee_id' => $manager->id]);
        $manager->syncDepartments([$operations->id], $operations->id);

        $primary = Employee::factory()->create(['name' => 'Primary Person', 'department_id' => $operations->id]);
        $secondary = Employee::factory()->create(['name' => 'Secondary Person', 'department_id' => $finance->id]);
        $secondary->syncDepartments([$finance->id, $operations->id], $finance->id);

        $row = collect($this->getJson('/api/departments?with_members=1')->assertOk()->json('data'))
            ->firstWhere('id', $operations->id);

        $this->assertSame(3, $row['employees_count']);
        $this->assertCount(3, $row['members']);

        $secondaryRow = collect($row['members'])->firstWhere('id', $secondary->id);
        $this->assertFalse($secondaryRow['is_primary']);
        $this->assertFalse($secondaryRow['is_manager']);
        $this->assertArrayHasKey('supervisor_name', $secondaryRow);

        $managerRow = collect($row['members'])->firstWhere('id', $manager->id);
        $this->assertTrue($managerRow['is_manager']);
        $this->assertSame('manager', $managerRow['role']);
        $this->assertTrue($managerRow['is_primary']);

        $this->assertTrue(collect($row['members'])->firstWhere('id', $primary->id)['is_primary']);
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
        $employee = Employee::factory()->create(['department_id' => $finance->id]);

        $this->putJson("/api/departments/{$operations->id}", [
            'employee_ids' => [$employee->id],
        ])->assertOk()
            ->assertJsonPath('data.employees_count', 1)
            ->assertJsonPath('data.members.0.id', $employee->id);

        $this->assertEqualsCanonicalizing(
            [$operations->id, $finance->id],
            $this->membershipIds($employee),
        );
        $this->assertSame($finance->id, (int) $employee->fresh()->department_id);
    }

    public function test_removing_a_primary_member_promotes_their_oldest_other_department(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $employee = Employee::factory()->create(['department_id' => $operations->id]);
        $employee->syncDepartments([$operations->id, $finance->id], $operations->id);

        $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => []])
            ->assertOk()
            ->assertJsonPath('data.employees_count', 0)
            ->assertJsonPath('data.members', []);

        $this->assertSame([$finance->id], $this->membershipIds($employee));
        $this->assertSame($finance->id, (int) $employee->fresh()->department_id);
        $this->assertSame(
            1,
            DB::table('department_employee')->where('employee_id', $employee->id)->where('is_primary', true)->count(),
        );
    }

    public function test_removing_the_last_membership_clears_the_primary_projection(): void
    {
        $operations = $this->department('Operations');
        $employee = Employee::factory()->create(['department_id' => $operations->id]);

        $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => []])->assertOk();

        $this->assertSame([], $this->membershipIds($employee));
        $this->assertNull($employee->fresh()->department_id);
        $this->assertNull($employee->fresh()->department);
    }

    public function test_the_current_manager_cannot_be_removed_from_the_member_set(): void
    {
        $operations = $this->department('Operations');
        $manager = $this->employeeWithRole('manager', ['name' => 'Head Person']);
        $operations->update(['manager_employee_id' => $manager->id]);

        $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => [$manager->id]])->assertOk();

        $refused = $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => []])
            ->assertStatus(422);
        $this->assertBusinessError($refused, 'employee_ids', 'business.department_manager_cannot_be_removed');

        $this->assertDatabaseHas('department_employee', [
            'department_id' => $operations->id,
            'employee_id' => $manager->id,
        ]);
    }

    public function test_an_added_employee_must_rank_below_the_manager(): void
    {
        $operations = $this->department('Operations');
        $manager = $this->employeeWithRole('manager');
        $operations->update(['manager_employee_id' => $manager->id]);
        $senior = $this->employeeWithRole('admin');

        $refused = $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => [$senior->id]])
            ->assertStatus(422);
        $this->assertBusinessError($refused, 'employee_ids.0', 'business.department_member_rank_invalid');

        $this->assertSame(0, DB::table('department_employee')->where('department_id', $operations->id)->count());
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
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson("/api/departments/{$operations->id}", ['employee_ids' => []])->assertOk();

        $this->assertSame(0, DB::table('department_employee')->where('employee_id', $inactive->id)->count());
    }

    public function test_omitting_the_member_set_leaves_members_untouched(): void
    {
        $operations = $this->department('Operations');
        $member = Employee::factory()->create(['department_id' => $operations->id]);

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

    /**
     * Validation messages for a dotted field are keyed literally in the JSON
     * payload (`errors."employee_ids.0"`), so assert the bag entry directly.
     */
    private function assertBusinessError(\Illuminate\Testing\TestResponse $response, string $field, string $code): void
    {
        $errors = $response->json('errors');

        $this->assertIsArray($errors);
        $this->assertArrayHasKey($field, $errors);
        $this->assertSame([$code], $errors[$field]);
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

    /** @return array<int, int> */
    private function membershipIds(Employee $employee): array
    {
        return DB::table('department_employee')
            ->where('employee_id', $employee->id)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
