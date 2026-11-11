<?php

namespace Database\Seeders;

use App\Models\AcademyClass;
use App\Models\Country;
use App\Models\EnglishLevel;
use App\Models\EducationalScholarship;
use App\Models\EducationLevel;
use App\Models\Language;
use App\Models\Program;
use App\Models\Province;
use App\Models\Semester;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Test;
use App\Models\University;
use Illuminate\Database\Seeder;

class EducationalReferenceSeeder extends Seeder
{
    /**
     * Reference data for every built-in service, in one place, idempotently.
     *
     * Scholarship/private need countries, universities, levels and semesters;
     * test offer needs the test catalogue; academy needs programs and classes.
     * Subjects and skills back the
     * multi-select fields on scholarship and test-offer applicants.
     */
    public function run(): void
    {
        $countries = collect([
            ['name' => 'Canada', 'code' => 'CA'],
            ['name' => 'Germany', 'code' => 'DE'],
            ['name' => 'United Kingdom', 'code' => 'GB'],
            ['name' => 'Turkey', 'code' => 'TR'],
        ])->mapWithKeys(fn (array $data) => [$data['code'] => Country::withTrashed()->updateOrCreate(
            ['code' => $data['code']], [...$data, 'is_active' => true, 'deleted_at' => null],
        )]);

        // Academy catalogue.
        AcademyClass::withTrashed()->updateOrCreate(['name' => 'General English'], ['is_active' => true, 'deleted_at' => null]);
        AcademyClass::withTrashed()->updateOrCreate(['name' => 'IELTS Intensive'], ['is_active' => true, 'deleted_at' => null]);
        foreach (['English Language', 'IELTS Preparation'] as $name) {
            Program::withTrashed()->updateOrCreate(['name' => $name], ['is_active' => true, 'deleted_at' => null]);
        }

        // Test-offer catalogue. Test-offer contracts are versioned per test, so
        // this catalogue must not be empty for the picker and workflow.
        foreach (['IELTS Academic' => 10, 'TOEFL iBT' => 20, 'Duolingo English Test' => 30] as $name => $order) {
            Test::withTrashed()->updateOrCreate(['name' => $name], ['sort_order' => $order, 'is_active' => true, 'deleted_at' => null]);
        }
        foreach (['English Writing' => 10, 'Speaking' => 20, 'Listening' => 30] as $name => $order) {
            Skill::withTrashed()->updateOrCreate(['name' => $name], ['sort_order' => $order, 'is_active' => true, 'deleted_at' => null]);
        }

        // Scholarship catalogue.
        EducationalScholarship::withTrashed()->updateOrCreate(['name' => 'Merit scholarship'], ['sort_order' => 10, 'is_active' => true, 'deleted_at' => null]);
        foreach (['Computer Science' => 10, 'Mathematics' => 20, 'Business Administration' => 30] as $name => $order) {
            Subject::withTrashed()->updateOrCreate(['name' => $name], ['sort_order' => $order, 'is_active' => true, 'deleted_at' => null]);
        }

        foreach (['Bachelor' => 10, 'Master' => 20, 'PhD' => 30] as $name => $order) {
            EducationLevel::withTrashed()->updateOrCreate(['name' => $name], ['sort_order' => $order, 'is_active' => true, 'deleted_at' => null]);
        }
        foreach (['en' => 'English', 'de' => 'German', 'fr' => 'French'] as $code => $name) {
            Language::withTrashed()->updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true, 'deleted_at' => null]);
        }

        // Visitor profile catalogues. Provinces cover Afghanistan; English
        // levels use the CEFR bands. Acquisition routes stay admin-managed.
        foreach ([
            'Badakhshan', 'Badghis', 'Baghlan', 'Balkh', 'Bamyan', 'Daykundi', 'Farah', 'Faryab',
            'Ghazni', 'Ghor', 'Helmand', 'Herat', 'Jowzjan', 'Kabul', 'Kandahar', 'Kapisa',
            'Khost', 'Kunar', 'Kunduz', 'Laghman', 'Logar', 'Nangarhar', 'Nimroz', 'Nuristan',
            'Paktia', 'Paktika', 'Panjshir', 'Parwan', 'Samangan', 'Sar-e Pol', 'Takhar',
            'Urozgan', 'Wardak', 'Zabul',
        ] as $index => $name) {
            Province::withTrashed()->updateOrCreate(['name' => $name], [
                'sort_order' => ($index + 1) * 10, 'is_active' => true, 'deleted_at' => null,
            ]);
        }
        foreach (['A1 Beginner', 'A2 Elementary', 'B1 Intermediate', 'B2 Upper Intermediate', 'C1 Advanced', 'C2 Proficiency'] as $index => $name) {
            EnglishLevel::withTrashed()->updateOrCreate(['name' => $name], [
                'sort_order' => ($index + 1) * 10, 'is_active' => true, 'deleted_at' => null,
            ]);
        }
        Semester::withTrashed()->updateOrCreate(['code' => 'FALL-2027'], ['name' => 'Fall 2027', 'sort_order' => 10, 'is_active' => true, 'deleted_at' => null]);

        foreach ([
            ['name' => 'University of Toronto', 'country' => 'CA', 'city' => 'Toronto'],
            ['name' => 'Technical University of Munich', 'country' => 'DE', 'city' => 'Munich'],
            ['name' => 'University of Manchester', 'country' => 'GB', 'city' => 'Manchester'],
        ] as $data) {
            University::withTrashed()->updateOrCreate(
                ['name' => $data['name'], 'country_id' => $countries[$data['country']]->id],
                ['city' => $data['city'], 'is_active' => true, 'deleted_at' => null],
            );
        }
    }
}
