<?php

namespace App\Services\Contracts;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Service;

/**
 * Resolves whitelisted placeholders against explicit value maps.
 *
 * No dynamic `$model->{$key}` access is performed: every placeholder key
 * maps to a hand-written accessor below, so a malicious template can never
 * reach arbitrary model properties.
 */
class PlaceholderResolver
{
    /**
     * @param  array{employee?: ?Employee, customer?: ?Customer, company?: ?Company, contract_number?: ?string, start_date?: ?string, status?: ?string}  $context
     * @return array{content: string, unresolved: list<string>, missing: list<string>}
     */
    public function resolve(string $content, array $context, ?string $typeSlug = null): array
    {
        $values = $this->buildValueMap($context);

        $unresolved = [];
        $missing = [];
        $allowed = PlaceholderRegistry::keysForSlug($typeSlug);
        // employee.identity and services.table render as HTML tables and are
        // exempt from escaping like other values.
        $raw = ['employee.identity', 'services.table'];

        $resolved = preg_replace_callback('/{{\s*([a-zA-Z0-9_.]+)\s*}}/', function (array $matches) use ($values, $allowed, &$unresolved, &$missing, $raw) {
            $key = strtolower($matches[1]);

            if (! in_array($key, $allowed, true)) {
                $unresolved[] = $matches[0];

                return $matches[0];
            }

            if (! array_key_exists($key, $values) || ($values[$key] === null || $values[$key] === '')) {
                $missing[] = $key;

                return '';
            }

            return in_array($key, $raw, true) ? (string) $values[$key] : e((string) $values[$key]);
        }, $content);

        return [
            'content' => $resolved,
            'unresolved' => array_values(array_unique($unresolved)),
            'missing' => array_values(array_unique($missing)),
        ];
    }

    /** @return array<string, ?scalar> */
    private function buildValueMap(array $context): array
    {
        $values = [];

        foreach (PlaceholderRegistry::ourCompanyValues($context['company_signatory_name'] ?? null) as $key => $value) {
            $values["our_company.{$key}"] = $value;
        }

        if ($employee = $context['employee'] ?? null) {
            $values += [
                'employee.name' => $employee->name,
                'employee.father_name' => $employee->father_name,
                'employee.national_id' => $employee->id_number,
                'employee.phone' => $employee->contact,
                'employee.email' => $employee->user?->email,
                'employee.address' => $employee->addr,
                'employee.position' => $employee->position?->title,
                'employee.department' => $employee->department,
                'employee.office' => $employee->office?->name,
                'employee.start_date' => $employee->join_date?->format('Y-m-d'),
                'employee.contract_duration' => $employee->contract_duration,
                'employee.salary' => $employee->salary !== null ? number_format((float) $employee->salary, 2) : null,
                'employee.identity' => $this->employeeIdentityHtml($employee),
            ];
        }

        if ($position = ($context['employee'] ?? null)?->position) {
            $values += [
                'position.name' => $position->title,
                'position.description' => $position->description,
                'position.salary' => $position->salary !== null ? number_format((float) $position->salary, 2) : null,
            ];
        }

        if ($customer = $context['customer'] ?? null) {
            $values += [
                'customer.name' => $customer->name,
                'customer.company_name' => $customer->company,
                'customer.phone' => $customer->contact,
                'customer.email' => $customer->email,
                'customer.address' => $customer->address,
                'customer.tax_number' => $customer->tax_number,
            ];
        }
        $services = collect($context['services'] ?? []);
        if (($service = $context['service'] ?? null) === null && $services->isNotEmpty()) {
            // Contract lines: singular keys fall back to the first service.
            $service = $services->first()->service ?? $services->first();
        }
        if ($service = $service instanceof \App\Models\ContractService ? $service->service : $service) {
            $values += [
                'service.name' => $service->type,
                'service.category' => $service->category,
                'service.description' => $service->description,
                'service.fee' => $service->fee,
            ];
        }
        if ($services->isNotEmpty()) {
            $rows = $services->map(function ($line) {
                $service = $line instanceof \App\Models\ContractService ? $line->service : $line;
                $fee = $line instanceof \App\Models\ContractService ? ($line->fee ?? $service?->fee) : $service?->fee;

                return ['name' => $service?->type, 'fee' => $fee];
            })->filter(fn ($row) => $row['name'] !== null);
            $values += [
                'services.names' => $rows->pluck('name')->implode(', '),
                'services.count' => $rows->count(),
                'services.total_fee' => $rows->sum(fn ($row) => (float) ($row['fee'] ?? 0)),
                'services.table' => $this->servicesTableHtml($rows),
            ];
        }

        if ($company = $context['company'] ?? null) {
            $values += [
                'company.legal_name' => $company->legal_name,
                'company.trade_name' => $company->trade_name,
                'company.registration_number' => $company->registration_number,
                'company.tax_number' => $company->tax_number,
                'company.national_id' => $company->national_id,
                'company.legal_form' => $company->legal_form,
                'company.email' => $company->email,
                'company.phone' => $company->phone,
                'company.website' => $company->website,
                'company.address_line' => $company->address_line,
                'company.city' => $company->city,
                'company.province' => $company->province,
                'company.postal_code' => $company->postal_code,
                'company.country' => $company->country,
                'company.address' => collect([
                    $company->address_line, $company->city, $company->province,
                    $company->postal_code, $company->country,
                ])->filter()->implode(', '),
                'company.industry' => $company->industry,
                'company.representative_name' => $company->representative_name,
                'company.representative_title' => $company->representative_title,
                'company.representative_phone' => $company->representative_phone,
                'company.representative_email' => $company->representative_email,
            ];
        }

        $values += [
            'contract.number' => $context['contract_number'] ?? null,
            'contract.start_date' => $context['start_date'] ?? null,
            'contract.status' => $context['status'] ?? null,
        ];

        return $values;
    }

    /** @param \Illuminate\Support\Collection<int, array{name: ?string, fee: mixed}> $rows */
    private function servicesTableHtml($rows): string
    {
        $body = $rows->map(fn ($row) => '<tr><td>'.e((string) $row['name']).'</td><td>'
            .e($row['fee'] !== null ? (string) $row['fee'] : '').'</td></tr>')->implode('');

        return '<section class="contract-services"><h2>Contracted services</h2><table><thead><tr><th>Service</th><th>Fee</th></tr></thead><tbody>'
            .$body.'</tbody></table></section>';
    }

    private function employeeIdentityHtml(Employee $employee): string
    {
        $fields = [
            'Employee name' => $employee->name,
            'Father name' => $employee->father_name,
            'National ID' => $employee->id_number,
            'Position' => $employee->position?->title,
            'Department' => $employee->department,
            'Office' => $employee->office?->name,
            'Contact' => $employee->contact,
            'Email' => $employee->user?->email,
            'Address' => $employee->addr,
            'Start date' => $employee->join_date?->format('Y-m-d'),
            'Contract duration' => $employee->contract_duration,
            'Salary' => $employee->salary !== null ? number_format((float) $employee->salary, 2) : null,
        ];

        $rows = collect($fields)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $label) => '<tr><th>'.e($label).'</th><td>'.e((string) $value).'</td></tr>')
            ->implode('');

        return '<section class="employment-identity"><h2>Employee identity and employment details</h2><table><tbody>'
            .$rows.'</tbody></table></section>';
    }
}
