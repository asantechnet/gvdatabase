<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Duration;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class AcademyClassApiTest extends AcademyTestCase
{
    use RefreshDatabase;

    private Program $program;

    private TimeSlot $time;

    private Duration $duration;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $this->program = Program::create(['name' => 'Web Development', 'is_active' => true]);
        $this->time = TimeSlot::create(['name' => 'Morning', 'is_active' => true]);
        $this->duration = Duration::create(['name' => '6 months', 'months' => 6, 'is_active' => true]);
        $this->teacher = Teacher::create(['name' => 'Jane Doe', 'is_active' => true]);
    }

    public function test_class_can_be_created_listed_and_updated_with_schedule_and_teachers(): void
    {
        $created = $this->postJson('/api/academy/classes', [
            'name' => 'Class A',
            'description' => 'Evening cohort',
            'program_id' => $this->program->id,
            'time_id' => $this->time->id,
            'duration_id' => $this->duration->id,
            'teacher_ids' => [$this->teacher->id],
            'weekdays' => [1, 3, 5],
            'starts_on' => '2026-11-01',
            'ends_on' => '2027-01-31',
            'starts_at' => '17:00',
            'ends_at' => '19:00',
            'capacity' => 20,
            'fee' => 12000,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Class A')
            ->assertJsonPath('data.program.name', 'Web Development')
            ->assertJsonPath('data.teachers.0.name', 'Jane Doe')
            ->assertJsonPath('data.seats_taken', 0);

        $id = $created->json('data.id');

        $this->getJson('/api/academy/classes?search=Class A')->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.starts_at', '17:00');

        $this->putJson("/api/academy/classes/{$id}", [
            'name' => 'Class A',
            'capacity' => 30,
            'weekdays' => [2, 4],
        ])->assertOk()
            ->assertJsonPath('data.capacity', 30)
            ->assertJsonPath('data.weekdays', [2, 4]);
    }

    public function test_class_validation_rejects_bad_weekdays_capacity_and_dates(): void
    {
        $this->postJson('/api/academy/classes', [
            'name' => 'Bad class',
            'weekdays' => [0, 8],
            'capacity' => 0,
            'starts_on' => '2027-02-01',
            'ends_on' => '2027-01-01',
            'starts_at' => '25:00',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['weekdays.0', 'weekdays.1', 'capacity', 'ends_on', 'starts_at']);
    }

    public function test_class_with_enrollments_cannot_be_deleted_and_capacity_cannot_drop_below_active_seats(): void
    {
        $class = AcademyClass::factory()->create(['capacity' => 2]);
        AcademyEnrollment::factory()->count(2)->create([
            'class_id' => $class->id,
            'student_id' => Student::factory(),
            'status' => 'active',
        ]);

        $this->putJson("/api/academy/classes/{$class->id}", [
            'name' => $class->name,
            'capacity' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('capacity');

        $this->deleteJson("/api/academy/classes/{$class->id}")->assertStatus(422);
        $this->assertDatabaseHas('academy_classes', ['id' => $class->id, 'capacity' => 2]);
    }

    public function test_roster_is_returned_with_class_detail(): void
    {
        $class = AcademyClass::factory()->create();
        $student = Student::factory()->create(['name' => 'Roster Student', 'student_number' => 'STU-ROSTER']);
        AcademyEnrollment::factory()->create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $this->getJson("/api/academy/classes/{$class->id}")->assertOk()
            ->assertJsonPath('data.roster.0.student.name', 'Roster Student')
            ->assertJsonPath('data.roster.0.student_number', 'STU-ROSTER')
            ->assertJsonPath('data.seats_taken', 1);
    }
}
