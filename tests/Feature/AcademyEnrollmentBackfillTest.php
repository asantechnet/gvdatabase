<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Applicant;
use App\Models\Duration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademyEnrollmentBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function backfill(): void
    {
        $migration = require database_path('migrations/2026_11_07_000004_backfill_academy_enrollments.php');
        $migration->up();
    }

    public function test_backfill_creates_one_student_and_enrollment_per_legacy_detail_and_is_idempotent(): void
    {
        $applicant = Applicant::factory()->create(['service' => 'academy']);
        $class = AcademyClass::factory()->create(['starts_on' => '2026-11-01', 'ends_on' => '2027-01-31']);
        DB::table('academy_applicant_details')->insert([
            'applicant_id' => $applicant->id,
            'student_id' => 'STU-LEGACY-1',
            'class_id' => $class->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->backfill();
        $this->backfill();

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('academy_enrollments', 1);
        $student = DB::table('students')->first();
        $enrollment = DB::table('academy_enrollments')->first();

        $this->assertSame('STU-LEGACY-1', $student->student_number);
        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame($applicant->id, $enrollment->applicant_id);
        $this->assertNull($enrollment->applicant_contract_id);
        $this->assertSame('2026-11-01', $enrollment->started_on);
        $this->assertSame('2027-01-31', $enrollment->ends_on);
    }

    public function test_duration_months_are_derived_only_from_explicit_names_and_re_runs_do_not_rewrite_them(): void
    {
        $three = Duration::create(['name' => '3 months', 'is_active' => true]);
        $six = Duration::create(['name' => '6 Months', 'is_active' => true]);
        $unknown = Duration::create(['name' => 'Beginner', 'is_active' => true]);
        Duration::create(['name' => '12 weeks', 'is_active' => true]);

        $this->backfill();
        $this->backfill();

        $this->assertSame(3, $three->refresh()->months);
        $this->assertSame(6, $six->refresh()->months);
        $this->assertNull($unknown->refresh()->months);
        $this->assertNull(Duration::where('name', '12 weeks')->value('months'));
    }

    public function test_duplicate_student_numbers_are_preserved_as_separate_people(): void
    {
        foreach (['A', 'B'] as $name) {
            $applicant = Applicant::factory()->create(['service' => 'academy', 'name' => "Student {$name}"]);
            $class = AcademyClass::factory()->create();
            DB::table('academy_applicant_details')->insert([
                'applicant_id' => $applicant->id,
                'student_id' => 'STU-DUPLICATE',
                'class_id' => $class->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->backfill();

        $this->assertDatabaseCount('students', 2);
        $this->assertSame(2, DB::table('students')->where('student_number', 'STU-DUPLICATE')->count());
        $this->assertDatabaseCount('academy_enrollments', 2);
    }
}
