<?php

namespace App\Http\Requests;

use App\Support\AgencyStaffAssignment;
use App\Support\BuiltInService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('create_contracts') ?? false;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $fields = match ($this->route('service')) {
                BuiltInService::PRIVATE => ['application_fee', 'visa_fee'],
                BuiltInService::SCHOLARSHIP => ['application_fee'],
                BuiltInService::TEST_OFFER => ['booking_fee', 'service_fee', 'remote_fee'],
                BuiltInService::ACADEMY => ['service_fee'],
                default => [],
            };
            if (collect($fields)->contains(fn ($field) => $this->filled($field)) && ! $validator->errors()->isNotEmpty()) {
                $sum = collect($fields)->sum(fn ($field) => (float) $this->input($field, 0));
                if (abs($sum - (float) $this->input('fee')) > 0.001) {
                    $validator->errors()->add('fee', 'The total fee must equal the itemised charges.');
                }
            }
        });
    }

    public function rules(): array
    {
        $exists = fn (string $table) => Rule::exists($table, 'id')->whereNull('deleted_at');
        $service = $this->route('service');
        $extending = $this->filled('parent_contract_id');
        // An academy extension keeps its applicant editable, so identity fields
        // are accepted; every other extension copies the parent applicant and
        // must not carry identity changes.
        $identity = $extending && $service !== BuiltInService::ACADEMY ? 'prohibited' : 'nullable';
        $rules = [
            'parent_contract_id' => ['nullable', 'integer', $exists('contracts')],
            'marketer' => ['prohibited'],
            'marketer_id' => ['prohibited'],
            'name' => [$extending && $service !== BuiltInService::ACADEMY ? 'prohibited' : 'required', 'string', 'max:255'],
            'father_name' => [$identity, 'string', 'max:255'],
            'backup_phone' => [$identity, 'string', 'max:50'],
            'father_phone' => [$identity, 'string', 'max:50'],
            'contact' => [$identity, 'string', 'max:255'],
            'email' => [$identity, 'email', 'max:255'],
            'address' => [$identity, 'string', 'max:1000'],
            'tax_number' => [$identity, 'string', 'max:255'],
            'responsible_employee_id' => [$identity, 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'admission_employee_id' => $extending && $service !== BuiltInService::ACADEMY ? ['prohibited'] : AgencyStaffAssignment::rules('admission'),
            'fee' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'discount' => ['required', 'numeric', 'min:0', 'lte:fee', 'decimal:0,2'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'currency_rate' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,8'],
            'language_id' => ['required', 'integer', Rule::exists('languages', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'start_date' => ['prohibited'],
            'contract_template_version_id' => ['required', 'integer', 'exists:contract_template_versions,id'],
            'company_signatory_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
        $fields = [
            'passport_id' => ['nullable', 'string', 'max:64'],
            'grade_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'country_id' => ['nullable', 'integer', $exists('countries')],
            'contract_type_id' => ['nullable', 'integer', $exists('contract_types')],
            'education_level_id' => ['nullable', 'integer', $exists('education_levels')],
            'semester_id' => ['nullable', 'integer', $exists('semesters')],
            'university_id' => ['nullable', 'integer', $exists('universities')],
            'university_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'favorite_subject_ids' => ['nullable', 'array', 'max:100'],
            'scholarship_id' => ['nullable', 'integer', $exists('educational_scholarships')],
            'class_id' => ['nullable', 'integer', $exists('academy_classes')],
            'test_id' => ['nullable', 'integer', $exists('tests')],
            'skill_ids' => ['nullable', 'array', 'max:100'],
            'program_id' => ['nullable', 'integer', $exists('programs')],
            'time_id' => ['nullable', 'integer', $exists('times')],
            'duration_id' => ['nullable', 'integer', $exists('durations')],
            'teacher_ids' => ['nullable', 'array', 'max:100'],
        ];
        $allowed = match ($service) {
            BuiltInService::SCHOLARSHIP => ['passport_id', 'grade_score', 'country_id', 'contract_type_id', 'education_level_id', 'semester_id', 'favorite_subject_ids', 'scholarship_id'],
            BuiltInService::PRIVATE => ['passport_id', 'grade_score', 'country_id', 'contract_type_id', 'education_level_id', 'semester_id', 'favorite_subject_ids'],
            BuiltInService::TEST_OFFER => ['passport_id', 'test_id', 'skill_ids'],
            BuiltInService::ACADEMY => ['program_id', 'time_id', 'duration_id', 'teacher_ids', 'class_id'],
            default => [],
        };
        $allowsServiceFields = (! $extending || $service === BuiltInService::ACADEMY);
        foreach ($fields as $key => $fieldRules) {
            $rules[$key] = $allowsServiceFields && in_array($key, $allowed, true) ? $fieldRules : ['prohibited'];
        }
        foreach (['favorite_subject_ids' => 'subjects', 'skill_ids' => 'skills', 'teacher_ids' => 'teachers'] as $key => $table) {
            if ($allowsServiceFields && in_array($key, $allowed, true)) {
                $rules["{$key}.*"] = ['integer', 'distinct', $exists($table)];
            }
        }
        foreach (['applicant_id', 'service', 'service_id', 'service_ids', 'service_lines', 'package_id', 'customer_package_id', 'template_id', 'status', 'content', 'amount_paid', 'payment_status'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        foreach (['application_fee' => [BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE], 'visa_fee' => [BuiltInService::PRIVATE], 'booking_fee' => [BuiltInService::TEST_OFFER], 'service_fee' => [BuiltInService::TEST_OFFER, BuiltInService::ACADEMY], 'remote_fee' => [BuiltInService::TEST_OFFER]] as $field => $services) {
            $rules[$field] = in_array($service, $services, true) ? ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'] : ['prohibited'];
        }

        return $rules;
    }
}
