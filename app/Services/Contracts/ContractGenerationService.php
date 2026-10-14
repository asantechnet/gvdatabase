<?php

namespace App\Services\Contracts;

use App\Http\Requests\StoreServiceContractRequest;
use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Employee;
use App\Services\ApplicantServiceDetails;
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
            $line = $contract->applicantContracts()->create([
                'service' => $service, 'applicant_id' => $applicant->id,
                'current_stage_id' => $newStage->id,
                'fee' => $data['fee'], 'discount' => $data['discount'],
                'currency_id' => $data['currency_id'], 'currency_rate' => $rate,
                'sold_by_employee_id' => $contract->sold_by_employee_id,
                ...Arr::only($data, ['application_fee', 'visa_fee', 'booking_fee', 'service_fee', 'remote_fee']),
                'notes' => $data['notes'] ?? null,
            ]);
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
                    'entity' => ['applicant_id' => $applicant->id, 'service' => $service, 'applicant' => $applicant->only(['name', 'father_name', 'father_phone', 'backup_phone', 'contact', 'email', 'address']), 'details' => $applicant->detail()?->toArray(), 'favorite_subject_ids' => $applicant->favoriteSubjects()->pluck('subjects.id')->all(), 'skill_ids' => $applicant->skills()->pluck('skills.id')->all(), 'teacher_ids' => $applicant->teachers()->pluck('teachers.id')->all(), 'services' => [[...$line->only(['id', 'service', 'fee', 'discount', 'currency_id', 'currency_rate', 'application_fee', 'visa_fee', 'booking_fee', 'service_fee', 'remote_fee']), 'currency_code' => $line->currency?->code]], 'extension_of' => $primary?->contract_number],
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
            if (! $primary) {
                $this->notifications->applicantAssigned($applicant, $request->user()->id);
            }
            $this->notifications->contractGenerated($contract, $request->user()->id);

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
