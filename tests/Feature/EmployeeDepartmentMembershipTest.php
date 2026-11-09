<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\DepartmentMembership;
use App\Support\EmployeeScope;
use Database\Seeders\PermissionSeeder;
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
        $this->seed(PermissionSeeder::class);

        $actor = User::factory()->create();
        $actor->syncRoles(['super_admin']);
        Sanctum::actingAs($actor, ['access']);
    }

    public function test_an_employee_can_belong_to_two_departments_without_a_primary(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $employee = Employee::factory()->create();

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'department_ids' => [$operations->id, $finance->id],
        ])->assertOk();

        $this->assertEqualsCanonicalizing(
            [$operations->id, $finance->id],
            $response->json('data.department_ids'),
        );
        $response->assertJsonMissingPath('data.primary_department_id');
        $this->assertCount(2, $response->json('data.departments'));
        $this->assertSame(2, DB::table('department_employee')->where('employee_id', $employee->id)->count());
    }

    public function test_the_submitted_department_set_is_authoritative(): void
    {
        $operations = $this->department('Operations');
        $finance = $this->department('Finance');
        $employee = Employee::factory()->create();
        $employee->syncDepartments([$operations->id]);

        $this->putJson("/api/employees/{$employee->id}", [
            'department_ids' => [$finance->id],
        ])->assertOk();

        $this->assertSame(
            [$finance->id],
            DB::table('department_employee')->where('employee_id', $employee->id)->pluck('department_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_an_empty_department_set_clears_the_memberships(): void
    {
        $operations = $this->department('Operations');
        $employee = Employee::factory()->create();
        $employee->syncDepartments([$operations->id]);

        $this->putJson("/api/employees/{$employee->id}", ['department_ids' => []])
            ->assertOk()
            ->assertJsonPath('data.department_ids', []);

        $this->assertSame(0, DB::table('department_employee')->where('employee_id', $employee->id)->count());
    }

    public function test_a_department_manager_sees_every_member_in_their_scope(): void
    {
        $managed = $this->department('Managed');
        $other = $this->department('Other');

        $managerUser = User::factory()->create();
        $managerUser->syncRoles(['manager']);
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
        app(DepartmentMembership::class)->syncManagers($managed, [$manager->id]);

        $member = Employee::factory()->create();
        $member->syncDepartments([$other->id, $managed->id]);

        $visible = app(EmployeeScope::class)->visibleEmployeeIds($managerUser->fresh());

        $this->assertContains($member->id, $visible);
    }

    public function test_a_caller_without_the_department_permission_cannot_change_memberships(): void
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

        $employee = Employee::factory()->create();
        $employee->syncDepartments([$operations->id]);

        $this->putJson("/api/employees/{$employee->id}", [
            'department_ids' => [$finance->id],
        ])->assertOk();

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
        $employee = Employee::factory()->create();
        $employee->syncDepartments([$operations->id]);

        $this->putJson("/api/employees/{$employee->id}", [
            'department_ids' => [$operations->id, $deleted->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['department_ids.1']);

        $this->assertSame(
            [$operations->id],
            DB::table('department_employee')->where('employee_id', $employee->id)->pluck('department_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    private function department(string $name): Department
    {
        return Department::create(['name' => $name, 'is_active' => true]);
    }
}
