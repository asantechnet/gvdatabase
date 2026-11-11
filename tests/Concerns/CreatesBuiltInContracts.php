<?php

namespace Tests\Concerns;

use App\Models\AcademyClass;
use App\Models\ContractType;
use App\Models\Employee;
use App\Models\Country;
use App\Models\Currency;
use App\Models\EducationLevel;
use App\Models\Language;
use App\Models\Position;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Test;
use App\Services\Contracts\BuiltInContractTemplateService;

trait CreatesBuiltInContracts
{
    protected function builtInContractPayload(string $service, array $data = []): array
    {
        $language = Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true]);
        $country = Country::firstOrCreate(['code' => 'QA'], ['name' => 'Default destination']);
        $countryId = in_array($service, ['scholarship', 'private'], true) ? ($data['country_id'] ?? $country->id) : null;
        $test = $service === 'test_offer' ? Test::firstOrCreate(['name' => 'IELTS'], ['is_active' => true]) : null;
        $template = app(BuiltInContractTemplateService::class)->ensure($service);
        $version = $template->versions()->firstOrCreate([
            'contract_template_id' => $template->id,
            'country_id' => $countryId,
            'test_id' => $test?->id,
            'language_id' => $language->id,
            'version_number' => 1,
        ], [
            'name' => 'Active agreement', 'status' => 'active', 'is_current' => true,
            'current_scope_key' => implode(':', [$template->id, $countryId ?? 0, $test?->id ?? 0, $language->id]),
            'content' => '<p>{{contract.number}} {{customer.name}} {{service.name}}</p>',
        ]);

        $payload = [...[
            'name' => 'Built-in applicant', 'contact' => '0701234567',
            'fee' => 100, 'discount' => 0,
            'currency_id' => Currency::factory()->create(['rate' => 1])->id, 'currency_rate' => 1,
            'language_id' => $language->id, 'contract_template_version_id' => $version->id,
        ], ...$data];
        $fee = (float) $payload['fee'];

        $details = match ($service) {
            'scholarship' => [
                'father_name' => 'Father', 'father_phone' => '0701111111',
                'grade_score' => 80, 'country_id' => $countryId,
                'education_level_id' => EducationLevel::firstOrCreate(['name' => 'Bachelor'], ['is_active' => true])->id,
                'favorite_subject_ids' => [Subject::firstOrCreate(['name' => 'Mathematics'], ['is_active' => true])->id],
                'application_fee' => $fee,
            ],
            'private' => [
                'father_name' => 'Father', 'father_phone' => '0701111111',
                'grade_score' => 80, 'country_id' => $countryId,
                'education_level_id' => EducationLevel::firstOrCreate(['name' => 'Bachelor'], ['is_active' => true])->id,
                'semester_id' => Semester::firstOrCreate(['code' => 'FALL'], ['name' => 'Fall', 'is_active' => true])->id,
                'contract_type_id' => ContractType::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer'])->id,
                'favorite_subject_ids' => [Subject::firstOrCreate(['name' => 'Mathematics'], ['is_active' => true])->id],
                'university_fee' => 0,
                'application_fee_min' => round($fee / 2, 2),
                'application_fee_max' => $fee,
                'visa_fee' => 0,
            ],
            'test_offer' => [
                'backup_phone' => '0702222222', 'test_id' => $test?->id,
                'skill_ids' => [Skill::firstOrCreate(['name' => 'Writing'], ['is_active' => true])->id],
                'booking_fee' => round($fee / 3, 2),
                'service_fee' => round($fee / 3, 2),
                'remote_fee' => round($fee - (2 * round($fee / 3, 2)), 2),
            ],
            default => [
                'student_id' => 'STU-1001',
                'service_fee' => $fee,
                'program_id' => Program::firstOrCreate(['name' => 'Foundation'], ['is_active' => true])->id,
                'teacher_ids' => [Employee::factory()->create([
                    'name' => 'Jane Doe',
                    'position_id' => Position::firstOrCreate(['system_key' => 'teacher'], ['title' => 'Teacher', 'status' => 'active'])->id,
                ])->id],
                // An Academy term needs a class end date; without one the
                // enrollment service correctly refuses to create the line.
                'class_id' => AcademyClass::firstOrCreate(
                    ['name' => 'Class A'],
                    ['is_active' => true, 'ends_on' => now()->addYear()->toDateString()],
                )->id,
            ],
        };

        $payload += $details;

        if (! empty($payload['parent_contract_id']) && $service !== 'academy') {
            foreach (['name', 'contact', 'father_name', 'father_phone', 'backup_phone', 'email', 'address',
                'admission_employee_id', 'responsible_employee_id', 'passport_id', 'grade_score', 'country_id',
                'contract_type_id', 'education_level_id', 'semester_id', 'university_id', 'university_fee',
                'favorite_subject_ids', 'scholarship_id', 'test_id', 'skill_ids', 'student_id', 'program_id',
                'teacher_ids', 'class_id'] as $field) {
                unset($payload[$field]);
            }
        }

        return $payload;
    }

    protected function createBuiltInContract(string $service, array $data = []): array
    {
        return $this->postJson('/api/contracts/'.$service, $this->builtInContractPayload($service, $data))
            ->assertCreated()->json('data');
    }
}
