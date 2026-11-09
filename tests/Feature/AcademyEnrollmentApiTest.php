<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class AcademyEnrollmentApiTest extends AcademyTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_enrollment_list_filters_by_class_status_ending_search_and_teacher(): void
    {
        $teacher = Teacher::create(['name' => 'Filter Teacher', 'is_active' => true]);
        $class = AcademyClass::factory()->create();
        $class->teachers()->attach($teacher);
        $other = AcademyClass::factory()->create();

        $student = Student::factory()->create(['name' => 'Ending Soon', 'student_number' => 'STU-END']);
        $ending = AcademyEnrollment::factory()->create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'active',
            'ends_on' => now()->addDays(10)->toDateString(),
        ]);
        AcademyEnrollment::factory()->create([
            'class_id' => $other->id,
            'student_id' => Student::factory(),
            'status' => 'withdrawn',
            'ends_on' => now()->addDays(90)->toDateString(),
        ]);

        $this->getJson('/api/academy/enrollments?class_id='.$class->id.'&status=active&ending=1&teacher_id='.$teacher->id.'&search=STU-END')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ending->id)
            ->assertJsonPath('data.0.student.name', 'Ending Soon')
            ->assertJsonPath('data.0.student_number', 'STU-END')
            ->assertJsonPath('data.0.class.id', $class->id);
    }

    public function test_terminal_status_updates_only_the_enrollment_and_cannot_be_reopened(): void
    {
        $enrollment = AcademyEnrollment::factory()->create(['status' => 'active']);

        $this->patchJson("/api/academy/enrollments/{$enrollment->id}", [
            'status' => 'completed',
            'notes' => 'Finished the course',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completed_on', now()->toDateString());

        $this->assertDatabaseHas('academy_enrollments', [
            'id' => $enrollment->id,
            'status' => 'completed',
            'notes' => 'Finished the course',
        ]);

        $this->patchJson("/api/academy/enrollments/{$enrollment->id}", [
            'status' => 'withdrawn',
        ])->assertStatus(422);
    }

    public function test_enrollment_status_requires_a_terminal_value(): void
    {
        $enrollment = AcademyEnrollment::factory()->create();

        $this->patchJson("/api/academy/enrollments/{$enrollment->id}", [
            'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }
}
