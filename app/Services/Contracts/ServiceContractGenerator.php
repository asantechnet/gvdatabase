<?php

namespace App\Services\Contracts;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Package;
use App\Models\Service;
use App\Services\ContractDocumentDesignService;
use App\Services\ApplicantDocumentReadiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generates a contract from a catalogue service.
 *
 * Every service is expected to have exactly one contract template. When a
 * service is assigned to a customer this class:
 *
 *   1. resolves (or auto-creates) the service's contract template,
 *   2. picks the active template version for the applicant's country/language,
 *   3. renders the placeholders into the contract document,
 *   4. creates the contract (marked with `source_service_id`) and its single
 *      `contract_service` line, carrying the editable per-customer fee.
 *
 * Repeat sales of the same service to the same customer are modelled as
 * extension contracts linked back to the live primary contract.
 */
class ServiceContractGenerator
{
    public function __construct(
        private readonly PlaceholderResolver $resolver,
        private readonly ContentSanitizer $sanitizer,
        private readonly ContractDocumentDesignService $documentDesigns,
        private readonly ApplicantDocumentReadiness $documents,
    ) {}

    /** The contract type every service-generated contract belongs to. */
    public const CONTRACT_TYPE_SLUG = 'customer';

    /**
     * The service's contract template, creating a draft one from the service
     * metadata when the catalogue entry has none yet.
     */
    public function templateFor(Service $service, ?int $createdBy = null): ContractTemplate
    {
        if ($template = $service->primaryContractTemplate()) {
            return $template;
        }

        $type = ContractType::query()->where('slug', self::CONTRACT_TYPE_SLUG)->first();
        abort_unless($type, 500, 'The customer contract type is missing.');

        return ContractTemplate::create([
            'name' => "{$service->type} Agreement",
            'description' => "Contract template for the {$service->type} service.",
            'is_active' => true,
            'contract_type_id' => $type->id,
            'service_id' => $service->id,
            'cover_text' => $service->type,
            'content' => $this->defaultContent(),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * The package's contract template, creating a draft one from the package
     * metadata when the catalogue entry has none yet. Mirrors templateFor().
     */
    public function templateForPackage(Package $package, ?int $createdBy = null): ContractTemplate
    {
        if ($template = $package->primaryContractTemplate()) {
            return $template;
        }

        $type = ContractType::query()->where('slug', self::CONTRACT_TYPE_SLUG)->first();
        abort_unless($type, 500, 'The customer contract type is missing.');

        return ContractTemplate::create([
            'name' => "{$package->name} Agreement",
            'description' => "Contract template for the {$package->name} package.",
            'is_active' => true,
            'contract_type_id' => $type->id,
            'package_id' => $package->id,
            'cover_text' => $package->name,
            'content' => $this->defaultContent(),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Create the single agreement for a package sale.
     *
     * Unlike createFor(), this mints ONE contract from the package's bound
     * template with one `contract_service` line per member service, so the
     * bundle is sold (and printed) as a unit — exactly like assigning a
     * service, but with many lines.
     *
     * @param  array<string, mixed>  $options  package_lines ([service_id =>
     *   ['fee', 'currency_id']]), customer_package_id, status, start_date,
     *   sold_by_employee_id, marketer_id, country_id, education_level_id,
     *   language_id, notes, template, source, user_id
     */
    public function createForPackage(Package $package, Applicant $applicant, array $options = []): Contract
    {
        return DB::transaction(function () use ($package, $applicant, $options): Contract {
            $package->loadMissing('services.currency');
            if (! $package->is_active || $package->services->isEmpty()) {
                throw ValidationException::withMessages(['package_id' => ['Choose an active package that contains at least one service.']]);
            }

            $this->documents->require($applicant);
            $template = $options['template'] ?? $package->primaryContractTemplate();
            if (! $template) {
                throw ValidationException::withMessages(['package_id' => ['This package has no contract template yet. Create one before selling it as a single agreement.']]);
            }
            $type = $template->contractType ?? ContractType::query()->where('slug', self::CONTRACT_TYPE_SLUG)->first();

            $countryId = $options['country_id'] ?? $applicant->country_id;
            $languageId = $options['language_id'] ?? $applicant->language_id;
            $version = $this->resolveVersion($template, $countryId ? (int) $countryId : null, $languageId ? (int) $languageId : null);
            $content = $version?->content ?? ($template->content ?? '');

            $startDate = $options['start_date'] ?? now()->toDateString();
            $status = $this->normalizeStatus($options['status'] ?? 'active');
            $signatory = $options['company_signatory_name'] ?? null;

            $lines = $this->packageLines($package, $options['package_lines'] ?? []);

            $rendered = $this->resolver->resolve(
                (string) $content,
                [
                    'applicant' => $applicant,
                    'customer' => $applicant,
                    'services' => $package->services,
                    'contract_number' => null,
                    'start_date' => $startDate,
                    'status' => $status,
                    'company_signatory_name' => $signatory,
                ],
                self::CONTRACT_TYPE_SLUG,
            );

            $counterparty = $this->counterpartyFor($applicant);
            $design = $this->documentDesigns->resolve($type?->id, $package->services->map(fn ($service) => (int) $service->id)->all());

            $contract = Contract::create([
                'template_id' => $template->id,
                'contract_template_version_id' => $version?->id,
                'contract_type_id' => $type?->id,
                'source_service_id' => null,
                'contract_role' => Contract::ROLE_PRIMARY,
                'parent_contract_id' => null,
                'applicant_id' => $applicant->id,
                'sold_by_employee_id' => $options['sold_by_employee_id'] ?? null,
                'marketer_id' => $options['marketer_id'] ?? ($options['sold_by_employee_id'] ?? null),
                'contract_country_id' => $countryId,
                'contract_language_id' => $languageId,
                'name' => $package->name,
                'type' => $type?->name,
                'cover_text' => $template->cover_text ?? $package->name,
                'content' => $this->sanitizer->clean($rendered['content']),
                'status' => $status,
                'start_date' => $startDate,
                'company_signatory_name' => $signatory,
                'is_custom' => false,
                'counterparty' => $counterparty['counterparty'],
                'counterparty_company' => $counterparty['counterparty_company'],
                'counterparty_email' => $counterparty['counterparty_email'],
                'counterparty_contact' => $counterparty['counterparty_contact'],
                'counterparty_address' => $counterparty['counterparty_address'],
                'snapshot_data' => [
                    'parties' => [
                        'our_company' => PlaceholderRegistry::ourCompanyValues($signatory),
                        'counterparty' => $counterparty,
                    ],
                    'entity' => [
                        'applicant_id' => $applicant->id,
                        'package_id' => $package->id,
                        'customer_package_id' => $options['customer_package_id'] ?? null,
                        'services' => collect($lines)->map(fn ($line, $serviceId) => [
                            'service_id' => (int) $serviceId,
                            'fee' => $line['fee'],
                            'currency_id' => $line['currency_id'],
                        ])->values()->all(),
                        'source' => $options['source'] ?? 'package_issuance',
                        ...($options['snapshot_extra'] ?? []),
                    ],
                    'template_version' => $this->versionSnapshot($version),
                    'document_design' => $this->documentDesigns->snapshot($design),
                ],
            ]);

            foreach ($package->services as $service) {
                $line = $lines[$service->id];
                $contract->contractServices()->create([
                    'service_id' => $service->id,
                    'applicant_id' => $applicant->id,
                    'sold_by_employee_id' => $options['sold_by_employee_id'] ?? null,
                    'marketer_id' => $options['marketer_id'] ?? ($options['sold_by_employee_id'] ?? null),
                    'customer_package_id' => $options['customer_package_id'] ?? null,
                    'currency_id' => $line['currency_id'],
                    'fee' => $line['fee'],
                    'country_id' => $countryId,
                    'education_level_id' => $options['education_level_id'] ?? $applicant->education_level_id,
                    'language_id' => $languageId,
                    'notes' => $options['notes'] ?? null,
                ]);
            }

            return $contract->load(['contractServices.service.currency', 'contractServices.currency']);
        });
    }

    /**
     * Per-member-service fee split for a package sale, keyed by service id.
     *
     * Explicit overrides win; otherwise each member keeps its allocated share
     * of the package price (or its own fee when the package has no price).
     *
     * @param  array<int, array{fee: mixed, currency_id: mixed}>  $overrides
     * @return array<int, array{fee: mixed, currency_id: mixed}>
     */
    private function packageLines(Package $package, array $overrides = []): array
    {
        $lines = [];
        foreach ($package->services as $service) {
            if (array_key_exists($service->id, $overrides)) {
                $lines[$service->id] = $overrides[$service->id];
            } else {
                $lines[$service->id] = [
                    'fee' => $service->pivot->allocated_fee ?? $service->fee,
                    'currency_id' => $service->currency_id,
                ];
            }
        }

        return $lines;
    }

    /**
     * Create the contract for a service assignment.
     *
     * @param  array<string, mixed>  $options  fee, currency_id, status, start_date,
     *   sold_by_employee_id, marketer_id, country_id, education_level_id,
     *   language_id, notes, customer_package_id, template, extend, source, user_id
     */
    public function createFor(Service $service, Applicant $applicant, array $options = []): Contract
    {
        return DB::transaction(function () use ($service, $applicant, $options): Contract {
            $this->documents->require($applicant);
            $template = $options['template'] ?? $this->templateFor($service, $options['user_id'] ?? null);
            $type = $template->contractType ?? ContractType::query()->where('slug', self::CONTRACT_TYPE_SLUG)->first();

            $primary = $this->existingPrimaryFor((int) $applicant->id, (int) $service->id);
            // A repeat sale becomes an extension by default. Callers may pass
            // `extend => false` to refuse it instead.
            if ($primary && ($options['extend'] ?? true) === false) {
                throw ValidationException::withMessages([
                    'service_id' => ['This customer already has a live contract for this service. Use an extension instead.'],
                ]);
            }

            $countryId = $options['country_id'] ?? $applicant->country_id;
            $languageId = $options['language_id'] ?? $applicant->language_id;
            $version = $this->resolveVersion($template, $countryId ? (int) $countryId : null, $languageId ? (int) $languageId : null);
            $content = $version?->content ?? ($template->content ?? '');

            $startDate = $options['start_date'] ?? now()->toDateString();
            $status = $this->normalizeStatus($options['status'] ?? 'active');
            $signatory = $options['company_signatory_name'] ?? null;

            $rendered = $this->resolver->resolve(
                (string) $content,
                [
                    'applicant' => $applicant,
                    'customer' => $applicant,
                    'services' => collect([$service]),
                    'contract_number' => null,
                    'start_date' => $startDate,
                    'status' => $status,
                    'company_signatory_name' => $signatory,
                ],
                self::CONTRACT_TYPE_SLUG,
            );

            $counterparty = $this->counterpartyFor($applicant);
            $design = $this->documentDesigns->resolve($type?->id, [(int) $service->id]);

            $contract = Contract::create([
                'template_id' => $template->id,
                'contract_template_version_id' => $version?->id,
                'contract_type_id' => $type?->id,
                'source_service_id' => $service->id,
                'contract_role' => $primary ? Contract::ROLE_EXTENSION : Contract::ROLE_PRIMARY,
                'parent_contract_id' => $primary?->id,
                'applicant_id' => $applicant->id,
                'sold_by_employee_id' => $options['sold_by_employee_id'] ?? null,
                'marketer_id' => $options['marketer_id'] ?? ($options['sold_by_employee_id'] ?? null),
                'contract_country_id' => $countryId,
                'contract_language_id' => $languageId,
                'name' => $service->type,
                'type' => $type?->name,
                'cover_text' => $template->cover_text ?? $service->type,
                'content' => $this->sanitizer->clean($rendered['content']),
                'status' => $status,
                'start_date' => $startDate,
                'company_signatory_name' => $signatory,
                'is_custom' => false,
                'counterparty' => $counterparty['counterparty'],
                'counterparty_company' => $counterparty['counterparty_company'],
                'counterparty_email' => $counterparty['counterparty_email'],
                'counterparty_contact' => $counterparty['counterparty_contact'],
                'counterparty_address' => $counterparty['counterparty_address'],
                'snapshot_data' => [
                    'parties' => [
                        'our_company' => PlaceholderRegistry::ourCompanyValues($signatory),
                        'counterparty' => $counterparty,
                    ],
                    'entity' => [
                        'applicant_id' => $applicant->id,
                        'services' => [[
                            'service_id' => $service->id,
                            'fee' => $options['fee'] ?? $service->fee,
                            'currency_id' => $options['currency_id'] ?? $service->currency_id,
                        ]],
                        'source' => $options['source'] ?? 'service_assignment',
                        'extension_of' => $primary?->contract_number,
                        ...($options['snapshot_extra'] ?? []),
                    ],
                    'template_version' => $this->versionSnapshot($version),
                    'document_design' => $this->documentDesigns->snapshot($design),
                ],
            ]);

            $contract->contractServices()->create([
                'service_id' => $service->id,
                'applicant_id' => $applicant->id,
                'sold_by_employee_id' => $options['sold_by_employee_id'] ?? null,
                'marketer_id' => $options['marketer_id'] ?? ($options['sold_by_employee_id'] ?? null),
                'customer_package_id' => $options['customer_package_id'] ?? null,
                'currency_id' => $options['currency_id'] ?? $service->currency_id,
                'fee' => $options['fee'] ?? $service->fee,
                'country_id' => $countryId,
                'education_level_id' => $options['education_level_id'] ?? $applicant->education_level_id,
                'language_id' => $languageId,
                'notes' => $options['notes'] ?? null,
            ]);

            return $contract->load(['contractServices.service.currency', 'contractServices.currency']);
        });
    }

    /** The live primary contract for a customer + service pair, if any. */
    public function existingPrimaryFor(int $applicantId, int $serviceId): ?Contract
    {
        return Contract::query()
            ->where('applicant_id', $applicantId)
            ->where('source_service_id', $serviceId)
            ->where('contract_role', Contract::ROLE_PRIMARY)
            ->whereNotIn('status', ['cancelled', 'terminated'])
            ->where('is_cancelled', false)
            ->latest('id')
            ->first();
    }

    /** A version that can render for the applicant's country/language. */
    private function resolveVersion(ContractTemplate $template, ?int $countryId, ?int $languageId): ?\App\Models\ContractTemplateVersion
    {
        $versions = $template->versions();
        if (! (clone $versions)->exists()) {
            return null;
        }

        $scoped = (clone $versions)
            ->where('is_current', true)
            ->where('status', 'active')
            ->when($countryId, fn ($query) => $query->where('country_id', $countryId))
            ->when($languageId, fn ($query) => $query->where('language_id', $languageId))
            ->latest('version_number')
            ->first();

        return $scoped
            ?? (clone $versions)->where('status', 'active')->latest('version_number')->first()
            ?? (clone $versions)->latest('version_number')->first();
    }

    /** @return array<string, ?string> */
    private function counterpartyFor(Applicant $applicant): array
    {
        return [
            'counterparty' => $applicant->name,
            'counterparty_company' => null,
            'counterparty_email' => $applicant->email,
            'counterparty_contact' => $applicant->contact,
            'counterparty_address' => $applicant->address,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, Contract::STATUSES, true) ? $status : 'active';
    }

    /** @return array<string, mixed>|null */
    private function versionSnapshot(?\App\Models\ContractTemplateVersion $version): ?array
    {
        if (! $version) {
            return null;
        }
        $version->loadMissing(['country', 'language', 'template']);

        return [
            'id' => $version->id,
            'name' => $version->name,
            'version_number' => $version->version_number,
            'template_id' => $version->contract_template_id,
            'template_name' => $version->template?->name,
            'country_id' => $version->country_id,
            'country_name' => $version->country?->name,
            'language_id' => $version->language_id,
            'language_name' => $version->language?->name,
            'language_code' => $version->language?->code,
        ];
    }

    /** Default customer-agreement body used for auto-created templates. */
    private function defaultContent(): string
    {
        return '<h1>Service Agreement</h1>'
            .'<p>This agreement is made between <strong>{{our_company.name}}</strong> and '
            .'<strong>{{customer.name}}</strong> on <strong>{{contract.start_date}}</strong>.</p>'
            .'<h2>1. Service</h2>'
            .'<p>The provider will deliver <strong>{{service.name}}</strong> '
            .'({{service.description}}).</p>'
            .'<h2>2. Fees and payment</h2>'
            .'<p>The agreed fee for this service is <strong>{{service.fee}}</strong>.</p>'
            .'<h2>3. Terms</h2>'
            .'<p>Both parties agree to the obligations and terms recorded in this contract. '
            .'This document is generated automatically from the service contract template and can be reviewed '
            .'before it is finalised.</p>';
    }
}
