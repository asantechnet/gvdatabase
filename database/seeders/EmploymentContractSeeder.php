<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Employee;
use App\Models\Language;
use App\Models\Position;
use App\Services\Contracts\PlaceholderRegistry;
use App\Services\Contracts\PlaceholderResolver;
use App\Support\CompanyProfile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateInterval;
use Illuminate\Database\Seeder;

class EmploymentContractSeeder extends Seeder
{
    /**
     * Employment templates (one per position, the admin convention) and one
     * active employment contract per seeded employee.
     *
     * The term is derived here from the employee's free-text `contract_duration`
     * for demo purposes only and rolled forward to the current renewal, so the
     * seeded contracts are live. "Permanent" has no end date. One finite
     * contract is deliberately placed inside the one-month deadline window so
     * `contracts:send-expiry-alerts` has a demo target.
     */
    public function run(): void
    {
        $type = ContractType::query()->where('slug', 'employment')->first();
        $language = Language::query()->where('is_active', true)->orderBy('id')->first()
            ?? Language::query()->orderBy('id')->first();
        if (! $type || ! $language) {
            return;
        }

        $today = CarbonImmutable::now(app(CompanyProfile::class)->timezone())->startOfDay();
        $resolver = app(PlaceholderResolver::class);
        $signatory = PlaceholderRegistry::ourCompanyValues()['name'];

        $templates = [];
        foreach (Position::query()->orderBy('id')->get() as $position) {
            $templates[$position->id] = $this->ensureTemplate($type, $position);
        }
        if ($templates === []) {
            return;
        }

        $employees = Employee::query()->with(['position', 'office', 'user'])->orderBy('id')->get();
        $demoAlertEmployeeId = $employees
            ->filter(fn (Employee $employee) => $this->durationInterval($employee->contract_duration) !== null)
            ->last()?->id;

        foreach ($employees as $employee) {
            $template = $templates[$employee->position_id] ?? reset($templates);
            $version = $template->versions()
                ->where('status', 'active')->where('is_current', true)->orderBy('id')->first();
            if (! $version) {
                continue;
            }

            $start = $employee->join_date?->toDateString() ?? $today->subYear()->toDateString();
            $end = $this->resolveEndDate($employee->contract_duration, $employee->join_date ?? $today, $today);
            if ($employee->id === $demoAlertEmployeeId) {
                // Demo target for the one-month deadline alert.
                $end = $today->addDays(20)->toDateString();
            }

            $contract = Contract::withTrashed()->firstOrNew([
                'employee_id' => $employee->id,
                'contract_role' => Contract::ROLE_PRIMARY,
                'service' => null,
            ]);
            $contract->fill([
                'template_id' => $template->id,
                'contract_type_id' => $type->id,
                'contract_template_version_id' => $version->id,
                'contract_language_id' => $language->id,
                'name' => $template->name,
                'type' => $type->name,
                'cover_text' => $template->cover_text,
                'content' => $contract->content ?: '',
                'status' => 'active',
                'start_date' => $start,
                'end_date' => $end,
                'employee_id' => $employee->id,
                'counterparty' => $employee->name,
                'counterparty_company' => $employee->position?->title,
                'counterparty_contact' => $employee->contact,
                'counterparty_email' => $employee->user?->email,
                'counterparty_address' => $employee->addr,
                'company_signatory_name' => $signatory,
                'is_custom' => false,
                'is_cancelled' => false,
            ]);
            $contract->deleted_at = null;
            $contract->save();

            // Render the template against the real employee, mirroring the
            // generic creation path so placeholders never survive in content.
            $rendered = $resolver->resolve($version->content, [
                'employee' => $employee,
                'contract_number' => $contract->contract_number,
                'start_date' => $start,
                'status' => 'active',
            ], 'employment');

            $contract->forceFill([
                'content' => $rendered['content'],
                'snapshot_data' => [
                    'parties' => [
                        'our_company' => PlaceholderRegistry::ourCompanyValues($signatory),
                        'counterparty' => [
                            'label' => $employee->name,
                            'fields' => [
                                'counterparty' => $employee->name,
                                'counterparty_company' => $employee->position?->title,
                                'counterparty_email' => $employee->user?->email,
                                'counterparty_contact' => $employee->contact,
                                'counterparty_address' => $employee->addr,
                            ],
                        ],
                    ],
                    'entity' => ['employee' => $this->employeeSnapshot($employee)],
                    'template_version' => [
                        'id' => $version->id, 'name' => $version->name,
                        'version_number' => $version->version_number,
                        'template_id' => $template->id, 'template_name' => $template->name,
                        'language_id' => $language->id, 'language_name' => $language->name,
                        'language_code' => $language->code,
                    ],
                ],
            ])->save();
        }
    }

    private function ensureTemplate(ContractType $type, Position $position): ContractTemplate
    {
        $template = ContractTemplate::withTrashed()->firstOrNew([
            'position_id' => $position->id,
            'contract_type_id' => $type->id,
            'name' => "Employment Agreement — {$position->title}",
        ]);
        $template->fill([
            'description' => "Standard employment terms for {$position->title}.",
            'is_active' => true,
            'cover_text' => 'Employment agreement',
            'content' => $this->content(),
        ]);
        $template->deleted_at = null;
        $template->save();

        // Employment templates are versioned by name only — no country/language scope.
        $version = ContractTemplateVersion::withTrashed()->firstOrNew([
            'contract_template_id' => $template->id,
            'country_id' => null,
            'test_id' => null,
            'language_id' => null,
            'version_number' => 1,
        ]);
        $version->fill([
            'name' => "Employment Agreement — {$position->title}",
            'content' => $this->content(),
            'status' => 'active',
            'is_current' => true,
        ]);
        $version->deleted_at = null;
        $version->current_scope_key = null;
        $version->save();
        $version->current_scope_key = $version->scopeKey();
        $version->save();

        return $template;
    }

    private function content(): string
    {
        return '<h1>Employment Agreement</h1>'
            .'<p>This agreement is made between {{our_company.name}} and {{employee.name}} for the position of {{position.name}}, effective {{contract.start_date}}.</p>'
            .'{{employee.identity}}'
            .'<p>The employee agrees to perform the duties of the position in line with the firm&rsquo;s policies and the terms recorded in this contract.</p>';
    }

    /** Parses the free-text employee duration ("2 years", "6 months") into an interval. */
    private function durationInterval(?string $duration): ?DateInterval
    {
        if (! $duration || ! preg_match('/(\d+)\s*(year|month|week|day)/i', $duration, $match)) {
            return null;
        }

        $value = max(1, (int) $match[1]);

        return match (strtolower($match[2])) {
            'year' => new DateInterval("P{$value}Y"),
            'month' => new DateInterval("P{$value}M"),
            'week' => new DateInterval("P{$value}W"),
            default => new DateInterval("P{$value}D"),
        };
    }

    private function resolveEndDate(?string $duration, CarbonInterface $join, CarbonImmutable $today): ?string
    {
        $interval = $this->durationInterval($duration);
        if (! $interval) {
            return null;
        }

        $end = CarbonImmutable::instance($join)->startOfDay();
        $guard = 0;
        while ($end->lessThanOrEqualTo($today) && $guard < 500) {
            $end = $end->add($interval);
            $guard++;
        }

        return $end->toDateString();
    }

    /** @return array<string, mixed> */
    private function employeeSnapshot(Employee $employee): array
    {
        return [
            'id' => $employee->id, 'name' => $employee->name, 'father_name' => $employee->father_name,
            'national_id' => $employee->id_number, 'phone' => $employee->contact,
            'email' => $employee->user?->email, 'address' => $employee->addr,
            'education' => $employee->education_lvl, 'position' => $employee->position?->title,
            'department' => $employee->department, 'office' => $employee->office?->name,
            'start_date' => $employee->join_date?->format('Y-m-d'),
            'contract_duration' => $employee->contract_duration,
            'salary' => $employee->salary,
        ];
    }
}
