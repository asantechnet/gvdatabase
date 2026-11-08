<?php

namespace Tests\Feature;

use App\Models\AcademyAttendance;
use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\AcademyTeacherTimesheet;
use App\Models\Currency;
use App\Models\Duration;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private AcademyClass $class;

    private AcademyEnrollment $enrollment;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $program = Program::create(['name' => 'Foundation', 'is_active' => true]);
        $time = TimeSlot::create(['name' => 'Morning', 'is_active' => true]);
        $duration = Duration::create(['name' => 'Three months', 'months' => 3, 'is_active' => true]);
        $this->teacher = Teacher::create(['name' => 'Jane Doe', 'is_active' => true]);
        $this->class = AcademyClass::create([
            'name' => 'Class A', 'program_id' => $program->id, 'time_id' => $time->id,
            'duration_id' => $duration->id, 'starts_on' => '2027-01-01', 'ends_on' => '2027-03-31',
            'weekdays' => [1, 3, 5], 'starts_at' => '09:00:00', 'ends_at' => '10:00:00',
            'capacity' => 20, 'is_active' => true,
        ]);
        $this->class->teachers()->sync([$this->teacher->id]);
        $this->enrollment = AcademyEnrollment::factory()->create([
            'student_id' => Student::factory()->create(),
            'class_id' => $this->class->id,
            'started_on' => '2027-01-04',
            'ends_on' => '2027-03-31',
            'status' => 'active',
        ]);
    }

    public function test_month_read_and_repeated_cell_save_keep_one_row(): void
    {
        $this->getJson('/api/academy/attendance?class_id='.$this->class->id.'&month=2027-01')
            ->assertOk()
            ->assertJsonPath('data.month', '2027-01')
            ->assertJsonPath('data.rows.0.enrollment_id', $this->enrollment->id)
            ->assertJsonCount(31, 'data.dates');

        foreach (['present', 'absent'] as $status) {
            $this->putJson('/api/academy/attendance', ['rows' => [[
                'enrollment_id' => $this->enrollment->id,
                'date' => '2027-01-04',
                'status' => $status,
            ]]])->assertOk();
        }

        $this->assertSame(1, AcademyAttendance::count());
        $this->assertSame('absent', AcademyAttendance::sole()->status);
    }

    public function test_dates_outside_the_term_are_refused_without_writes(): void
    {
        $this->putJson('/api/academy/attendance', ['rows' => [[
            'enrollment_id' => $this->enrollment->id,
            'date' => '2026-12-28',
            'status' => 'present',
        ]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.date');

        $this->assertSame(0, AcademyAttendance::count());
    }

    public function test_teacher_can_have_two_classes_on_one_date(): void
    {
        $second = AcademyClass::create([
            'name' => 'Class B', 'program_id' => $this->class->program_id, 'time_id' => $this->class->time_id,
            'duration_id' => $this->class->duration_id, 'weekdays' => [1],
            'capacity' => 20, 'is_active' => true,
        ]);
        $second->teachers()->sync([$this->teacher->id]);

        $this->putJson('/api/academy/teacher-timesheets', ['teacher_rows' => [
            ['teacher_id' => $this->teacher->id, 'class_id' => $this->class->id, 'date' => '2027-01-04', 'status' => 'present', 'starts_at' => '09:00', 'ends_at' => '10:00'],
            ['teacher_id' => $this->teacher->id, 'class_id' => $second->id, 'date' => '2027-01-04', 'status' => 'present', 'starts_at' => '11:00', 'ends_at' => '12:30'],
        ]])->assertOk();

        $this->assertSame(2, AcademyTeacherTimesheet::count());
        $this->assertSame(90, AcademyTeacherTimesheet::where('class_id', $second->id)->sole()->minutes);
        $this->getJson('/api/academy/teacher-timesheets?teacher_id='.$this->teacher->id.'&month=2027-01')
            ->assertOk()->assertJsonCount(2, 'data.rows');
    }

    public function test_permissions_gate_reads_and_writes(): void
    {
        $reader = User::factory()->create();
        $read = Permission::firstOrCreate(['name' => 'view_academy_contracts'], ['category' => 'customer_contracts', 'guard_name' => 'api']);
        $readRole = Role::firstOrCreate(['name' => 'academy_reader']);
        $readRole->permissions()->sync([$read->id]);
        $reader->roles()->sync([$readRole->id]);
        Sanctum::actingAs($reader, ['access']);

        $this->getJson('/api/academy/attendance?class_id='.$this->class->id.'&month=2027-01')->assertOk();
        $this->putJson('/api/academy/attendance', ['rows' => [['enrollment_id' => $this->enrollment->id, 'date' => '2027-01-04', 'status' => 'present']]])->assertForbidden();

        $outsider = User::factory()->create();
        Sanctum::actingAs($outsider, ['access']);
        $this->getJson('/api/academy/attendance?class_id='.$this->class->id.'&month=2027-01')->assertForbidden();
    }
}
