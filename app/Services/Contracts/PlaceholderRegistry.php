<?php

namespace App\Services\Contracts;

use App\Models\Setting;

/**
 * Centralized placeholder definition.
 *
 * Every placeholder that may appear in a template or contract is declared
 * here exactly once: its stable machine key, a human label, and the
 * contract-type contexts in which it is available. The frontend fetches
 * this configuration from the API instead of duplicating strings, and the
 * resolver refuses to touch any key not present in these maps.
 */
class PlaceholderRegistry
{
    public const EDUCATIONAL_SLUGS = ['educational-service', 'scholarship-service', 'test-service', 'visa-service'];

    public const SYSTEM_SLUGS = ['employment', 'customer', ...self::EDUCATIONAL_SLUGS];

    /**
     * Placeholder groups. Each field key is globally unique and stable.
     *
     * @return array<int, array{key: string, label: string, contexts: list<string>, fields: list<array{key: string, label: string}>}>
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'employee',
                'label' => 'Employee',
                'contexts' => ['employment', 'other'],
                'fields' => [
                    ['key' => 'employee.name', 'label' => 'Full Name'],
                    ['key' => 'employee.father_name', 'label' => 'Father Name'],
                    ['key' => 'employee.national_id', 'label' => 'National ID'],
                    ['key' => 'employee.phone', 'label' => 'Phone'],
                    ['key' => 'employee.email', 'label' => 'Email'],
                    ['key' => 'employee.address', 'label' => 'Address'],
                    ['key' => 'employee.position', 'label' => 'Position'],
                    ['key' => 'employee.department', 'label' => 'Department'],
                    ['key' => 'employee.office', 'label' => 'Office'],
                    ['key' => 'employee.start_date', 'label' => 'Start Date'],
                    ['key' => 'employee.contract_duration', 'label' => 'Contract Duration'],
                    ['key' => 'employee.salary', 'label' => 'Salary'],
                    ['key' => 'employee.identity', 'label' => 'Identity Table'],
                ],
            ],
            [
                'key' => 'position',
                'label' => 'Position',
                'contexts' => ['employment', 'other'],
                'fields' => [
                    ['key' => 'position.name', 'label' => 'Position Title'],
                    ['key' => 'position.description', 'label' => 'Description'],
                    ['key' => 'position.salary', 'label' => 'Base Salary'],
                ],
            ],
            [
                'key' => 'applicant',
                'label' => 'Applicant',
                'contexts' => ['customer', 'other'],
                'fields' => [
                    ['key' => 'applicant.name', 'label' => 'Full Name'],
                    ['key' => 'applicant.father_name', 'label' => 'Father Name'],
                    ['key' => 'applicant.father_phone', 'label' => 'Father Phone'],
                    ['key' => 'applicant.backup_phone', 'label' => 'Backup Phone'],
                    ['key' => 'applicant.phone', 'label' => 'Phone'],
                    ['key' => 'applicant.email', 'label' => 'Email'],
                    ['key' => 'applicant.address', 'label' => 'Address'],
                    ['key' => 'applicant.passport_id', 'label' => 'Passport Number'],
                    ['key' => 'applicant.grade_score', 'label' => 'Grade Percentage'],
                    ['key' => 'applicant.country', 'label' => 'Destination Country'],
                    ['key' => 'applicant.university', 'label' => 'University'],
                    ['key' => 'applicant.education_level', 'label' => 'Education Level'],
                    ['key' => 'applicant.semester', 'label' => 'Semester'],
                    ['key' => 'applicant.university_fee', 'label' => 'University Fee'],
                    ['key' => 'applicant.scholarship', 'label' => 'Scholarship'],
                    ['key' => 'applicant.test', 'label' => 'Test'],
                    ['key' => 'applicant.program', 'label' => 'Academy Program'],
                    ['key' => 'applicant.class', 'label' => 'Academy Class'],
                    ['key' => 'applicant.time', 'label' => 'Class Time'],
                    ['key' => 'applicant.duration', 'label' => 'Course Duration'],
                    ['key' => 'applicant.subjects', 'label' => 'Requested Subjects'],
                    ['key' => 'applicant.skills', 'label' => 'Skills'],
                    ['key' => 'applicant.teachers', 'label' => 'Teachers'],
                    ['key' => 'applicant.responsible_employees', 'label' => 'Responsible Employees'],
                    ['key' => 'applicant.admission_employee', 'label' => 'Responsible Employee (legacy)'],
                ],
            ],
            [
                'key' => 'customer',
                'label' => 'Legacy Applicant Fields',
                'deprecated' => true,
                'contexts' => ['customer', 'other', ...self::EDUCATIONAL_SLUGS],
                'fields' => [
                    ['key' => 'customer.name', 'label' => 'Name'],
                    ['key' => 'customer.phone', 'label' => 'Phone'],
                    ['key' => 'customer.email', 'label' => 'Email'],
                    ['key' => 'customer.address', 'label' => 'Address'],
                    ['key' => 'customer.tax_number', 'label' => 'Tax Number'],
                ],
            ],
            [
                'key' => 'service',
                'label' => 'Service',
                'contexts' => ['customer', 'other', ...self::EDUCATIONAL_SLUGS],
                'fields' => [
                    ['key' => 'service.name', 'label' => 'Service Name'],
                    ['key' => 'service.category', 'label' => 'Category'],
                    ['key' => 'service.description', 'label' => 'Description'],
                    ['key' => 'service.application_fee', 'label' => 'Application Fee'],
                    ['key' => 'service.visa_fee', 'label' => 'Visa Fee'],
                    ['key' => 'service.booking_fee', 'label' => 'Booking Fee'],
                    ['key' => 'service.service_fee', 'label' => 'Service Fee'],
                    ['key' => 'service.remote_fee', 'label' => 'Remote Fee'],
                    ['key' => 'service.fee', 'label' => 'Fee'],
                    ['key' => 'service.discount', 'label' => 'Discount'],
                    ['key' => 'service.currency', 'label' => 'Currency'],
                    ['key' => 'service.currency_rate', 'label' => 'Currency Rate'],
                    ['key' => 'service.payable_total', 'label' => 'Total After Discount'],
                ],
            ],
            [
                'key' => 'services',
                'label' => 'Services',
                'contexts' => ['customer', 'other'],
                'fields' => [
                    ['key' => 'services.names', 'label' => 'Service Names'],
                    ['key' => 'services.count', 'label' => 'Service Count'],
                    ['key' => 'services.total_fee', 'label' => 'Total Fee'],
                    ['key' => 'services.table', 'label' => 'Services Table'],
                ],
            ],
            [
                'key' => 'our_company',
                'label' => 'Our Company',
                'contexts' => ['*'],
                'fields' => [
                    ['key' => 'our_company.name', 'label' => 'Company Name'],
                    ['key' => 'our_company.short_name', 'label' => 'Short Name'],
                    ['key' => 'our_company.email', 'label' => 'Email'],
                    ['key' => 'our_company.phone', 'label' => 'Phone'],
                    ['key' => 'our_company.address', 'label' => 'Address'],
                    ['key' => 'our_company.website', 'label' => 'Website'],
                    ['key' => 'our_company.business_licence', 'label' => 'Business Licence'],
                    ['key' => 'our_company.signatory_name', 'label' => 'Authorized Signatory'],
                ],
            ],
            [
                'key' => 'contract',
                'label' => 'Contract',
                'contexts' => ['*'],
                'fields' => [
                    ['key' => 'contract.number', 'label' => 'Contract Number'],
                    ['key' => 'contract.start_date', 'label' => 'Start Date'],
                    ['key' => 'contract.end_date', 'label' => 'End Date'],
                    ['key' => 'contract.status', 'label' => 'Status'],
                ],
            ],
        ];
    }

    /**
     * Groups available for a given contract-type slug.
     * System slugs map to their context; custom types get the universal groups.
     *
     * @return array<int, array{key: string, label: string, fields: list<array{key: string, label: string}>}>
     */
    public static function groupsForSlug(?string $slug): array
    {
        return collect(self::groups())
            ->filter(fn ($group) => in_array('*', $group['contexts'], true) || ($slug !== null && in_array($slug, $group['contexts'], true)))
            ->map(fn ($group) => [
                'key' => $group['key'],
                'label' => $group['label'],
                'deprecated' => $group['deprecated'] ?? false,
                'fields' => collect($group['fields'])
                    ->map(fn ($field) => ['key' => $field['key'], 'label' => $field['label']])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function keysForSlug(?string $slug): array
    {
        return collect(self::groupsForSlug($slug))
            ->flatMap(fn ($group) => array_column($group['fields'], 'key'))
            ->values()
            ->all();
    }

    /**
     * Our-company values come from the application's own settings record,
     * so there is no second "owner company" concept to maintain.
     */
    public static function ourCompanyValues(?string $signatoryName = null): array
    {
        $setting = Setting::query()->latest('id')->first();

        return [
            // Match the company-name fallback used throughout the document UI.
            // A short company name is still a valid legal display name for a
            // generated contract when the original name has not been set.
            // A fresh installation may not have a settings record yet. Keep
            // finalized contracts renderable until the company profile is
            // completed, using the configured application name as a final
            // non-empty fallback.
            'name' => $setting?->original_company_name
                ?: $setting?->short_company_name
                ?: $setting?->website_title
                ?: config('app.name')
                ?: 'Company',
            'short_name' => $setting?->short_company_name,
            'email' => $setting?->company_email,
            'phone' => $setting?->phone,
            'address' => $setting?->physical_address,
            'website' => $setting?->website_address,
            'business_licence' => $setting?->business_licence,
            'signatory_name' => $signatoryName,
        ];
    }
}
