<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Language;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Services\Contracts\ContentSanitizer;
use App\Services\Contracts\PlaceholderRegistry;
use App\Services\Contracts\PlaceholderResolver;
use App\Support\BuiltInService;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    /**
     * One issued contract per seeded applicant, each with its service line.
     *
     * This mirrors what {@see \App\Services\Contracts\ContractGenerationService}
     * produces: a primary patient contract bound to the built-in template,
     * its active template version rendered against the applicant, a snapshot of
     * the parties/version, and a single `applicant_contract` line that carries
     * the fee, currency and payment state. Payment overrides are used
     * deliberately so the demo set shows paid, partially-paid and unpaid lines.
     *
     * A contract without rendered content or a version snapshot prints a
     * generic fallback document, so the seeded contracts must carry the same
     * data a generated one does.
     */
    public function run(): void
    {
        $customerType = ContractType::query()->where('slug', 'customer')->first();
        $currency = Currency::query()->where('is_base', true)->first() ?? Currency::query()->first();
        $language = Language::query()->where('code', 'en')->first();
        if (! $customerType || ! $currency || ! $language) {
            return;
        }

        $soldBy = Employee::query()->whereHas('position', fn ($query) => $query->where('system_key', 'counselor'))->value('id');
        $signatory = PlaceholderRegistry::ourCompanyValues()['name'];
        $resolver = app(PlaceholderResolver::class);
        $sanitizer = app(ContentSanitizer::class);
        $templates = app(BuiltInContractTemplateService::class);

        $plans = [
            BuiltInService::SCHOLARSHIP => ['fee' => 50000, 'paid' => 50000],
            BuiltInService::PRIVATE => ['fee' => 80000, 'paid' => 30000],
            BuiltInService::TEST_OFFER => ['fee' => 15000, 'paid' => 0],
            BuiltInService::ACADEMY => ['fee' => 20000, 'paid' => 20000],
        ];

        // A seeded line starts on its service's `new` stage exactly like a
        // line created through ContractGenerationService, so the service
        // pipeline and the per-line move control work on demo data too.
        $stages = [];
        foreach (array_keys($plans) as $service) {
            $stages[$service] = ApplicantStage::query()
                ->where('key', 'new')->where('is_active', true)->where('service', $service)
                ->orderByRaw('country_id IS NULL')->orderByRaw('university_id IS NULL')
                ->value('id');
        }

        foreach ($plans as $service => $plan) {
            $applicant = Applicant::query()->where('service', $service)->orderBy('id')->first();
            if (! $applicant) {
                continue;
            }

            $template = $templates->ensure($service);
            $version = $this->activeVersion($template, $language);
            if (! $version) {
                continue;
            }

            $contract = Contract::withTrashed()->firstOrNew([
                'applicant_id' => $applicant->id,
                'service' => $service,
                'contract_role' => Contract::ROLE_PRIMARY,
            ]);
            $contract->fill([
                'template_id' => $template->id,
                'contract_template_version_id' => $version->id,
                'contract_type_id' => $customerType->id,
                'contract_language_id' => $language->id,
                'sold_by_employee_id' => $soldBy,
                'name' => $template->name,
                'type' => $customerType->name,
                'cover_text' => $template->cover_text,
                'status' => 'active',
                'start_date' => now()->subDays(30)->toDateString(),
                'company_signatory_name' => $signatory,
                'counterparty' => $applicant->name,
                'counterparty_email' => $applicant->email,
                'counterparty_contact' => $applicant->contact,
                'counterparty_address' => $applicant->address,
                'is_custom' => false,
                'is_cancelled' => false,
            ]);
            $contract->deleted_at = null;
            $contract->save();

            $line = ApplicantContract::withTrashed()->firstOrNew([
                'contract_id' => $contract->id,
                'service' => $service,
            ]);
            $line->fill([
                'applicant_id' => $applicant->id,
                'fee' => $plan['fee'],
                'discount' => 0,
                'currency_id' => $currency->id,
                'currency_rate' => 1,
                'sold_by_employee_id' => $soldBy,
                'current_stage_id' => $stages[$service] ?? $applicant->current_stage_id,
                'notes' => 'Seeded service line',
            ]);
            if ($plan['paid'] > 0) {
                $line->amount_paid_overridden = true;
                $line->amount_paid = $plan['paid'];
            }
            $line->deleted_at = null;
            $line->save();

            $this->issueContent($contract, $version, $applicant, $line, $signatory, $resolver, $sanitizer);

            // Create an extension contract for each primary contract
            $extContract = Contract::withTrashed()->firstOrNew([
                'applicant_id' => $applicant->id,
                'service' => $service,
                'contract_role' => Contract::ROLE_EXTENSION,
                'parent_contract_id' => $contract->id,
            ]);
            $extContract->fill([
                'template_id' => $template->id,
                'contract_template_version_id' => $version->id,
                'contract_type_id' => $customerType->id,
                'contract_language_id' => $language->id,
                'sold_by_employee_id' => $soldBy,
                'name' => $template->name . ' (Extension)',
                'type' => $customerType->name,
                'cover_text' => $template->cover_text,
                'status' => 'active',
                'start_date' => now()->addDays(30)->toDateString(),
                'company_signatory_name' => $signatory,
                'counterparty' => $applicant->name,
                'counterparty_email' => $applicant->email,
                'counterparty_contact' => $applicant->contact,
                'counterparty_address' => $applicant->address,
                'is_custom' => false,
                'is_cancelled' => false,
            ]);
            $extContract->deleted_at = null;
            $extContract->save();

            $extLine = ApplicantContract::withTrashed()->firstOrNew([
                'contract_id' => $extContract->id,
                'service' => $service,
            ]);
            $extLine->fill([
                'applicant_id' => $applicant->id,
                'fee' => $plan['fee'],
                'discount' => 0,
                'currency_id' => $currency->id,
                'currency_rate' => 1,
                'sold_by_employee_id' => $soldBy,
                'current_stage_id' => $stages[$service] ?? $applicant->current_stage_id,
                'notes' => 'Seeded extension service line',
            ]);
            $extLine->deleted_at = null;
            $extLine->save();

            $this->issueContent($extContract, $version, $applicant, $extLine, $signatory, $resolver, $sanitizer, $contract);
        }
    }

    /** The active, current version of a built-in template for the seeded language. */
    private function activeVersion(ContractTemplate $template, Language $language): ?ContractTemplateVersion
    {
        return $template->versions()
            ->where('status', 'active')
            ->where('is_current', true)
            ->where('language_id', $language->id)
            ->orderBy('id')
            ->first();
    }

    /**
     * Renders the version against the applicant and freezes the same snapshot a
     * generated contract carries: the parties, the closed entity payload and the
     * template version shown on the printed document.
     */
    private function issueContent(
        Contract $contract,
        ContractTemplateVersion $version,
        Applicant $applicant,
        ApplicantContract $line,
        string $signatory,
        PlaceholderResolver $resolver,
        ContentSanitizer $sanitizer,
        ?Contract $extensionOf = null,
    ): void {
        $rendered = $resolver->resolve($version->content, [
            'applicant' => $applicant,
            'built_in_line' => $line,
            'contract_number' => $contract->contract_number,
            'start_date' => $contract->start_date?->toDateString(),
            'status' => $contract->status,
            'company_signatory_name' => $contract->company_signatory_name,
        ], 'customer');

        $contract->forceFill([
            'content' => $sanitizer->clean($rendered['content']),
            'snapshot_data' => [
                'parties' => [
                    'our_company' => PlaceholderRegistry::ourCompanyValues($signatory),
                    'counterparty' => [
                        'name' => $applicant->name,
                        'email' => $applicant->email,
                        'phone' => $applicant->contact,
                        'address' => $applicant->address,
                    ],
                ],
                'entity' => [
                    'applicant_id' => $applicant->id,
                    'service' => $line->service,
                    'applicant' => $applicant->only(['name', 'father_name', 'father_phone', 'backup_phone', 'contact', 'email', 'address']),
                    'services' => [[
                        ...$line->only(['id', 'service', 'fee', 'discount', 'currency_id', 'currency_rate', 'application_fee', 'visa_fee', 'booking_fee', 'service_fee', 'remote_fee']),
                        'currency_code' => $line->currency?->code,
                    ]],
                    'extension_of' => $extensionOf?->contract_number,
                ],
                'template_version' => $version->only(['id', 'name', 'version_number', 'country_id', 'language_id']),
            ],
        ])->save();
    }
}
