<?php

namespace App\Services\Contracts;

use App\Http\Requests\StoreServiceContractRequest;
use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Income;
use App\Services\ApplicantPortalAccountService;
use App\Services\ApplicantServiceDetails;
use App\Services\CurrencyConversionService;
use App\Services\ContractDocumentDesignService;
use App\Services\ContractTaskService;
use App\Services\CurrencyRateService;
use App\Services\EducationalWorkflowNotificationService;
use App\Support\BuiltInService;
use App\Support\ContractAccess;
use App\Support\EmployeeScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ContractGenerationService
{
    public function __construct(
        private readonly PlaceholderResolver $resolver,
        private readonly ContentSanitizer $sanitizer,
        private readonly ApplicantServiceDetails $details,
        private readonly CurrencyRateService $rates,
        private readonly ContractTaskService $tasks,
        private readonly ContractDocumentDesignService $designs,
        private readonly EducationalWorkflowNotificationService $notifications,
        private readonly CurrencyConversionService $currencies,
        private readonly ApplicantPortalAccountService $portalAccounts,
    ) {}

    public function create(string $service, StoreServiceContractRequest $request): Contract
    {
        abort_unless(BuiltInService::isValid($service), 404);
        $data = $request->validated();

        return DB::transaction(function () use ($service, $request, $data): Contract {
            $primary = null;
            if (! empty($data['parent_contract_id'])) {
                $parent = Contract::query()->lockForUpdate()->findOrFail($data['parent_contract_id']);
                abort_unless(ContractAccess::canManage($request->user(), $parent), 403, 'auth.forbidden');
                abort_unless($parent->service === $service && $parent->contract_role === Contract::ROLE_PRIMARY
                    && ! $parent->is_cancelled && in_array($parent->status, ['active', 'completed'], true), 422, 'business.contract_extension_invalid');
                $primary = $parent;
                $applicant = Applicant::query()->lockForUpdate()->findOrFail($parent->applicant_id);
                abort_unless($applicant->service === $service, 422, 'business.contract_extension_invalid');
                // An academy extension keeps its applicant editable, so the
                // submitted identity and programme fields update that record
                // before the new service line is created.
                if ($service === BuiltInService::ACADEMY) {
                    $applicant->update(Arr::only($data, ['name', 'father_name', 'father_phone', 'backup_phone', 'contact', 'email', 'address', 'tax_number', 'admission_employee_id', 'responsible_employee_id', 'notes']));
                    $this->details->sync($applicant, [...$data, 'service' => $service]);
                    $applicant->refresh();
                }
            } else {
                $applicant = $this->createApplicant($service, $request, $data);
            }

            $countryId = in_array($service, [BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE], true) ? $applicant->detail()?->country_id : null;
            // Test-offer terms vary by the test the applicant is preparing for,
            // not by a destination country.
            $testId = $service === BuiltInService::TEST_OFFER ? $applicant->testDetail?->test_id : null;
            $template = ContractTemplate::query()->where('service', $service)->lockForUpdate()->first();
            abort_unless($template, 422, 'business.builtin_contract_template_not_ready');
            $type = $template->contractType;
            abort_unless($type?->is_active && $type->slug === 'customer', 422, 'business.builtin_contract_template_not_ready');
            // The operator may intentionally issue any active historical version,
            // but never one belonging to another template, language or scope.
            $version = $template->versions()->whereKey($data['contract_template_version_id'])
                ->where('status', 'active')
                ->where('language_id', $data['language_id'])
                ->where('country_id', $countryId)
                ->where('test_id', $testId)->lockForUpdate()->first();
            abort_unless($version && trim($version->content) !== '', 422, 'business.builtin_contract_template_not_ready');

            $rate = $this->rates->remember((int) $data['currency_id'], $data['currency_rate']);
            abort_unless($rate, 422, 'business.invalid_currency_rate');
            $counterparty = [
                'name' => $applicant->name, 'email' => $applicant->email,
                'phone' => $applicant->contact, 'address' => $applicant->address,
            ];
            $signatory = $request->user()->employee?->name ?: $request->user()->name;
            if ($request->user()->isSuperAdmin() && ! empty($data['company_signatory_name'])) {
                $signatory = $data['company_signatory_name'];
            }
            $contract = Contract::create([
                'service' => $service, 'template_id' => $template->id,
                'contract_template_version_id' => $version->id, 'contract_type_id' => $type->id,
                'contract_role' => $primary ? Contract::ROLE_EXTENSION : Contract::ROLE_PRIMARY,
                'parent_contract_id' => $primary?->id, 'applicant_id' => $applicant->id,
                'sold_by_employee_id' => Employee::query()->active()->where('user_id', $request->user()->id)->value('id'),
                'contract_country_id' => $countryId, 'contract_language_id' => $data['language_id'],
                'name' => $template->name, 'type' => $type->name, 'cover_text' => $template->cover_text,
                'content' => '', 'status' => 'active', 'start_date' => now()->toDateString(),
                'company_signatory_name' => $signatory,
                'is_custom' => false, 'is_cancelled' => false,
                'counterparty' => $applicant->name, 'counterparty_email' => $applicant->email,
                'counterparty_contact' => $applicant->contact, 'counterparty_address' => $applicant->address,
            ]);
            $newStage = $this->newStage($service, $countryId, $applicant->detail()?->university_id);
            $lineFees = Arr::only($data, ['application_fee', 'application_fee_min', 'application_fee_max', 'visa_fee', 'booking_fee', 'service_fee', 'remote_fee']);
            // A private application fee is a range; the legacy single column
            // keeps the range maximum so older readers see a price.
            if ($service === BuiltInService::PRIVATE) {
                $lineFees['application_fee'] = $data['application_fee_max'];
            }
            $line = $contract->applicantContracts()->create([
                'service' => $service, 'applicant_id' => $applicant->id,
                'current_stage_id' => $newStage->id,
                'fee' => $data['fee'], 'discount' => $data['discount'],
                'currency_id' => $data['currency_id'], 'currency_rate' => $rate,
                'sold_by_employee_id' => $contract->sold_by_employee_id,
                ...$lineFees,
                'notes' => $data['notes'] ?? null,
            ]);
            // An academy fee paid at signing is a real receipt, not a stored
            // informational amount: the line's paid state follows the income.
            if ($service === BuiltInService::ACADEMY && (float) ($data['paid_fee'] ?? 0) > 0) {
                abort_unless($request->user()->hasPermission('create_incomes'), 403, 'auth.forbidden');
                $currency = Currency::query()->findOrFail($data['currency_id']);
                Income::create([
                    ...$this->currencies->snapshot($currency),
                    'applicant_id' => $applicant->id,
                    'applicant_contract_id' => $line->id,
                    'type' => 'Service',
                    'description' => 'Academy fee — '.$contract->contract_number,
                    'amount' => $data['paid_fee'],
                    'quantity' => 1,
                    'currency_id' => $currency->id,
                    'rate' => $rate,
                    'date' => now()->toDateString(),
                    'recorded_by' => $request->user()->id,
                ]);
            }
            $rendered = $this->resolver->resolve($version->content, [
                'applicant' => $applicant, 'built_in_line' => $line,
                'contract_number' => $contract->contract_number, 'start_date' => $contract->start_date->toDateString(),
                'status' => 'active', 'company_signatory_name' => $contract->company_signatory_name,
            ], 'customer');
            abort_unless($rendered['unresolved'] === [], 422, 'business.contract_template_unresolved_placeholders');
            $contract->update([
                'content' => $this->sanitizer->clean($rendered['content']),
                'snapshot_data' => [
                    'parties' => ['our_company' => PlaceholderRegistry::ourCompanyValues($contract->company_signatory_name), 'counterparty' => $counterparty],
                    'entity' => ['applicant_id' => $applicant->id, 'service' => $service, 'applicant' => $applicant->only(['name', 'father_name', 'father_phone', 'backup_phone', 'contact', 'email', 'address']), 'details' => $applicant->detail()?->toArray(), 'favorite_subject_ids' => $applicant->favoriteSubjects()->pluck('subjects.id')->all(), 'skill_ids' => $applicant->skills()->pluck('skills.id')->all(), 'teacher_ids' => $applicant->teachers()->pluck('teachers.id')->all(), 'services' => [[...$line->only(['id', 'service', 'fee', 'discount', 'currency_id', 'currency_rate', 'application_fee', 'application_fee_min', 'application_fee_max', 'visa_fee', 'booking_fee', 'service_fee', 'remote_fee']), 'currency_code' => $line->currency?->code]], 'extension_of' => $primary?->contract_number],
                    'template_version' => $version->only(['id', 'name', 'version_number', 'country_id', 'language_id']),
                    'document_design' => $this->designs->snapshot($this->designs->resolve($type->id, [$service])),
                ],
            ]);
            // Each service line starts its own workflow at the service's `new` stage.
            ApplicantStageHistory::create([
                'applicant_id' => $applicant->id, 'applicant_contract_id' => $line->id,
                'new_stage_id' => $newStage->id, 'changed_by' => $request->user()->id,
                'changed_at' => now(), 'note' => 'Contract created',
            ]);
            $this->tasks->ensureForApplicantContract($line, $request->user());
            // The form pre-generated these credentials and showed them to the
            // operator, so the account is created with exactly that pair.
            if (! $primary && ($data['create_portal_user'] ?? false)) {
                abort_unless($request->user()->hasPermission('invite_applicant_portal'), 403, 'auth.forbidden');
                $this->portalAccounts->create($applicant, $applicant->name, $data['portal_user_email'], $data['portal_user_password'], [
                    'phone' => $applicant->contact, 'address' => $applicant->address,
                ]);
            }
            if (! $primary) {
                $this->notifications->applicantAssigned($applicant, $request->user()->id);
            }
            $this->notifications->contractGenerated($contract, $request->user()->id);

            return $contract->load(['template.contractType', 'contractType', 'applicant', 'applicantContracts.currency']);
        });
    }

    /** Update the live case and service-line terms without altering the issued document snapshot. */
    public function update(string $service, int $id, StoreServiceContractRequest $request): Contract
    {
        abort_unless(BuiltInService::isValid($service), 404);
        $data = $request->validated();

        return DB::transaction(function () use ($service, $id, $request, $data): Contract {
            $contract = Contract::query()->lockForUpdate()->findOrFail($id);
            abort_unless($contract->service === $service && ! $contract->is_cancelled, 422, 'business.builtin_contract_creation_required');
            abort_unless(ContractAccess::canManage($request->user(), $contract), 403, 'auth.forbidden');

            $line = ApplicantContract::query()->where('contract_id', $contract->id)->lockForUpdate()->sole();
            abort_unless($line->service === $service && (int) $line->applicant_id === (int) $contract->applicant_id, 422, 'business.builtin_contract_creation_required');
            abort_if($line->incomes()->exists() && (int) $line->currency_id !== (int) $data['currency_id'], 422, 'business.contract_paid_line_immutable');

            $applicant = Applicant::query()->lockForUpdate()->findOrFail($contract->applicant_id);
            $this->authorizeApplicantAssignment($request, $data, $applicant);
            if (array_key_exists('admission_employee_id', $data) && ! $request->user()->hasPermission('assign_admission_employee')) {
                abort(403, 'auth.forbidden');
            }
            $applicant->update(Arr::only($data, ['name', 'father_name', 'father_phone', 'backup_phone', 'contact', 'email', 'address', 'tax_number', 'admission_employee_id', 'responsible_employee_id', 'notes']));
            $this->details->sync($applicant, [...$data, 'service' => $service]);
            $applicant->refresh();

            $rate = $this->rates->remember((int) $data['currency_id'], $data['currency_rate']);
            abort_unless($rate, 422, 'business.invalid_currency_rate');
            $lineFees = Arr::only($data, ['application_fee', 'application_fee_min', 'application_fee_max', 'visa_fee', 'booking_fee', 'service_fee', 'remote_fee']);
            if ($service === BuiltInService::PRIVATE) {
                $lineFees['application_fee'] = $data['application_fee_max'];
            }
            $line->update([
                'fee' => $data['fee'], 'discount' => $data['discount'],
                'currency_id' => $data['currency_id'], 'currency_rate' => $rate,
                ...$lineFees,
                'notes' => $data['notes'] ?? null,
            ]);

            $countryId = in_array($service, [BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE], true)
                ? $applicant->detail()?->country_id : null;
            $contract->update([
                'contract_country_id' => $countryId,
                'counterparty' => $applicant->name,
                'counterparty_email' => $applicant->email,
                'counterparty_contact' => $applicant->contact,
                'counterparty_address' => $applicant->address,
            ]);

            return $contract->load(['template.contractType', 'contractType', 'applicant', 'applicantContracts.currency']);
        });
    }

    private function createApplicant(string $service, StoreServiceContractRequest $request, array $data): Applicant
    {
        if (array_key_exists('admission_employee_id', $data) && ! $request->user()->hasPermission('assign_admission_employee')) {
            abort(403, 'auth.forbidden');
        }
        if (! $request->user()->hasPermission('assign_applicant_employee')) {
            $ownEmployee = Employee::query()->active()->where('user_id', $request->user()->id)->value('id');
            abort_unless($ownEmployee, 422, 'business.employee_profile_missing');
            $selected = $data['responsible_employee_id'] ?? $ownEmployee;
            abort_unless(app(EmployeeScope::class)->canManageOwnedBy($request->user(), (int) $selected), 403, 'auth.forbidden');
            $data['responsible_employee_id'] = $selected;
        }
        $stage = $this->newStage($service, $data['country_id'] ?? null, null);
        $applicant = Applicant::create([
            ...Arr::only($data, ['name', 'father_name', 'father_phone', 'backup_phone', 'contact', 'email', 'address', 'tax_number', 'admission_employee_id', 'responsible_employee_id', 'notes']),
            'service' => $service, 'current_stage_id' => $stage->id, 'status' => 'active',
        ]);
        $this->details->sync($applicant, [...$data, 'service' => $service]);
        $applicant->{$applicant->detailRelationName()}()->firstOrCreate([]);
        ApplicantStageHistory::create(['applicant_id' => $applicant->id, 'new_stage_id' => $stage->id, 'changed_by' => $request->user()->id, 'changed_at' => now(), 'note' => 'Contract created']);

        return $applicant->fresh();
    }

    /** @param array<string, mixed> $data */
    private function authorizeApplicantAssignment(StoreServiceContractRequest $request, array $data, Applicant $applicant): void
    {
        if ($request->user()->hasPermission('assign_applicant_employee')) {
            return;
        }

        $ownEmployee = Employee::query()->active()->where('user_id', $request->user()->id)->value('id');
        abort_unless($ownEmployee, 422, 'business.employee_profile_missing');
        $selected = $data['responsible_employee_id'] ?? $applicant->responsible_employee_id ?? $ownEmployee;
        abort_unless(app(EmployeeScope::class)->canManageOwnedBy($request->user(), (int) $selected), 403, 'auth.forbidden');
        abort_unless((int) $selected === (int) $ownEmployee, 403, 'auth.forbidden');
    }

    /** The `new` stage of one service, preferring the most specific context. */
    private function newStage(string $service, ?int $countryId, ?int $universityId): ApplicantStage
    {
        return ApplicantStage::query()->where('key', 'new')->where('is_active', true)
            ->where('service', $service)
            ->where(function ($query) use ($countryId) {
                $query->whereNull('country_id')->orWhere('country_id', $countryId);
            })->where(function ($query) use ($universityId) {
                $query->whereNull('university_id')->orWhere('university_id', $universityId);
            })->orderByRaw('university_id IS NULL')->orderByRaw('country_id IS NULL')->firstOrFail();
    }
}
