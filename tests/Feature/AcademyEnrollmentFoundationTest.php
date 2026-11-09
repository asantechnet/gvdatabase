<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Applicant;
use App\Models\Duration;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\AcademyTestCase;

class AcademyEnrollmentFoundationTest extends AcademyTestCase
{
    use RefreshDatabase;

    public function test_schema_contains_the_student_class_and_enrollment_foundation(): void
    {
        foreach (['students', 'academy_enrollments', 'academy_class_teacher'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        foreach (['program_id', 'time_id', 'duration_id', 'starts_on', 'ends_on', 'weekdays', 'starts_at', 'ends_at', 'capacity', 'fee'] as $column) {
            $this->assertTrue(Schema::hasColumn('academy_classes', $column), $column);
        }

        $this->assertTrue(Schema::hasColumn('durations', 'months'));
    }

    public function test_class_schedule_and_duration_round_trip_and_class_owns_only_its_enrollments(): void
    {
        $duration = Duration::create(['name' => 'Three months', 'months' => 3, 'is_active' => true]);
        $class = AcademyClass::factory()->create([
            'duration_id' => $duration->id,
            'weekdays' => [1, 3, 5],
            'capacity' => 12,
            'starts_on' => '2026-11-01',
            'ends_on' => '2027-02-01',
            'starts_at' => '08:30:00',
            'ends_at' => '10:00:00',
            'fee' => 9000,
        ]);
        $other = AcademyClass::factory()->create();

        $mine = AcademyEnrollment::factory()->create([
            'class_id' => $class->id,
            'student_id' => Student::factory()->create(),
        ]);
        AcademyEnrollment::factory()->create([
            'class_id' => $other->id,
            'student_id' => Student::factory()->create(),
        ]);

        $class->refresh();

        $this->assertSame([1, 3, 5], $class->weekdays);
        $this->assertSame('2026-11-01', $class->starts_on->toDateString());
        $this->assertSame(12, $class->capacity);
        $this->assertSame('9000.00', $class->fee);
        $this->assertSame([$mine->id], $class->enrollments()->pluck('id')->all());
        $this->assertSame(3, $duration->refresh()->months);
    }

    public function test_one_student_can_hold_several_courses_but_a_line_can_only_have_one_enrollment(): void
    {
        $applicant = Applicant::factory()->create(['service' => 'academy']);
        $student = Student::factory()->create(['applicant_id' => $applicant->id]);
        $firstClass = AcademyClass::factory()->create();
        $secondClass = AcademyClass::factory()->create();

        $first = AcademyEnrollment::factory()->create([
            'student_id' => $student->id,
            'applicant_id' => $applicant->id,
            'class_id' => $firstClass->id,
        ]);
        $second = AcademyEnrollment::factory()->create([
            'student_id' => $student->id,
            'applicant_id' => $applicant->id,
            'class_id' => $secondClass->id,
        ]);

        $this->assertSame(2, $student->enrollments()->count());
        $this->assertNotSame($first->id, $second->id);

        $contractId = DB::table('contracts')->insertGetId([
            'status' => 'active', 'start_date' => now()->toDateString(),
            'counterparty' => $applicant->name, 'contract_role' => 'primary',
            'is_cancelled' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $lineId = DB::table('applicant_contract')->insertGetId([
            'contract_id' => $contractId, 'service' => 'academy', 'applicant_id' => $applicant->id,
            'fee' => 100, 'discount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $first->update(['contract_id' => $contractId, 'applicant_contract_id' => $lineId]);

        $this->expectException(QueryException::class);
        $second->update(['contract_id' => $contractId, 'applicant_contract_id' => $lineId]);
    }

    public function test_a_duration_without_a_month_count_stays_null(): void
    {
        $duration = Duration::create(['name' => 'Beginner', 'months' => null, 'is_active' => true]);

        $this->assertNull($duration->refresh()->months);
    }
}
