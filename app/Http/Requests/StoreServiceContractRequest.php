<?php

namespace App\Http\Requests;

use App\Models\AcademyClass;
use App\Models\University;
use App\Support\AgencyStaffAssignment;
use App\Support\BuiltInService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One service contract form, four strict field sets.
 *
 * Each built-in service accepts only its own identity, academic and money
 * fields; fields that belong to another service stay prohibited. Fields that
 * used to be shared but are no longer displayed for a service (for example a
 * scholarship university) remain optional so older clients and stored records
 * can still be submitted or corrected.
 */
class StoreServiceContractRequest extends FormRequest
{
    private const SCHOLARSHIP_FIELDS = [
        'passport_id', 'grade_score', 'country_id', 'contract_type_id',
        'education_level_id', 'semester_id', 'university_id', 'university_fee', 'scholarship_id',
    ];

    private const TEST_FIELDS = ['passport_id', 'test_id', 'skill_ids'];

    private const ACADEMY_FIELDS = ['student_id', 'program_id', 'teacher_ids', 'class_id'];

    public function authorize(): bool
    {
        if ($this->has('responsible_employee_ids')
            && ! $this->user()?->hasPermission('assign_responsible_employees')) {
            return false;
        }

        return $this->user()?->hasPermission($this->isMethod('post') ? 'create_contracts' : 'edit_contracts') ?? false;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $service = $this->route('service');
            if ($service === BuiltInService::PRIVATE && $this->filled('application_fee_min') && $this->filled('application_fee_max')
                && (float) $this->input('application_fee_max') < (float) $this->input('application_fee_min')) {
                $validator->errors()->add('application_fee_max', 'The maximum application fee cannot be lower than the minimum.');
            }

            if (in_array($service, [BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE], true)
                && $this->filled('university_id') && $this->filled('country_id')
                && ! University::query()->whereKey($this->input('university_id'))->where('country_id', $this->input('country_id'))->exists()) {
                $validator->errors()->add('university_id', 'The selected university does not belong to the selected country.');
            }

            if ($service === BuiltInService::ACADEMY && $this->filled('class_id')) {
                $class = AcademyClass::query()->find($this->input('class_id'));
                if ($class) {
                    foreach (['program_id' => 'programme'] as $field => $label) {
                        if ($class->{$field} !== null && $this->filled($field)
                            && (int) $this->input($field) !== (int) $class->{$field}) {
                            $validator->errors()->add($field, "The selected {$label} does not match the class.");
                        }
                    }
                }
            }

            $fields = match ($service) {
                BuiltInService::PRIVATE => ['application_fee_max', 'visa_fee'],
                BuiltInService::SCHOLARSHIP => ['application_fee'],
                BuiltInService::TEST_OFFER => ['booking_fee', 'service_fee', 'remote_fee'],
                BuiltInService::ACADEMY => ['service_fee'],
                default => [],
            };
            if ($fields !== [] && ! collect($fields)->contains(fn (string $field) => $this->input($field) === null)) {
                $sum = collect($fields)->sum(fn (string $field) => (float) $this->input($field, 0));
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
        $allowsServiceFields = ! $extending || $service === BuiltInService::ACADEMY;
        // An academy extension keeps its applicant editable, so identity fields
        // are accepted; every other extension copies the parent applicant and
        // must not carry identity changes.
        $identity = $extending && $service !== BuiltInService::ACADEMY ? 'prohibited' : 'nullable';
        $requiredIdentity = fn (string $field) => $extending && $service !== BuiltInService::ACADEMY
            ? ['prohibited']
            : ['required', 'string', $field];

        $rules = [
            'parent_contract_id' => ['nullable', 'integer', $exists('contracts')],
            'marketer' => ['prohibited'],
            'marketer_id' => ['prohibited'],
            'name' => [$extending && $service !== BuiltInService::ACADEMY ? 'prohibited' : 'required', 'string', 'max:255'],
            'father_name' => [$identity, 'string', 'max:255'],
            'father_phone' => [$identity, 'string', 'max:50'],
            'backup_phone' => $service === BuiltInService::TEST_OFFER
                ? $requiredIdentity('max:50') : [$identity, 'string', 'max:50'],
            'contact' => $requiredIdentity('max:255'),
            'email' => [$identity, 'email', 'max:255'],
            'address' => [$identity, 'string', 'max:1000'],
            'responsible_employee_id' => [$identity, 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')->where('status', 'active')],
            // The singular admission field is retired. Responsibility is a
            // plural assignment accepted only on case-bearing services.
            'admission_employee_id' => ['prohibited'],
            'responsible_employee_ids' => in_array($service, BuiltInService::CASE_BEARING, true)
                ? ['nullable', 'array', 'max:100']
                : ['prohibited'],
            'responsible_employee_ids.*' => ['integer', 'distinct', ...AgencyStaffAssignment::rules('admission')],
            'fee' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'discount' => ['required', 'numeric', 'min:0', 'lte:fee', 'decimal:0,2'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->whereNull('deleted_at')],
            'currency_rate' => ['required', 'numeric', 'gt:0', 'max:9999999', 'decimal:0,8'],
            'language_id' => ['nullable', 'integer', Rule::exists('languages', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'start_date' => ['prohibited'],
            // Academy prints only its slip and has no template/language choice,
            // so the server resolves its hidden template's current version.
            'contract_template_version_id' => $service === BuiltInService::ACADEMY
                ? ['nullable', 'integer', 'exists:contract_template_versions,id']
                : ['required', 'integer', 'exists:contract_template_versions,id'],
            'company_signatory_name' => ['nullable', 'string', 'max:255'],
            'notes' => [$identity, 'string', 'max:10000'],
        ];

        // Money fields: only the service that prices with them accepts them.
        $rules['application_fee'] = match ($service) {
            BuiltInService::SCHOLARSHIP => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            BuiltInService::PRIVATE => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            default => ['prohibited'],
        };
        $rules['application_fee_min'] = $service === BuiltInService::PRIVATE
            ? ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2']
            : ['prohibited'];
        $rules['application_fee_max'] = $service === BuiltInService::PRIVATE
            ? ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2']
            : ['prohibited'];
        $rules['visa_fee'] = $service === BuiltInService::PRIVATE
            ? ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2']
            : ['prohibited'];
        foreach (['booking_fee', 'remote_fee'] as $field) {
            $rules[$field] = $service === BuiltInService::TEST_OFFER
                ? ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2']
                : ['prohibited'];
        }
        $rules['service_fee'] = match ($service) {
            BuiltInService::TEST_OFFER, BuiltInService::ACADEMY => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            default => ['prohibited'],
        };
        $rules['paid_fee'] = $service === BuiltInService::ACADEMY && $this->isMethod('post')
            ? ['nullable', 'numeric', 'min:0', 'lte:fee', 'max:9999999999999', 'decimal:0,2']
            : ['prohibited'];

        // A portal login issued by the same save. The form generates and shows
        // these credentials before submitting, so the account is created with
        // exactly the pair the operator saw. Editing a contract never touches
        // the account.
        $issuing = $this->isMethod('post');
        $rules['create_portal_user'] = $issuing ? ['sometimes', 'boolean'] : ['prohibited'];
        $rules['portal_user_email'] = $issuing
            ? ['required_if_accepted:create_portal_user', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')]
            : ['prohibited'];
        $rules['portal_user_password'] = $issuing
            ? ['required_if_accepted:create_portal_user', 'nullable', 'string', 'min:8', 'max:255']
            : ['prohibited'];

        // Service detail fields.
        $detailFields = match ($service) {
            BuiltInService::PRIVATE => [
                'passport_id' => ['nullable', 'string', 'max:64'],
                'grade_score' => ['required', 'numeric', 'min:0', 'max:100'],
                'country_id' => ['required', 'integer', $exists('countries')],
                'contract_type_id' => ['nullable', 'integer', $exists('contract_types')],
                'education_level_id' => ['required', 'integer', $exists('education_levels')],
                'semester_id' => ['required', 'integer', $exists('semesters')],
                'university_id' => ['nullable', 'integer', $exists('universities')],
                'university_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'favorite_subject_ids' => ['nullable', 'array', 'max:100'],
            ],
            BuiltInService::SCHOLARSHIP => [
                'passport_id' => ['nullable', 'string', 'max:64'],
                'grade_score' => ['required', 'numeric', 'min:0', 'max:100'],
                'country_id' => ['required', 'integer', $exists('countries')],
                'contract_type_id' => ['nullable', 'integer', $exists('contract_types')],
                'education_level_id' => ['required', 'integer', $exists('education_levels')],
                'semester_id' => ['nullable', 'integer', $exists('semesters')],
                'university_id' => ['nullable', 'integer', $exists('universities')],
                'university_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'favorite_subject_ids' => ['required', 'array', 'min:1', 'max:100'],
                'scholarship_id' => ['nullable', 'integer', $exists('educational_scholarships')],
            ],
            BuiltInService::TEST_OFFER => [
                'passport_id' => ['nullable', 'string', 'max:64'],
                'test_id' => ['required', 'integer', $exists('tests')],
                'skill_ids' => ['required', 'array', 'min:1', 'max:100'],
            ],
            BuiltInService::ACADEMY => [
                // The student record and number are created from the contract;
                // older clients may still supply their own number.
                'student_id' => ['nullable', 'string', 'max:64'],
                'program_id' => ['required', 'integer', $exists('programs')],
                'teacher_ids' => ['required', 'array', 'min:1', 'max:100'],
                'class_id' => ['required', 'integer', $exists('academy_classes')],
            ],
            default => [],
        };
        foreach ($detailFields as $key => $fieldRules) {
            $rules[$key] = $allowsServiceFields ? $fieldRules : ['prohibited'];
        }
        // Fields exclusive to another service are rejected, not silently ignored.
        $allDetailFields = [...self::SCHOLARSHIP_FIELDS, ...self::TEST_FIELDS, ...self::ACADEMY_FIELDS];
        foreach (array_diff($allDetailFields, array_keys($detailFields)) as $field) {
            if (! array_key_exists($field, $rules)) {
                $rules[$field] = ['prohibited'];
            }
        }
        // Application fee is derived from the private range maximum; a client
        // that still sends the legacy single value must agree with it.
        if ($service === BuiltInService::PRIVATE && $this->filled('application_fee') && $this->filled('application_fee_max')
            && abs((float) $this->input('application_fee') - (float) $this->input('application_fee_max')) > 0.001) {
            $rules['application_fee'][] = function (string $attribute, mixed $value, \Closure $fail) {
                if (abs((float) $value - (float) $this->input('application_fee_max')) > 0.001) {
                    $fail('The application fee must equal the maximum of the selected range.');
                }
            };
        }

        if (in_array($service, BuiltInService::CASE_BEARING, true)) {
            $rules['responsible_employee_ids.*'] = ['integer', 'distinct', ...AgencyStaffAssignment::rules('admission')];
        }
        foreach (['favorite_subject_ids' => 'subjects', 'skill_ids' => 'skills', 'teacher_ids' => 'employees'] as $key => $table) {
            if (isset($rules[$key]) && ! in_array('prohibited', $rules[$key], true)) {
                $rules["{$key}.*"] = ['integer', 'distinct', $exists($table)];
            }
        }
        if (in_array($service, [BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE], true)) {
            $rules['responsible_employee_ids.*'] = [
                'integer', 'distinct',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! AgencyStaffAssignment::query('admission')->whereKey($value)->exists()) {
                        $fail('Choose an active employee with the admission position or role.');
                    }
                },
            ];
        }
        foreach (['applicant_id', 'service', 'service_id', 'service_ids', 'service_lines', 'package_id', 'customer_package_id', 'template_id', 'status', 'content', 'amount_paid', 'payment_status'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
