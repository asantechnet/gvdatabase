<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class TeacherClassCompletionTest extends AcademyTestCase
{
    use RefreshDatabase;

    public function test_assigned_teacher_can_complete_the_class(): void
    {
        $teacherUser = User::factory()->create(['roles' => ['teacher']]);
        $teacher = Employee::factory()->create(['user_id' => $teacherUser->id]);
        $class = AcademyClass::factory()->create();
        $class->teachers()->attach($teacher->id);

        Sanctum::actingAs($teacherUser, ['access']);

        $this->patchJson("/api/academy/classes/{$class->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.id', $class->id);

        $this->assertNotNull($class->fresh()->completed_at);
    }

    public function test_unassigned_teacher_cannot_complete_the_class(): void
    {
        $assignedUser = User::factory()->create(['roles' => ['teacher']]);
        $assigned = Employee::factory()->create(['user_id' => $assignedUser->id]);
        $class = AcademyClass::factory()->create();
        $class->teachers()->attach($assigned->id);

        $unassignedUser = User::factory()->create(['roles' => ['teacher']]);
        Employee::factory()->create(['user_id' => $unassignedUser->id]);

        Sanctum::actingAs($unassignedUser, ['access']);

        $this->patchJson("/api/academy/classes/{$class->id}/complete")
            ->assertForbidden();

        $this->assertNull($class->fresh()->completed_at);
    }
}
