<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Requests\StoreServiceContractRequest;
use App\Http\Resources\ContractResource;
use App\Http\Resources\ContractTemplateResource;
use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Language;
use App\Services\AcademySlipService;
use App\Services\ContractDocumentDesignService;
use App\Services\Contracts\ContentSanitizer;
use App\Services\Contracts\ContractGenerationService;
use App\Services\Contracts\PlaceholderRegistry;
use App\Services\Contracts\PlaceholderResolver;
use App\Support\AgencyStaffAssignment;
use App\Support\BuiltInService;
use App\Support\ContractAccess;
use App\Support\EmployeeScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    use PaginatesLists;
    private const RELATIONS = [
        'template.contractType', 'templateVersion.country', 'templateVersion.language',
        'contractCountry', 'contractLanguage',        'contractType', 'parentContract:id,contract_number', 'applicantContracts.currency',
        'employee.position', 'employee.office', 'employee.user',
        'applicant.scholarshipDetail.educationLevel', 'applicant.scholarshipDetail.semester',
        'applicant.scholarshipDetail.country', 'applicant.scholarshipDetail.scholarship',
        'applicant.privateDetail.country', 'applicant.privateDetail.university', 'applicant.privateDetail.educationLevel', 'applicant.privateDetail.semester', 'applicant.privateDetail.contractType',
        'applicant.testDetail.test', 'applicant.academyDetail.program', 'applicant.academyDetail.time', 'applicant.academyDetail.duration', 'applicant.academyDetail.academyClass',
        'applicant.favoriteSubjects', 'applicant.skills', 'applicant.teachers',
    ];

    public function __construct(
        private readonly PlaceholderResolver $resolver,
        private readonly ContentSanitizer $sanitizer,
        private readonly ContractDocumentDesignService $documentDesigns,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audience' => ['nullable', Rule::in(['customers', 'employees', 'other'])],
            'service' => ['nullable', Rule::in(BuiltInService::ALL)],
            'applicant_id' => ['nullable', 'integer', Rule::exists('applicants', 'id')->whereNull('deleted_at')],
            'type_slug' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Contract::STATUSES)],
            'search' => ['nullable', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', $request->filled('date_from') ? 'after_or_equal:date_from' : 'nullable'],
            'contract_template_version_id' => ['nullable', 'integer', Rule::exists('contract_template_versions', 'id')],
            'only_cancelled' => ['nullable', 'boolean'],
            'expiring' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['contract_number', 'counterparty', 'start_date', 'status', 'created_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'service_id' => ['prohibited'], 'service_ids' => ['prohibited'],
            'without_service' => ['prohibited'],
        ]);
        $query = Contract::with(self::RELATIONS)
            ->whereHas('contractType', fn ($type) => $type->where('is_active', true));
        if (! empty($data['service'])) {
            $query->where('service', $data['service']);
        }
        if (! empty($data['applicant_id'])) {
            $query->where('applicant_id', $data['applicant_id']);
        }
        if (! empty($data['type_slug'])) {
            $query->whereHas('contractType', fn ($type) => $type->where('slug', $data['type_slug']));
        } elseif (($data['audience'] ?? null) === 'employees') {
            $query->whereHas('contractType', fn ($type) => $type->where('slug', 'employment'));
        } elseif (($data['audience'] ?? null) === 'customers') {
            $query->whereIn('service', BuiltInService::ALL);
        } elseif (($data['audience'] ?? null) === 'other') {
            $query->whereHas('contractType', fn ($type) => $type
                ->where('slug', 'other')->orWhere('is_system', false));
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['contract_template_version_id'])) {
            $query->where('contract_template_version_id', $data['contract_template_version_id']);
        }
        // Same definition as the dashboard's contracts-requiring-attention tile:
        // an active, non-cancelled contract whose end date is on or before 30
        // days from today (already-expired active contracts included).
        if ($request->boolean('expiring')) {
            $query->where('is_cancelled', false)
                ->where('status', 'active')
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<=', now()->addDays(30)->toDateString());
        }
        if (! empty($data['counterparty'])) {
            $query->where('counterparty', $data['counterparty']);
        }
        if (! empty($data['search'])) {
            $search = $data['search'];
            $query->where(fn ($query) => $query
                ->where('contract_number', 'like', "%{$search}%")
                ->orWhere('counterparty', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }
        if ($request->boolean('only_cancelled')) {
            $query->where(fn ($query) => $query->where('is_cancelled', true)->orWhere('status', 'cancelled'));
        }
        $query->when(! empty($data['date_from']), fn ($q) => $q->whereDate('start_date', '>=', $data['date_from']))
            ->when(! empty($data['date_to']), fn ($q) => $q->whereDate('start_date', '<=', $data['date_to']));
        ContractAccess::scopeVisibleTo($query, $request->user());
        // The registers send their own `start_date` ascending sort; a bare API
        // call keeps the long-standing newest-agreements-first order. Only
        // validated column names and directions reach this clause.
        $sortBy = $data['sort_by'] ?? 'created_at';
        $sortDirection = $data['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection)->orderBy('id', $sortDirection);

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true] + $this->paginatedPayload($request, $query, fn ($rows) => ContractResource::collection($rows)->resolve()));
        }

        return response()->json(['success' => true, 'data' => ContractResource::collection($query->get())]);
    }

    /**
     * The counterparties already on record, for the list filter. The list is
     * small and independent of paging, so it stays its own request.
     */
    public function counterparties(Request $request): JsonResponse
    {
        $query = Contract::query()
            ->whereNotNull('counterparty')
            ->where('counterparty', '!=', '');
        ContractAccess::scopeVisibleTo($query, $request->user());

        return response()->json([
            'success' => true,
            'data' => $query->distinct()->orderBy('counterparty')->pluck('counterparty')->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);
        $contract = DB::transaction(function () use ($data, $request): Contract {
            $template = $this->genericTemplate($data['template_id']);
            abort_unless(ContractAccess::canAccessTemplate($request->user(), $template), 403, 'auth.forbidden');
            // Employment and other agreements extend the same way built-in
            // service contracts do: a new record linked to a live primary
            // parent. Extensions are always issued as drafts so the terms can
            // be reviewed before the agreement becomes active.
            $parent = $this->extensionParent($request, $template, $data['parent_contract_id'] ?? null);
            $contract = Contract::create([
                'template_id' => $template->id, 'contract_type_id' => $template->contract_type_id,
                'name' => $template->name, 'content' => '', 'counterparty' => '',
                'status' => $parent ? 'draft' : ($data['status'] ?? 'active'),
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'employee_id' => $data['employee_id'] ?? null, 'applicant_id' => $data['applicant_id'] ?? null,
                'contract_role' => $parent ? Contract::ROLE_EXTENSION : Contract::ROLE_PRIMARY,
                'parent_contract_id' => $parent?->id,
                'is_cancelled' => false,
            ]);
            abort_unless(ContractAccess::canManage($request->user(), $contract), 403, 'auth.forbidden');
            $attributes = $this->documentAttributes($contract, $data);
            if ($parent) {
                // Keep the origin of the extension in the immutable snapshot so
                // the printed document can always name the agreement it extends.
                $attributes['snapshot_data'] = [...($attributes['snapshot_data'] ?? []), 'extension_of' => $parent->contract_number];
            }
            $contract->update($attributes);
            abort_unless(ContractAccess::canManage($request->user(), $contract->fresh()), 403, 'auth.forbidden');

            return $contract;
        });

        return $this->contractResponse($contract, 201);
    }

    /**
     * Resolve the agreement an extension is issued from.
     *
     * Only a live primary contract of the same contract type can be extended,
     * so a cancelled agreement, a finished one, or another extension is never
     * extended in place.
     */
    private function extensionParent(Request $request, ContractTemplate $template, ?int $parentId): ?Contract
    {
        if (! $parentId) {
            return null;
        }

        $parent = Contract::query()->lockForUpdate()->findOrFail($parentId);
        abort_unless(ContractAccess::canManage($request->user(), $parent), 403, 'auth.forbidden');
        abort_unless(
            $parent->contract_role === Contract::ROLE_PRIMARY
            && ! $parent->is_cancelled
            && in_array($parent->status, ['active', 'completed'], true)
            && (int) $parent->contract_type_id === (int) $template->contract_type_id,
            422,
            'business.contract_extension_invalid',
        );

        return $parent;
    }

    public function storeService(StoreServiceContractRequest $request, string $service, ContractGenerationService $generator): JsonResponse
    {
        return $this->contractResponse($generator->create($service, $request), 201);
    }

    public function updateService(StoreServiceContractRequest $request, string $service, int $id, ContractGenerationService $generator): JsonResponse
    {
        return $this->contractResponse($generator->update($service, $id, $request));
    }

    public function serviceOptions(Request $request, string $service): JsonResponse
    {
        abort_unless(BuiltInService::isValid($service), 404);
        abort_unless(ContractAccess::canAccessService($request->user(), $service), 403, 'auth.forbidden');
        $employees = Employee::query()->active();
        if (! $request->user()->hasPermission('assign_applicant_employee')) {
            $scope = app(EmployeeScope::class);
            $ids = array_filter($scope->visibleEmployeeIds($request->user()), fn ($id) => $scope->canManageOwnedBy($request->user(), (int) $id));
            $employees->whereIn('id', $ids);
        }

        return response()->json(['success' => true, 'data' => [
            // Every currency is offered; the base one stays first for the pickers.
            'currencies' => Currency::query()->orderByDesc('is_base')->orderBy('code')->get(['id', 'code', 'rate']),
            'languages' => Language::query()->where('is_active', true)->get(['id', 'name']),
            'employees' => $employees->get(['id', 'name']),
            'admission_employees' => $request->user()->hasPermission('assign_admission_employee')
                ? AgencyStaffAssignment::query('admission')->get(['id', 'name'])
                : [],
            'counselor_employees' => AgencyStaffAssignment::query('counselor')->get(['id', 'name']),
            'template' => ($template = ContractTemplate::with(['versions' => fn ($query) => $query->where('status', 'active')->orderByDesc('is_current')->orderByDesc('version_number')->with(['country', 'language', 'test'])])->where('service', $service)->first())
                ? new ContractTemplateResource($template) : null,
        ]]);
    }

    /** Compact list for the contract register filter; contract viewers do not
     * need the full creation-options payload to filter issued contracts. */
    public function versionOptions(Request $request, string $service): JsonResponse
    {
        abort_unless(BuiltInService::isValid($service), 404);

        $template = ContractTemplate::query()->where('service', $service)->first();

        return response()->json(['success' => true, 'data' => $template
            ? $template->versions()->orderByDesc('version_number')->get(['id', 'name', 'version_number'])
            : []]);
    }

    /**
     * The printable academy enrolment slip for one academy contract.
     *
     * Academy stays outside Templates & Policies, but each academy record is
     * issued a receipt-like A5 slip carrying the firm's branding and the
     * enrolled program details.
     */
    public function academySlip(Request $request, int $id, AcademySlipService $slips): JsonResponse
    {
        $contract = Contract::query()->findOrFail($id);
        abort_unless($contract->service === BuiltInService::ACADEMY, 404);
        abort_unless(ContractAccess::canView($request->user(), $contract), 403, 'auth.forbidden');

        return response()->json(['success' => true, 'data' => $slips->forContract($contract)]);
    }

    public function show($id): JsonResponse
    {
        $contract = Contract::with(self::RELATIONS)->findOrFail($id);
        abort_unless(ContractAccess::canView(request()->user(), $contract), 403, 'auth.forbidden');
        $contract->setAttribute('snapshot_data', $this->documentDesigns->snapshotForContract($contract));

        return $this->contractResponse($contract);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $data = $this->validatedData($request, partial: true);
        $contract = DB::transaction(function () use ($request, $id, $data): Contract {
            $contract = Contract::query()->lockForUpdate()->findOrFail($id);
            abort_unless(ContractAccess::canManage($request->user(), $contract), 403, 'auth.forbidden');
            abort_unless($contract->status === 'draft' && ! $contract->is_cancelled, 422, 'business.finalized_contract_immutable');
            abort_if($contract->service !== null || $contract->contractType?->slug === 'customer', 422, 'business.builtin_contract_creation_required');

            $renderInputs = [
                'template_id', 'contract_template_version_id', 'country_id', 'language_id',
                'employee_id', 'applicant_id', 'content', 'status', 'start_date', 'end_date',
                'company_signatory_name', 'counterparty', 'counterparty_company',
                'counterparty_email', 'counterparty_contact', 'counterparty_address',
            ];
            if (array_intersect(array_keys($data), $renderInputs) !== []) {
                $data = [...$data, ...$this->documentAttributes($contract, $data)];
            }
            $contract->update($data);
            abort_unless(ContractAccess::canManage($request->user(), $contract->fresh()), 403, 'auth.forbidden');

            return $contract;
        });

        return $this->contractResponse($contract);
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $contract = DB::transaction(function () use ($id, $request): Contract {
            $contract = Contract::query()->lockForUpdate()->findOrFail($id);
            abort_unless(ContractAccess::canManage($request->user(), $contract), 403, 'auth.forbidden');
            abort_if($contract->is_cancelled, 422, 'business.contract_already_cancelled');
            abort_unless(in_array($contract->status, ['draft', 'active'], true), 422, 'business.contract_not_cancellable');
            $contract->update(['is_cancelled' => true, 'cancelled_at' => now('UTC'), 'status' => 'cancelled']);

            return $contract;
        });

        return $this->contractResponse($contract);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        DB::transaction(function () use ($id, $request): void {
            $contract = Contract::query()->lockForUpdate()->findOrFail($id);
            abort_unless(ContractAccess::canManage($request->user(), $contract), 403, 'auth.forbidden');
            abort_unless($contract->status === 'draft' && ! $contract->is_cancelled, 422, 'business.only_draft_contracts_deletable');
            abort_if($contract->applicantContracts()->exists(), 422, 'business.only_draft_contracts_deletable');
            $contract->delete();
        });

        return response()->json(['success' => true]);
    }

    public function placeholders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_type_id' => ['nullable', 'integer', 'exists:contract_types,id'],
            'template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],
        ]);
        $slug = ! empty($data['template_id'])
            ? ContractTemplate::findOrFail($data['template_id'])->contractType?->slug
            : (! empty($data['contract_type_id']) ? ContractType::findOrFail($data['contract_type_id'])->slug : null);

        return response()->json(['success' => true, 'data' => PlaceholderRegistry::groupsForSlug($slug)]);
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $exists = fn (string $table) => Rule::exists($table, 'id')->whereNull('deleted_at');
        $data = $request->validate([
            'template_id' => [$partial ? 'sometimes' : 'required', 'integer', $exists('contract_templates')],
            'contract_template_version_id' => ['nullable', 'integer', $exists('contract_template_versions')],
            'parent_contract_id' => ['nullable', 'integer', $exists('contracts')],
            'country_id' => ['nullable', 'integer', $exists('countries')],
            'language_id' => ['nullable', 'integer', $exists('languages')],
            'employee_id' => ['nullable', 'integer', $exists('employees')],
            'applicant_id' => ['nullable', 'integer', $exists('applicants')],
            'name' => ['sometimes', 'string', 'max:255'],
            'cover_text' => ['nullable', 'string'],
            'content' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'completed', 'terminated'])],
            'start_date' => [$partial ? 'sometimes' : 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'company_signatory_name' => ['nullable', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'counterparty_company' => ['nullable', 'string', 'max:255'],
            'counterparty_email' => ['nullable', 'email', 'max:255'],
            'counterparty_contact' => ['nullable', 'string', 'max:255'],
            'counterparty_address' => ['nullable', 'string', 'max:5000'],
            'service' => ['prohibited'], 'service_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'service_ids' => ['prohibited'], 'service_lines' => ['prohibited'],
            'is_custom' => ['prohibited'],
            'is_cancelled' => ['prohibited'], 'contract_type_id' => ['prohibited'],
            'sold_by_employee_id' => ['prohibited'], 'marketer_id' => ['prohibited'],
        ]);
        if (! $partial || array_key_exists('company_signatory_name', $data)) {
            $actor = $request->user();
            $default = $actor->employee?->name ?: $actor->name;
            $data['company_signatory_name'] = $actor->isSuperAdmin()
                ? ($data['company_signatory_name'] ?? $default) : $default;
        }

        return $data;
    }

    private function genericTemplate(int $id): ContractTemplate
    {
        $template = ContractTemplate::with('contractType')->findOrFail($id);
        $type = $template->contractType;
        abort_if($template->service !== null || $type?->slug === 'customer', 422, 'business.builtin_contract_creation_required');
        abort_unless($type?->is_active && ($type->slug === 'employment' || $type->slug === 'other' || ! $type->is_system), 422, 'business.contract_type_unavailable');

        return $template;
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:contract_templates,id'],
            'contract_template_version_id' => ['nullable', 'integer', 'exists:contract_template_versions,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'applicant_id' => ['nullable', 'integer', 'exists:applicants,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'company_signatory_name' => ['nullable', 'string', 'max:255'],
        ]);
        $template = $this->genericTemplate($data['template_id']);
        abort_unless(ContractAccess::canAccessTemplate($request->user(), $template), 403, 'auth.forbidden');
        // Give the unsaved draft its real category (employment vs other) so
        // the manage check does not fall back to `other`.
        $contract = new Contract([...$data, 'contract_type_id' => $template->contract_type_id]);
        abort_unless(ContractAccess::canManage($request->user(), $contract), 403, 'auth.forbidden');
        $attributes = $this->documentAttributes($contract, $data, true);

        return response()->json(['data' => ['content' => $attributes['content']]]);
    }

    private function documentAttributes(Contract $contract, array $data, bool $preview = false): array
    {
        $template = $this->genericTemplate($data['template_id'] ?? $contract->template_id);
        $type = $template->contractType;
        $employeeId = array_key_exists('employee_id', $data) ? $data['employee_id'] : $contract->employee_id;
        $applicantId = array_key_exists('applicant_id', $data) ? $data['applicant_id'] : $contract->applicant_id;
        $employee = $type->slug === 'employment' && $employeeId
            ? Employee::with(['position', 'office', 'user'])->findOrFail($employeeId) : null;
        abort_if($type->slug === 'employment' && ! $employee, 422, 'business.contract_employee_required');
        $applicant = $type->slug !== 'employment' && $applicantId ? Applicant::findOrFail($applicantId) : null;
        $context = $employee ? ['employee' => $employee] : ($applicant ? ['applicant' => $applicant] : []);
        // Employment templates are not versioned per country/language, so they
        // resolve their single current version and carry no scope columns.
        $isEmployment = $type->slug === 'employment';
        $countryId = array_key_exists('country_id', $data) ? $data['country_id'] : $contract->contract_country_id;
        $languageId = array_key_exists('language_id', $data) ? $data['language_id'] : $contract->contract_language_id;
        if ($isEmployment) {
            $countryId = null;
            $languageId = null;
        }
        $versionId = array_key_exists('contract_template_version_id', $data)
            ? $data['contract_template_version_id']
            : ($template->id === $contract->template_id ? $contract->contract_template_version_id : null);
        $version = $versionId ? ContractTemplateVersion::findOrFail($versionId) : null;
        if (! $version && $isEmployment) {
            $version = $template->versions()->where('is_current', true)->where('status', 'active')->first();
        }
        if (! $version && $languageId && ! $isEmployment) {
            $version = $template->versions()->where('country_id', $countryId)->where('language_id', $languageId)
                ->where('is_current', true)->where('status', 'active')->first();
        }
        abort_if($template->versions()->exists() && (! $version || (! $isEmployment && ! $languageId)), 422, 'business.contract_template_version_unavailable');
        abort_if($version && ($version->contract_template_id !== $template->id
            || ! $version->is_current || $version->status !== 'active'
            || (! $isEmployment && ((int) $version->country_id !== (int) $countryId || (int) $version->language_id !== (int) $languageId))), 422, 'business.contract_template_version_unavailable');
        $start = $data['start_date'] ?? $contract->start_date?->toDateString() ?? now()->toDateString();
        $end = array_key_exists('end_date', $data) ? $data['end_date'] : $contract->end_date?->toDateString();
        abort_if($end && $end < $start, 422, 'business.contract_end_date_invalid');
        $signatory = $data['company_signatory_name'] ?? $contract->company_signatory_name;
        $rendered = $this->resolver->resolve($version?->content ?? $template->content ?? '', [
            ...$context, 'contract_number' => $contract->contract_number, 'start_date' => $start,
            'status' => $data['status'] ?? $contract->status, 'company_signatory_name' => $signatory,
        ], $type->slug, $preview);
        abort_if($rendered['unresolved'] !== [], 422, 'business.unresolved_placeholders');
        abort_if(! $preview && $rendered['missing'] !== [], 422, 'business.missing_placeholder_values');
        // Other contracts are free-form templates the office completes by hand:
        // they carry no counterparty at all, so nothing is derived from the
        // optional record link and nothing is required.
        $isOther = $type->slug === 'other';
        $partyChanged = array_key_exists('employee_id', $data) || array_key_exists('applicant_id', $data);
        $previous = fn (string $key) => ($partyChanged || $isOther) ? null : $contract->{$key};
        $counterparty = $isOther ? [
            'counterparty' => '',
            'counterparty_company' => null,
            'counterparty_email' => null,
            'counterparty_contact' => null,
            'counterparty_address' => null,
        ] : [
            'counterparty' => $employee?->name ?? $applicant?->name ?? $previous('counterparty'),
            'counterparty_company' => $employee?->position?->title ?? $previous('counterparty_company'),
            'counterparty_email' => $employee?->user?->email ?? $applicant?->email ?? $previous('counterparty_email'),
            'counterparty_contact' => $employee?->contact ?? $applicant?->contact ?? $previous('counterparty_contact'),
            'counterparty_address' => $employee?->addr ?? $applicant?->address ?? $previous('counterparty_address'),
        ];
        if (! $employee && ! $isOther) {
            foreach (array_keys($counterparty) as $key) {
                if (array_key_exists($key, $data)) {
                    if ($key === 'counterparty' && $applicant && ! filled($data[$key])) {
                        continue;
                    }
                    $counterparty[$key] = $data[$key];
                }
            }
        }
        abort_unless($isOther || filled($counterparty['counterparty']), 422, 'business.contract_counterparty_required');
        $party = ['label' => $counterparty['counterparty'], 'fields' => $counterparty];

        return [
            'template_id' => $template->id, 'contract_type_id' => $type->id,
            'type' => $type->name, 'name' => $data['name'] ?? $template->name,
            'cover_text' => $data['cover_text'] ?? $template->cover_text,
            'content' => $this->sanitizer->clean($rendered['content']),
            'start_date' => $start, 'end_date' => $end, 'company_signatory_name' => $signatory,
            'contract_template_version_id' => $version?->id,
            'contract_country_id' => $countryId, 'contract_language_id' => $languageId,
            'employee_id' => $employee?->id, 'applicant_id' => $applicant?->id,
            'is_custom' => false, ...$counterparty,
            'snapshot_data' => [
                'parties' => ['our_company' => PlaceholderRegistry::ourCompanyValues($signatory), 'counterparty' => $party],
                'entity' => $employee ? ['employee' => $this->employeeSnapshot($employee)] : ['applicant_id' => $applicant?->id],
                'template_version' => $version ? [
                    'id' => $version->id, 'name' => $version->name, 'version_number' => $version->version_number,
                    'template_id' => $template->id, 'template_name' => $template->name,
                    'country_id' => $version->country_id, 'country_name' => $version->country?->name,
                    'language_id' => $version->language_id, 'language_name' => $version->language?->name,
                    'language_code' => $version->language?->code,
                ] : null,
                'document_design' => $this->documentDesigns->snapshot($this->documentDesigns->resolve($type->id)),
            ],
        ];
    }

    private function employeeSnapshot(Employee $employee): array
    {
        return [
            'id' => $employee->id, 'name' => $employee->name, 'father_name' => $employee->father_name,
            'national_id' => $employee->id_number, 'phone' => $employee->contact,
            'emergency_contact_name' => $employee->emergency_contact_name,
            'emergency_contact_phone' => $employee->emergency_contact_phone,
            'email' => $employee->user?->email, 'address' => $employee->addr,
            'education' => $employee->education_lvl, 'position' => $employee->position?->title,
            'department' => $employee->department, 'office' => $employee->office?->name,
            'start_date' => $employee->join_date?->format('Y-m-d'),
            'contract_duration' => $employee->contract_duration,
            'paid_leave_days' => $employee->off_day_allowance_days !== null ? (float) $employee->off_day_allowance_days : null,
            'salary' => $employee->salary,
        ];
    }

    private function contractResponse(Contract $contract, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true, 'data' => new ContractResource($contract->load(self::RELATIONS)),
        ], $status);
    }
}
