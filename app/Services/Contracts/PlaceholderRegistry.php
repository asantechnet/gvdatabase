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
    public const SYSTEM_SLUGS = ['employment', 'customer', 'company', ...self::EDUCATIONAL_SLUGS];

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
                'key' => 'customer',
                'label' => 'Customer',
                'contexts' => ['customer', 'other', ...self::EDUCATIONAL_SLUGS],
                'fields' => [
                    ['key' => 'customer.name', 'label' => 'Name'],
                    ['key' => 'customer.company_name', 'label' => 'Company'],
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
                    ['key' => 'service.duration', 'label' => 'Duration'],
                    ['key' => 'service.fee', 'label' => 'Fee'],
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
                'key' => 'company',
                'label' => 'Counterparty Company',
                'contexts' => ['other'],
                'fields' => [
                    ['key' => 'company.legal_name', 'label' => 'Legal Name'],
                    ['key' => 'company.trade_name', 'label' => 'Trade Name'],
                    ['key' => 'company.registration_number', 'label' => 'Registration Number'],
                    ['key' => 'company.tax_number', 'label' => 'Tax Number'],
                    ['key' => 'company.national_id', 'label' => 'National ID'],
                    ['key' => 'company.legal_form', 'label' => 'Legal Form'],
                    ['key' => 'company.email', 'label' => 'Email'],
                    ['key' => 'company.phone', 'label' => 'Phone'],
                    ['key' => 'company.website', 'label' => 'Website'],
                    ['key' => 'company.address_line', 'label' => 'Address Line'],
                    ['key' => 'company.city', 'label' => 'City'],
                    ['key' => 'company.province', 'label' => 'Province'],
                    ['key' => 'company.postal_code', 'label' => 'Postal Code'],
                    ['key' => 'company.country', 'label' => 'Country'],
                    ['key' => 'company.address', 'label' => 'Full Address'],
                    ['key' => 'company.industry', 'label' => 'Industry'],
                    ['key' => 'company.representative_name', 'label' => 'Representative Name'],
                    ['key' => 'company.representative_title', 'label' => 'Representative Title'],
                    ['key' => 'company.representative_phone', 'label' => 'Representative Phone'],
                    ['key' => 'company.representative_email', 'label' => 'Representative Email'],
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
            'name' => $setting?->original_company_name ?: $setting?->website_title,
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
