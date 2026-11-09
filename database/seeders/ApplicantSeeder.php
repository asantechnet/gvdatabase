<?php

namespace Database\Seeders;

use App\Models\AcademyClass;
use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Country;
use App\Models\EducationalScholarship;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Test;
use App\Models\University;
use App\Support\BuiltInService;
use Illuminate\Database\Seeder;

class ApplicantSeeder extends Seeder
{
    /**
     * One representative case per built-in service.
     *
     * Each applicant carries the shared identity plus the detail row for its
     * service, is owned by the seeded counselor, was acquired by the seeded
     * admission officer, and sits at a realistic pipeline stage. This is what
     * the contract, task and stage-automation seeders build on.
     */
    public function run(): void
    {
        $counselor = Employee::query()->whereHas('position', fn ($query) => $query->where('system_key', 'counselor'))->value('id');
        $admission = Employee::query()->whereHas('position', fn ($query) => $query->where('system_key', 'admission'))->value('id');

        $stage = fn (string $key) => ApplicantStage::query()->where('key', $key)->value('id');

        $countries = Country::query()->pluck('id', 'code');
        $levels = EducationLevel::query()->pluck('id', 'name');
        $universities = University::query()->pluck('id', 'name');
        $scholarships = EducationalScholarship::query()->pluck('id', 'name');
        $semesters = Semester::query()->pluck('id', 'code');
        $tests = Test::query()->pluck('id', 'name');
        $subjects = Subject::query()->pluck('id', 'name');
        $skills = Skill::query()->pluck('id', 'name');
        $teachers = Employee::query()->whereHas('position', fn ($query) => $query->where('system_key', 'teacher'))->pluck('id', 'name');
        $programs = Program::query()->pluck('id', 'name');
        $classes = AcademyClass::query()->pluck('id', 'name');

        // --- Scholarship case -------------------------------------------------
        $scholarship = $this->applicant([
            'name' => 'Ahmad Wali', 'email' => 'ahmad.wali@example.test', 'contact' => '+93 700 200 001',
            'father_name' => 'Rahim Wali', 'father_phone' => '+93 700 200 002',
            'service' => BuiltInService::SCHOLARSHIP, 'responsible_employee_id' => $counselor,
            'current_stage_id' => $stage('university_selection'),
        ]);
        $scholarship->responsibleEmployees()->sync(array_filter([$admission]));
        $scholarship->scholarshipDetail()->updateOrCreate([], [
            'country_id' => $countries['CA'] ?? null,
            'university_id' => $universities['University of Toronto'] ?? null,
            'education_level_id' => $levels['Master'] ?? null,
            'semester_id' => $semesters['FALL-2027'] ?? null,
            'scholarship_id' => $scholarships['Merit scholarship'] ?? null,
            'passport_id' => 'P-AW-1001', 'grade_score' => 88.50, 'university_fee' => 42000,
        ]);
        $scholarship->favoriteSubjects()->sync(array_filter([
            $subjects['Computer Science'] ?? null, $subjects['Mathematics'] ?? null,
        ]));

        // --- Private admission case ------------------------------------------
        $private = $this->applicant([
            'name' => 'Nadia Habibi', 'email' => 'nadia.habibi@example.test', 'contact' => '+93 700 200 003',
            'father_name' => 'Karim Habibi', 'father_phone' => '+93 700 200 004',
            'service' => BuiltInService::PRIVATE, 'responsible_employee_id' => $counselor,
            'current_stage_id' => $stage('university_selection'),
        ]);
        $private->responsibleEmployees()->sync(array_filter([$admission]));
        $private->privateDetail()->updateOrCreate([], [
            'country_id' => $countries['DE'] ?? null,
            'university_id' => $universities['Technical University of Munich'] ?? null,
            'education_level_id' => $levels['Bachelor'] ?? null,
            'semester_id' => $semesters['FALL-2027'] ?? null,
            'passport_id' => 'P-NH-1002', 'grade_score' => 79.00, 'university_fee' => 30000,
        ]);
        $private->favoriteSubjects()->sync(array_filter([$subjects['Business Administration'] ?? null]));

        // --- Test-offer case --------------------------------------------------
        $testOffer = $this->applicant([
            'name' => 'Yusuf Rahmani', 'email' => 'yusuf.rahmani@example.test', 'contact' => '+93 700 200 005',
            'father_name' => 'Saleh Rahmani', 'father_phone' => '+93 700 200 006',
            'service' => BuiltInService::TEST_OFFER, 'responsible_employee_id' => $counselor,
            'current_stage_id' => null,
        ]);
        $testOffer->testDetail()->updateOrCreate([], [
            'test_id' => $tests['IELTS Academic'] ?? null, 'passport_id' => 'P-YR-1003',
        ]);
        $testOffer->skills()->sync(array_filter([
            $skills['English Writing'] ?? null, $skills['Speaking'] ?? null,
        ]));

        // --- Academy case -----------------------------------------------------
        $academy = $this->applicant([
            'name' => 'Sara Ahmadi', 'email' => 'sara.ahmadi@example.test', 'contact' => '+93 700 200 007',
            'father_name' => 'Jamal Ahmadi', 'father_phone' => '+93 700 200 008',
            'service' => BuiltInService::ACADEMY, 'responsible_employee_id' => $counselor,
            'current_stage_id' => null,
        ]);
        $academy->academyDetail()->updateOrCreate([], [
            'program_id' => $programs['English Language'] ?? null,
            'class_id' => $classes['General English'] ?? null,
        ]);
        $academy->teachers()->sync(array_filter([$teachers['Layla Hashimi'] ?? null, $teachers['Omar Nazari'] ?? null]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applicant(array $attributes): Applicant
    {
        $applicant = Applicant::withTrashed()->firstOrNew(['email' => $attributes['email']]);
        $applicant->fill([...$attributes, 'address' => 'Kabul, Afghanistan', 'status' => 'active']);
        $applicant->deleted_at = null;
        $applicant->save();

        return $applicant;
    }
}
