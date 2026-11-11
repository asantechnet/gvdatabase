<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class AcademyPermissionTest extends AcademyTestCase
{
    use RefreshDatabase;

    public function test_academy_reads_require_the_academy_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => []]), ['access']);

        $this->getJson('/api/academy/classes')->assertForbidden();
        $this->getJson('/api/academy/enrollments')->assertForbidden();
        $this->getJson('/api/academy/attendance?class_id=1&month=2027-01')->assertForbidden();
        $this->getJson('/api/academy/teachers')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);

        $this->getJson('/api/academy/classes')->assertOk();
        $this->getJson('/api/academy/teachers')->assertOk();
    }

    public function test_teacher_role_and_attendance_permissions_are_seeded(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'view_academy_attendance']);
        $this->assertDatabaseHas('permissions', ['name' => 'manage_academy_attendance']);
        $this->assertDatabaseHas('positions', ['system_key' => 'teacher']);

        $teacherRole = DB::table('roles')->where('name', 'teacher')->first();
        $this->assertNotNull($teacherRole);

        $permissions = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $teacherRole->id)
            ->pluck('permissions.name')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['view_academy_attendance', 'manage_academy_attendance'],
            $permissions,
        );

        foreach (['manage_academy_classes', 'manage_academy_attendance', 'issue_academy_certificates'] as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name]);
        }

        $this->assertGreaterThan(0, DB::table('role_permissions')->count());
    }
}
