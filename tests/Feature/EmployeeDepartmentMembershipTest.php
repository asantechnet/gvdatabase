<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\EmployeeScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeDepartmentMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $actor = User::factory()->create();
        $actor->syncRoles(['super_admin']);
        Sanctum::actingAs($actor, ['access']);
    }

    public function test_an_employee_can_belong_to_two_departments_with_one_primary(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $employee = Employee::factory()->create(['department_id' => $operations->id]);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'department_ids' => [$operations->id, $finance->id],
            'primary_department_id' => $finance->id,
        ])->assertOk();

        $this->assertEqualsCanonicalizing(
            [$operations->id, $finance->id],
            $response->json('data.department_ids'),
        );
        $response->assertJsonPath('data.primary_department_id', $finance->id);
        $response->assertJsonPath('data.department_id', $finance->id);
        $response->assertJsonPath('data.department_name', 'Finance');

        $this->assertCount(2, $response->json('data.departments'));
        $this->assertSame(2, DB::table('department_employee')->where('employee_id', $employee->id)->count());
        $this->assertSame(1, DB::table('department_employee')->where('employee_id', $employee->id)->where('is_primary', true)->count());
    }

    public function test_a_legacy_department_write_promotes_without_detaching_other_memberships(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $employee = Employee::factory()->create(['department_id' => $operations->id]);
        $employee->syncDepartments([$operations->id, $finance->id], $operations->id);

        $this->putJson("/api/employees/{$employee->id}", [
            'department_id' => $finance->id,
        ])->assertOk();

        $this->assertEqualsCanonicalizing(
            [$operations->id, $finance->id],
            DB::table('department_employee')->where('employee_id', $employee->id)->pluck('department_id')->all(),
        );
        $this->assertSame(
            $finance->id,
            (int) DB::table('department_employee')->where('employee_id', $employee->id)->where('is_primary', true)->value('department_id'),
        );
        $this->assertSame($finance->id, (int) $employee->fresh()->department_id);
    }

    public function test_the_submitted_department_set_is_authoritative(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $employee = Employee::factory()->create(['department_id' => $operations->id]);

        $this->putJson("/api/employees/{$employee->id}", [
            'department_ids' => [$finance->id],
            'primary_department_id' => $finance->id,
        ])->assertOk();

        $this->assertSame(
            [$finance->id],
            DB::table('department_employee')->where('employee_id', $employee->id)->pluck('department_id')->map(fn ($id) => (int) $id)->all(),
        );
        $this->assertSame($finance->id, (int) $employee->fresh()->department_id);
    }

    public function test_an_empty_department_set_clears_the_memberships_and_the_projection(): void
    {
        $operations = $this->department('Operations');
        $employee = Employee::factory()->create(['department_id' => $operations->id]);

        $this->putJson("/api/employees/{$employee->id}", ['department_ids' => []])
            ->assertOk()
            ->assertJsonPath('data.department_ids', [])
            ->assertJsonPath('data.department_id', null)
            ->assertJsonPath('data.department_name', null);

        $this->assertSame(0, DB::table('department_employee')->where('employee_id', $employee->id)->count());
        $this->assertNull($employee->fresh()->department_id);
    }

    public function test_a_department_manager_sees_secondary_members_in_their_scope(): void
    {
        $managed = $this->department('Managed');
        $other = $this->department('Other');

        $managerUser = User::factory()->create();
        $managerUser->syncRoles(['manager']);
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
        $managed->update(['manager_employee_id' => $manager->id]);

        $member = Employee::factory()->create(['department_id' => $other->id]);
        $member->syncDepartments([$other->id, $managed->id], $other->id);

        $visible = app(EmployeeScope::class)->visibleEmployeeIds($managerUser);

        $this->assertContains($member->id, $visible);
    }

    public function test_a_caller_without_the_hierarchy_permission_cannot_change_memberships(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');

        $role = Role::create(['name' => 'limited_editor', 'level' => 10]);
        $role->permissions()->sync(Permission::query()
            ->whereIn('name', ['view_all_employees', 'edit_employees', 'manage_all_employees'])
            ->pluck('id')
            ->all());

        $editor = User::factory()->create();
        $editor->syncRoles(['limited_editor']);
        Sanctum::actingAs($editor, ['access']);

        $employee = Employee::factory()->create(['department_id' => $operations->id]);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'department_ids' => [$finance->id],
            'primary_department_id' => $finance->id,
        ])->assertOk();

        $response->assertJsonPath('data.department_id', $operations->id);
        $this->assertSame(
            [$operations->id],
            DB::table('department_employee')->where('employee_id', $employee->id)->pluck('department_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_an_unknown_or_deleted_department_is_rejected_without_changing_memberships(): void
    {
        $operations = $this->department('Operations');
        $deleted = $this->department('Retired');
        $deleted->delete();
        $employee = Employee::factory()->create(['department_id' => $operations->id]);

        $this->putJson("/api/employees/{$employee->id}", [
            'department_ids' => [$operations->id, $deleted->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['department_ids.1']);

        $this->assertSame(
            [$operations->id],
            DB::table('department_employee')->where('employee_id', $employee->id)->pluck('department_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_the_primary_department_must_be_part_of_the_submitted_set(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $employee = Employee::factory()->create(['department_id' => $operations->id]);

        $this->putJson("/api/employees/{$employee->id}", [
            'department_ids' => [$operations->id],
            'primary_department_id' => $finance->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['primary_department_id']);

        $this->assertSame($operations->id, (int) $employee->fresh()->department_id);
    }

    public function test_the_same_department_cannot_be_stored_twice_for_one_employee(): void
    {
        $operations = $this->department('Operations');
        $employee = Employee::factory()->create(['department_id' => $operations->id]);

        DB::table('department_employee')->insertOrIgnore([
            'employee_id' => $employee->id,
            'department_id' => $operations->id,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, DB::table('department_employee')->where('employee_id', $employee->id)->count());
        $this->assertSame(1, DB::table('department_employee')->where('employee_id', $employee->id)->where('is_primary', true)->count());
    }

    private function department(string $name): Department
    {
        return Department::create(['name' => $name, 'is_active' => true]);
    }
}
