<?php

namespace App\Services\Contracts;

use App\Models\Applicant;
use App\Models\Employee;
use Illuminate\Support\Collection;

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
     * @param  array{employee?: ?Employee, applicant?: ?Applicant, customer?: ?Applicant, contract_number?: ?string, start_date?: ?string, end_date?: ?string, status?: ?string}  $context
     * @return array{content: string, unresolved: list<string>, missing: list<string>}
     */
    public function resolve(string $content, array $context, ?string $typeSlug = null, bool $preserveMissing = false): array
    {
        // Older editor saves may contain labelled marks instead of machine tokens.
        $content = preg_replace_callback('/<span\b[^>]*data-placeholder-key=["\']([a-zA-Z0-9_.]+)["\'][^>]*>.*?<\/span>/is',
            fn (array $match) => '{{ '.$match[1].' }}', $content) ?? $content;
        $values = $this->buildValueMap($context);

        $unresolved = [];
        $missing = [];
        $allowed = PlaceholderRegistry::keysForSlug($typeSlug);
        // employee.identity and services.table render as HTML tables and are
        // exempt from escaping like other values.
        $raw = ['employee.identity', 'services.table'];

        $resolved = preg_replace_callback('/{{\s*([a-zA-Z0-9_.]+)\s*}}/', function (array $matches) use ($values, $allowed, &$unresolved, &$missing, $raw, $preserveMissing) {
            $key = strtolower($matches[1]);

            if (! in_array($key, $allowed, true)) {
                $unresolved[] = $matches[0];

                return $matches[0];
            }

            if (! array_key_exists($key, $values) || ($values[$key] === null || $values[$key] === '')) {
                $missing[] = $key;

                return $preserveMissing ? $matches[0] : '';
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

        // `applicant` is the primary key; `customer` is the frozen placeholder
        // alias that ContractController still supplies (see warning.md).
        if ($applicant = $context['applicant'] ?? $context['customer'] ?? null) {
            $detail = $applicant->detail();
            $values += [
                'applicant.name' => $applicant->name,
                'applicant.father_name' => $applicant->father_name,
                'applicant.father_phone' => $applicant->father_phone,
                'applicant.backup_phone' => $applicant->backup_phone,
                'applicant.phone' => $applicant->contact,
                'applicant.email' => $applicant->email,
                'applicant.address' => $applicant->address,
                'applicant.passport_id' => $detail?->passport_id,
                'applicant.grade_score' => $detail?->grade_score,
                'applicant.country' => in_array($applicant->service, ['scholarship', 'private'], true) ? $detail?->country?->name : null,
                'applicant.university' => in_array($applicant->service, ['scholarship', 'private'], true) ? $detail?->university?->name : null,
                'applicant.education_level' => in_array($applicant->service, ['scholarship', 'private'], true) ? $detail?->educationLevel?->name : null,
                'applicant.semester' => in_array($applicant->service, ['scholarship', 'private'], true) ? $detail?->semester?->name : null,
                'applicant.university_fee' => $detail?->university_fee,
                'applicant.scholarship' => $applicant->service === 'scholarship' ? $detail?->scholarship?->name : null,
                'applicant.test' => $applicant->service === 'test_offer' ? $detail?->test?->name : null,
                'applicant.program' => $applicant->service === 'academy' ? $detail?->program?->name : null,
                'applicant.class' => $applicant->service === 'academy' ? $detail?->academyClass?->name : null,
                'applicant.subjects' => $applicant->favoriteSubjects->pluck('name')->join(', '),
                'applicant.skills' => $applicant->skills->pluck('name')->join(', '),
                'applicant.teachers' => $applicant->teachers->pluck('name')->join(', '),
                'applicant.responsible_employees' => $applicant->responsibleEmployees->pluck('name')->filter()->join(', '),
                // Legacy templates keep rendering; the plural key is canonical.
                'applicant.admission_employee' => $applicant->responsibleEmployees->pluck('name')->filter()->join(', ')
                    ?: $applicant->admissionEmployee?->name,
            ];
            $values += [
                'customer.name' => $applicant->name,
                'customer.phone' => $applicant->contact,
                'customer.email' => $applicant->email,
                'customer.address' => $applicant->address,
            ];
        }
        if ($line = $context['built_in_line'] ?? null) {
            $values += [
                'service.name' => $line->serviceLabel(),
                'service.category' => $line->service,
                'service.description' => $line->serviceLabel(),
                'service.application_fee' => $line->application_fee,
                'service.visa_fee' => $line->visa_fee,
                'service.booking_fee' => $line->booking_fee,
                'service.service_fee' => $line->service_fee,
                'service.remote_fee' => $line->remote_fee,
                'service.fee' => $line->fee,
                'service.discount' => $line->discount,
                'service.currency' => $line->currency?->code,
                'service.currency_rate' => $line->currency_rate,
                'service.payable_total' => $line->payableTotal(),
                'services.names' => $line->serviceLabel(),
                'services.count' => 1,
                'services.total_fee' => $line->fee,
                'services.table' => $this->servicesTableHtml(collect([['name' => $line->serviceLabel(), 'fee' => $line->fee]])),
            ];
        }
        $values += [
            'contract.number' => $context['contract_number'] ?? null,
            'contract.start_date' => $context['start_date'] ?? null,
            'contract.end_date' => $context['end_date'] ?? ($context['built_in_line'] ?? null)?->contract?->end_date?->format('Y-m-d'),
            'contract.status' => $context['status'] ?? null,
        ];

        return $values;
    }

    /** @param Collection<int, array{name: ?string, fee: mixed}> $rows */
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
