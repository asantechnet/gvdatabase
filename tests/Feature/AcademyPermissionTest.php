<?php

namespace Tests\Feature;

use App\Models\Duration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademyPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_academy_reads_require_the_academy_read_permission_and_duration_validation(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => []]), ['access']);

        $this->getJson('/api/academy/classes')->assertForbidden();
        $this->getJson('/api/academy/enrollments')->assertForbidden();
        $this->getJson('/api/academy/overview')->assertForbidden();
        $this->getJson('/api/academy/durations')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $duration = Duration::create(['name' => 'Three months', 'months' => null, 'is_active' => true]);

        $this->getJson('/api/academy/durations')->assertOk()
            ->assertJsonPath('data.0.id', $duration->id);

        $this->patchJson("/api/academy/durations/{$duration->id}", ['months' => 3])
            ->assertOk()->assertJsonPath('data.months', 3);
        $this->patchJson("/api/academy/durations/{$duration->id}", ['months' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('months');
        $this->patchJson("/api/academy/durations/{$duration->id}", ['months' => 61])
            ->assertStatus(422)->assertJsonValidationErrors('months');
    }

    public function test_old_admission_assignment_permission_is_renamed_in_place(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'assign_responsible_employees']);
        $this->assertDatabaseMissing('permissions', ['name' => 'assign_admission_employee']);

        foreach (['manage_academy_classes', 'manage_academy_attendance', 'issue_academy_certificates'] as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name]);
        }

        $this->assertGreaterThan(0, DB::table('role_permissions')->count());
    }
}
