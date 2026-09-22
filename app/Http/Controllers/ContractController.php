<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractResource;
use App\Models\Company;
use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractDocumentDesign;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Customer;
use App\Models\Employee;
use App\Services\Contracts\ContentSanitizer;
use App\Services\Contracts\PlaceholderRegistry;
use App\Services\Contracts\PlaceholderResolver;
use App\Support\CompanyProfile;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContractController extends Controller
{
    private const EDUCATIONAL_SLUGS = [];
    private const SUPPORTED_SLUGS = ['employment', 'customer', 'other'];

    public function __construct(
        private readonly PlaceholderResolver $resolver,
        private readonly ContentSanitizer $sanitizer,
        private readonly CompanyProfile $companyProfile,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'audience' => 'sometimes|string|in:customers,employees,other',
            'type_slug' => 'sometimes|string',
            'service_id' => 'sometimes|integer|exists:services,id',
            'without_service' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:'.implode(',', Contract::STATUSES),
        ]);

        $query = Contract::with(['template.contractType', 'contractType', 'service.currency', 'employee.position', 'employee.office', 'employee.user', 'customer.customerServices.service.currency', 'company']);
        $query->where(function ($query) {
                $query->whereHas('contractType', fn ($type) => $type->where('is_active', true))
                ->orWhereHas('template.contractType', fn ($type) => $type->where('is_active', true))
                ->orWhere(function ($legacy) {
                    $legacy->whereNull('contract_type_id')->whereNull('template_id')
                        ->whereRaw('LOWER(COALESCE(type, "")) IN ("employment", "customer", "company")');
                });
        });

        if ($slug = $request->input('type_slug')) {
            $query->where(function ($q) use ($slug) {
                $q->whereHas('contractType', fn ($t) => $t->where('slug', $slug))
                    ->orWhereHas('template.contractType', fn ($t) => $t->where('slug', $slug));
                if ($slug === 'employment') {
                    $q->orWhere(function ($legacy) {
                        $legacy->whereNull('contract_type_id')->whereNull('template_id')
                            ->whereRaw('LOWER(COALESCE(type, "")) = "employment"');
                    });
                }
            });
        } elseif ($request->input('audience') === 'employees') {
            $query->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('contractType', fn ($t) => $t->where('slug', 'employment'))
                        ->orWhereHas('template.contractType', fn ($t) => $t->where('slug', 'employment'));
                })->orWhere(function ($legacy) {
                    $legacy->whereNull('contract_type_id')->whereNull('template_id')
                        ->whereRaw('LOWER(COALESCE(type, "")) = "employment"');
                });
            });
        } elseif ($request->input('audience') === 'customers') {
            // Customer contracts have their own section; companies and custom
            // contract types must not appear in this list.
            $query->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('contractType', fn ($t) => $t->whereIn('slug', ['customer', ...self::EDUCATIONAL_SLUGS]))
                        ->orWhereHas('template.contractType', fn ($t) => $t->whereIn('slug', ['customer', ...self::EDUCATIONAL_SLUGS]));
                })->orWhereDoesntHave('contractType');
            })->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('contract_type_id')->whereNull('template_id')
                        ->whereRaw('LOWER(COALESCE(type, "")) = "customer"');
                })->orWhereHas('contractType', fn ($t) => $t->whereIn('slug', ['customer', ...self::EDUCATIONAL_SLUGS]))
                    ->orWhereHas('template.contractType', fn ($t) => $t->whereIn('slug', ['customer', ...self::EDUCATIONAL_SLUGS]));
            });
        } elseif ($request->input('audience') === 'educational') {
            $query->where(function ($q) {
                $q->whereHas('contractType', fn ($t) => $t->whereIn('slug', self::EDUCATIONAL_SLUGS))
                    ->orWhereHas('template.contractType', fn ($t) => $t->whereIn('slug', self::EDUCATIONAL_SLUGS));
            });
        } elseif ($request->input('audience') === 'other') {
            $query->where(function ($query) {
                $query->whereHas('contractType', fn ($t) => $t->where('slug', 'other')->orWhere('is_system', false))
                    ->orWhereHas('template.contractType', fn ($t) => $t->where('slug', 'other')->orWhere('is_system', false))
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('contract_type_id')->whereNull('template_id')
                        ->whereRaw('LOWER(COALESCE(type, "")) IN ("company", "other")');
                    });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('contract_number', 'like', "%{$search}%")
                    ->orWhere('counterparty', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('only-cancelled')) {
            $query->where(fn ($q) => $q->where('is_cancelled', true)->orWhere('status', 'cancelled'));
        }

        return response()->json([
            'success' => true,
            'data' => ContractResource::collection($query->get()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);
        $data['is_cancelled'] = false;

        $result = $this->createContract($data);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'success' => true,
            'data' => new ContractResource($result->load(['template.contractType', 'contractType', 'service.currency', 'employee', 'customer.customerServices.service.currency', 'company'])),
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $contract = Contract::with(['template.contractType', 'contractType', 'service.currency', 'employee.position', 'customer.customerServices.service.currency', 'company'])->find($id);
        if (! $contract) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $contract = Contract::find($id);
        if (! $contract) {
            return $this->notFound();
        }
        abort_unless($contract->status === 'draft' && ! $contract->is_cancelled, 422, 'business.finalized_contract_immutable');

        $data = $this->validatedData($request, partial: true);

        $templateId = $data['template_id'] ?? $contract->template_id;
        $template = $templateId ? ContractTemplate::find($templateId) : null;
        $type = $template?->contractType ?? ($template ? null : $contract->contractType);
        if (array_key_exists('contract_type_id', $data)) {
            $type = ContractType::find($data['contract_type_id']) ?? $type;
        }
        abort_unless($type?->is_active, 422, 'Select an active contract type.');
        $slug = $type?->slug;
        if (array_key_exists('duration_months', $data)) {
            $startDate = $data['start_date'] ?? $contract->start_date?->toDateString();
            if ($data['duration_months'] && $startDate) {
                $data['end_date'] = Carbon::parse($startDate)->addMonthsNoOverflow((int) $data['duration_months'])->toDateString();
            }
        }

        $effectiveStartDate = $data['start_date'] ?? $contract->start_date?->toDateString();
        $effectiveEndDate = array_key_exists('end_date', $data)
            ? $data['end_date']
            : $contract->end_date?->toDateString();
        if ($effectiveStartDate && $effectiveEndDate && Carbon::parse($effectiveEndDate)->lt(Carbon::parse($effectiveStartDate))) {
            throw ValidationException::withMessages([
                'end_date' => ['validation.after_or_equal'],
            ]);
        }

        // Re-render only when the template or one of its input values changed.
        $renderInputs = [
            'template_id', 'contract_template_version_id', 'service_id', 'employee_id', 'customer_id', 'applicant_id', 'company_id', 'content',
            'start_date', 'end_date', 'duration_months', 'status', 'company_signatory_name',
        ];
        $shouldRender = collect($renderInputs)->contains(fn (string $field) => array_key_exists($field, $data));

        if ($template && $slug === 'employment') {
            $employeeId = $data['employee_id'] ?? $contract->employee_id;
            if (! Employee::find($employeeId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'An employee is required for an employment contract.',
                ], 422);
            }
            $data['employee_id'] = $employeeId;
        }

        if ($shouldRender) {
            $contextEntity = $this->resolveContextEntity($slug, [
                'employee_id' => $data['employee_id'] ?? $contract->employee_id,
                'customer_id' => $data['customer_id'] ?? $contract->customer_id,
                'applicant_id' => $data['applicant_id'] ?? $contract->applicant_id,
                'company_id' => $data['company_id'] ?? $contract->company_id,
                'contract_type_id' => $type?->id,
            ]);

            $versionId = $data['contract_template_version_id'] ?? $contract->contract_template_version_id;
            $version = $versionId ? \App\Models\ContractTemplateVersion::query()->find($versionId) : null;
            $caseCountryId = ! empty($data['applicant_id'] ?? $contract->applicant_id)
                ? Applicant::query()->whereKey($data['applicant_id'] ?? $contract->applicant_id)->value('country_id')
                : null;
            abort_if($version && $version->contract_template_id !== $template?->id, 422, 'business.contract_template_version_mismatch');
            abort_if($version && $version->country_id && (int) $version->country_id !== (int) $caseCountryId, 422, 'business.contract_template_version_country_mismatch');
            if (array_key_exists('contract_template_version_id', $data)) {
                abort_if($version && (! $version->is_current || $version->status !== 'active'), 422, 'business.contract_template_version_unavailable');
            }
            $content = $version?->content ?? $template?->content ?? ($data['content'] ?? $contract->content);
            $rendered = $this->resolver->resolve((string) $content, $contextEntity['context'] + [
                'start_date' => $data['start_date'] ?? $contract->start_date?->toDateString(),
                'end_date' => $data['end_date'] ?? $contract->end_date?->toDateString(),
                'status' => $data['status'] ?? $contract->status,
                'company_signatory_name' => $data['company_signatory_name'] ?? $contract->company_signatory_name,
            ], $slug);

            if ($rendered['unresolved'] !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'The document contains placeholders that are not valid for this contract type: '
                        .implode(', ', $rendered['unresolved']),
                    'code' => 'business.unresolved_placeholders',
                    'placeholders' => $rendered['unresolved'],
                ], 422);
            }

            if ($rendered['missing'] !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some contract fields have no value and cannot be left empty in a finalized document: '
                        .implode(', ', $rendered['missing']),
                    'code' => 'business.missing_placeholder_values',
                    'placeholders' => $rendered['missing'],
                ], 422);
            }

            $counterparty = $this->buildCounterparty($slug, $contextEntity);

            $data += [
                'content' => $this->sanitizer->clean($rendered['content']),
                'contract_type_id' => $type?->id,
                'type' => $type?->name,
                'snapshot_data' => [
                    'parties' => [
                        'our_company' => PlaceholderRegistry::ourCompanyValues($data['company_signatory_name'] ?? $contract->company_signatory_name),
                        'counterparty' => $counterparty,
                    ],
                    'entity' => $contextEntity['snapshot'],
                    'template_version' => $version ? ['id' => $version->id, 'country_id' => $version->country_id, 'language_id' => $version->language_id, 'version_number' => $version->version_number] : null,
                ],
                ...$counterparty['fields'],
                ...$contextEntity['foreign_keys'],
            ];
        }

        $contract->update($data);

        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract->load(['template.contractType', 'contractType', 'service.currency', 'employee', 'customer.customerServices.service.currency', 'company'])),
        ]);
    }

    public function cancel($id): JsonResponse
    {
        $contract = DB::transaction(function () use ($id): Contract {
            $contract = Contract::query()->lockForUpdate()->findOrFail($id);
            abort_if($contract->is_cancelled, 422, 'business.contract_already_cancelled');
            abort_unless(in_array($contract->status, ['draft', 'active'], true), 422, 'business.contract_not_cancellable');
            $contract->update(['is_cancelled' => true, 'cancelled_at' => now('UTC'), 'status' => 'cancelled']);

            return $contract;
        });

        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract->load(['template.contractType', 'contractType'])),
        ]);
    }

    /**
     * Continue an active or expired agreement as a new draft. The source
     * contract remains immutable and the new document gets fresh dates,
     * placeholders, snapshots, and a distinct contract number.
     */
    public function renew(Request $request, $id): JsonResponse
    {
        $contract = Contract::with(['template.contractType', 'contractType'])->find($id);
        if (! $contract) {
            return $this->notFound();
        }

        abort_if($contract->is_cancelled, 422, 'business.contract_not_renewable');
        abort_unless(in_array(strtolower($contract->status), ['active', 'expired'], true), 422, 'business.contract_not_renewable');
        abort_unless($contract->template_id && ContractTemplate::query()->whereKey($contract->template_id)->exists(), 422, 'business.contract_template_unavailable');
        abort_if(Contract::query()
            ->where('snapshot_data->renewal->source_contract_id', $contract->id)
            ->whereRaw('LOWER(status) NOT IN (?, ?)', ['cancelled', 'terminated'])
            ->exists(), 422, 'business.contract_renewal_exists');

        $this->normalizeLocalizedInteger($request, 'duration_months');
        $validated = $request->validate([
            'duration_months' => ['required', 'integer', 'min:1', 'max:600'],
            'start_date' => ['nullable', 'date'],
        ]);

        $today = Carbon::today($this->companyProfile->timezone());
        $earliestStart = $contract->end_date?->copy()->addDay();
        if (! $earliestStart || $earliestStart->lt($today)) {
            $earliestStart = $today;
        }
        $startDate = Carbon::parse($validated['start_date'] ?? $earliestStart->toDateString());

        if ($startDate->lt($earliestStart)) {
            throw ValidationException::withMessages([
                'start_date' => ['The renewal must start on or after '.$earliestStart->toDateString().'.'],
            ]);
        }

        $durationMonths = (int) $validated['duration_months'];
        $data = [
            'template_id' => $contract->template_id,
            'language_id' => $contract->templateVersion?->language_id,
            'contract_type_id' => $contract->contract_type_id,
            'service_id' => $contract->service_id,
            'employee_id' => $contract->employee_id,
            'customer_id' => $contract->customer_id,
            'applicant_id' => $contract->applicant_id,
            'company_id' => $contract->company_id,
            'name' => $contract->name,
            'cover_text' => $contract->cover_text,
            'status' => 'draft',
            'start_date' => $startDate->toDateString(),
            'end_date' => $startDate->copy()->addMonthsNoOverflow($durationMonths)->toDateString(),
            'duration_months' => $durationMonths,
            'company_signatory_name' => $contract->company_signatory_name,
            'is_cancelled' => false,
        ];

        $result = DB::transaction(function () use ($contract, $data): Contract {
            $result = $this->createContract($data, [
                'renewal' => [
                    'source_contract_id' => $contract->id,
                    'source_contract_number' => $contract->contract_number,
                ],
            ]);

            if ($result instanceof JsonResponse) {
                throw ValidationException::withMessages([
                    'renewal' => [$result->getContent()],
                ]);
            }

            return $result;
        });
        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'success' => true,
            'data' => new ContractResource($result->load(['template.contractType', 'contractType', 'service.currency', 'employee', 'customer.customerServices.service.currency', 'company'])),
        ], 201);
    }

    public function destroy($id): JsonResponse
    {
        $contract = Contract::find($id);
        if (! $contract) {
            return $this->notFound();
        }
        abort_unless($contract->status === 'draft' && ! $contract->is_cancelled, 422, 'business.only_draft_contracts_deletable');

        $contract->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contract deleted successfully',
        ]);
    }

    public function templates(): JsonResponse
    {
        $templates = ContractTemplate::whereHas('contractType', fn ($type) => $type->where('is_active', true))
            ->with('contractType')->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Available placeholder groups for a contract type. The frontend editor
     * consumes this instead of maintaining its own placeholder lists.
     */
    public function placeholders(Request $request): JsonResponse
    {
        $request->validate([
            'contract_type_id' => 'nullable|integer|exists:contract_types,id',
            'template_id' => 'nullable|integer|exists:contract_templates,id',
        ]);

        $slug = null;
        if ($request->filled('template_id')) {
            $slug = ContractTemplate::with('contractType')->find($request->input('template_id'))?->getTypeSlugAttribute();
        } elseif ($request->filled('contract_type_id')) {
            $slug = ContractType::find($request->input('contract_type_id'))?->slug;
        }

        return response()->json([
            'success' => true,
            'data' => PlaceholderRegistry::groupsForSlug($slug),
        ]);
    }

    // ------------------------------------------------------------------

    private function validatedData(Request $request, bool $partial = false): array
    {
        $this->normalizeLocalizedInteger($request, 'duration_months');

        $sometimes = $partial ? ['sometimes'] : [];

        $validator = Validator::make($request->all(), [
            'template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'contract_template_version_id' => ['nullable', 'integer', 'exists:contract_template_versions,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'contract_type_id' => ['nullable', 'integer', 'exists:contract_types,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'applicant_id' => ['nullable', 'integer', 'exists:applicants,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'is_custom' => ['prohibited'],
            'name' => [...$sometimes, 'string', 'max:255'],
            'cover_text' => ['nullable', 'string'],
            'content' => [...$sometimes, 'string'],
            'status' => [...$sometimes, 'string', 'in:draft,active'],
            'start_date' => [$partial ? 'sometimes' : 'nullable', 'date'],
            'end_date' => $partial
                ? ['sometimes', 'nullable', 'date']
                : ['nullable', 'date', 'after_or_equal:start_date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'company_signatory_name' => ['nullable', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'counterparty_company' => ['nullable', 'string', 'max:255'],
            'counterparty_email' => ['nullable', 'email', 'max:255'],
            'counterparty_contact' => ['nullable', 'string', 'max:255'],
            'counterparty_address' => ['nullable', 'string', 'max:5000'],
            'is_cancelled' => ['prohibited'],
        ]);

        $validator->after(function ($validator) use ($request, $partial) {
            if (! $partial && ! $request->filled('template_id')) {
                $validator->errors()->add('template_id', 'Select a supported contract template.');
            }
        });

        $validator->validate();

        $data = $validator->validated();

        $customerId = $data['customer_id'] ?? null;
        $applicantId = $data['applicant_id'] ?? null;
        if ($applicantId && $customerId && ! Applicant::query()->whereKey($applicantId)->where('customer_id', $customerId)->exists()) {
            throw ValidationException::withMessages(['applicant_id' => ['The applicant must belong to the selected customer.']]);
        }

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->integer('service_id'));
        } elseif ($request->boolean('without_service')) {
            $query->whereNull('service_id');
        }

        if (! $partial && empty($data['start_date'])) {
            $data['start_date'] = now()->toDateString();
        }

        if (! $partial && ! empty($data['duration_months'])) {
            $data['end_date'] = Carbon::parse($data['start_date'])
                ->addMonthsNoOverflow((int) $data['duration_months'])
                ->toDateString();
        }

        return $data;
    }

    /** @param array<string, mixed> $snapshotMetadata */
    private function createContract(array $data, array $snapshotMetadata = []): Contract|JsonResponse
    {
        [$template, $type] = $this->resolveTemplateAndType($data);
        $data['service_id'] ??= $template?->service_id;
        $contextEntity = $this->resolveContextEntity($type?->slug, $data);
        $caseCountryId = ! empty($data['applicant_id']) ? Applicant::query()->whereKey($data['applicant_id'])->value('country_id') : null;
        $version = isset($data['contract_template_version_id']) ? \App\Models\ContractTemplateVersion::find($data['contract_template_version_id']) : null;
        if ($template && ! $version && isset($data['language_id'])) {
            $version = $template->versions()
                ->where('language_id', $data['language_id'])
                ->where('country_id', $caseCountryId)
                ->where('is_current', true)
                ->where('status', 'active')
                ->first();
        }
        // Existing pre-version templates remain usable until a language
        // version is explicitly added; versioned templates never fall back.
        $hasVersions = $template?->versions()->exists() ?? false;
        abort_if($template && $hasVersions && ! isset($data['language_id']) && ! $version, 422, 'business.contract_template_language_required');
        abort_if($template && $hasVersions && ! $version, 422, 'business.contract_template_version_unavailable');
        abort_if($version && $version->contract_template_id !== $template?->id, 422, 'business.contract_template_version_mismatch');
        abort_if($version && isset($data['language_id']) && $version->language_id !== (int) $data['language_id'], 422, 'business.contract_template_version_language_mismatch');
        abort_if($version && $version->country_id && (int) $version->country_id !== (int) $caseCountryId, 422, 'business.contract_template_version_country_mismatch');
        abort_if($version && (! $version->is_current || $version->status !== 'active'), 422, 'business.contract_template_version_unavailable');
        $content = $version?->content ?? ($template ? ($template->content ?? '') : ($data['content'] ?? ''));

        $rendered = $this->resolver->resolve(
            $content,
            $contextEntity['context'] + [
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'],
                'company_signatory_name' => $data['company_signatory_name'] ?? null,
            ],
            $type?->slug,
        );

        if ($rendered['unresolved'] !== []) {
            return response()->json([
                'success' => false,
                'message' => 'The document contains placeholders that are not valid for this contract type: '
                    .implode(', ', $rendered['unresolved']),
                'code' => 'business.unresolved_placeholders',
                'placeholders' => $rendered['unresolved'],
            ], 422);
        }

        if ($rendered['missing'] !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Some contract fields have no value and cannot be left empty in a finalized document: '
                    .implode(', ', $rendered['missing']),
                'code' => 'business.missing_placeholder_values',
                'placeholders' => $rendered['missing'],
            ], 422);
        }

        $counterparty = $this->buildCounterparty($type?->slug, $contextEntity);
        if ($type?->slug === 'other' || ! $type?->is_system) {
            $counterparty['fields'] = [
                'counterparty' => $data['counterparty'] ?? '',
                'counterparty_company' => $data['counterparty_company'] ?? null,
                'counterparty_email' => $data['counterparty_email'] ?? null,
                'counterparty_contact' => $data['counterparty_contact'] ?? null,
                'counterparty_address' => $data['counterparty_address'] ?? null,
            ];
            $counterparty['label'] = $counterparty['fields']['counterparty'];
        }
        $design = ContractDocumentDesign::query()
            ->where('contract_type_id', $type?->id)
            ->where(fn ($query) => $query->where('service_id', $data['service_id'] ?? $template?->service_id)->orWhereNull('service_id'))
            ->orderByRaw('service_id IS NULL')
            ->first();

        return Contract::create([
            ...$data,
            'name' => $data['name'] ?? $template?->name,
            'cover_text' => $data['cover_text'] ?? $template?->cover_text,
            'content' => $this->sanitizer->clean($rendered['content']),
            'contract_type_id' => $type?->id,
            'type' => $type?->name,
            'is_custom' => ! $template,
            'contract_template_version_id' => $version?->id,
            ...$counterparty['fields'],
            ...$contextEntity['foreign_keys'],
            'snapshot_data' => [
                'parties' => [
                    'our_company' => PlaceholderRegistry::ourCompanyValues($data['company_signatory_name'] ?? null),
                    'counterparty' => $counterparty,
                ],
                'entity' => $contextEntity['snapshot'],
                'template_version' => $version ? ['id'=>$version->id,'country_id'=>$version->country_id,'language_id'=>$version->language_id,'version_number'=>$version->version_number] : null,
                'document_design' => $design ? [
                    'id' => $design->id, 'letterhead' => $design->letterhead, 'cover_design' => $design->cover_design,
                    'safe_x' => $design->safe_x, 'safe_y' => $design->safe_y, 'safe_width' => $design->safe_width, 'safe_height' => $design->safe_height,
                ] : null,
                ...$snapshotMetadata,
            ],
        ]);
    }

    /**
     * HTML number inputs can submit Persian or Arabic-Indic digits when the
     * application is used in a right-to-left locale. Laravel's integer rule
     * only accepts ASCII digits, so normalize those representations first.
     */
    private function normalizeLocalizedInteger(Request $request, string $field): void
    {
        $value = $request->input($field);
        if (! is_string($value)) {
            return;
        }

        $normalized = strtr(trim($value), [
            "\u{06F0}" => '0', "\u{06F1}" => '1', "\u{06F2}" => '2', "\u{06F3}" => '3', "\u{06F4}" => '4',
            "\u{06F5}" => '5', "\u{06F6}" => '6', "\u{06F7}" => '7', "\u{06F8}" => '8', "\u{06F9}" => '9',
            "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3', "\u{0664}" => '4',
            "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7', "\u{0668}" => '8', "\u{0669}" => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        if ($normalized !== $value) {
            $request->merge([$field => $normalized]);
        }
    }

    /** @return array{0: ?ContractTemplate, 1: ?ContractType} */
    private function resolveTemplateAndType(array $data): array
    {
        $template = isset($data['template_id']) ? ContractTemplate::with('contractType')->find($data['template_id']) : null;

        if ($template) {
            abort_unless($template->contractType?->is_active, 422, 'The selected contract type is inactive.');

            return [$template, $template->contractType];
        }

        $type = isset($data['contract_type_id'])
            ? ContractType::find($data['contract_type_id'])
            : null;

        abort_unless($type?->is_active, 422, 'Select an active contract type.');

        return [null, $type];
    }

    /**
     * Enforce the contract-type → entity mapping on the backend:
     * employment ⇒ employee, customer ⇒ customer, company ⇒ company.
     *
     * @return array{context: array, snapshot: array, foreign_keys: array}
     */
    private function resolveContextEntity(?string $slug, array $data): array
    {
        $employeeId = isset($data['employee_id']) ? (int) $data['employee_id'] : null;
        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        $applicantId = isset($data['applicant_id']) ? (int) $data['applicant_id'] : null;
        $companyId = isset($data['company_id']) ? (int) $data['company_id'] : null;

        switch ($slug) {
            case 'employment':
                if (! $employeeId) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'An employee is required for an employment contract.',
                    ], 422));
                }
                $employee = Employee::with(['position', 'office', 'user'])->findOrFail($employeeId);

                return [
                    'context' => ['employee' => $employee],
                    'snapshot' => ['employee' => $this->employeeSnapshot($employee)],
                    'foreign_keys' => ['employee_id' => $employeeId, 'customer_id' => null, 'company_id' => null],
                ];

            case 'customer':
            case 'educational-service':
            case 'scholarship-service':
            case 'test-service':
            case 'visa-service':
                if (! $customerId) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'A customer is required for a customer contract.',
                    ], 422));
                }
                $customer = Customer::findOrFail($customerId);
                $service = ! empty($data['service_id']) ? \App\Models\Service::query()->find($data['service_id']) : null;
                $applicant = $applicantId ? Applicant::query()->findOrFail($applicantId) : null;
                if ($applicant && (int) $applicant->customer_id !== $customerId) {
                    throw ValidationException::withMessages(['applicant_id' => ['The applicant must belong to the selected customer.']]);
                }

                return [
                    'context' => ['customer' => $customer, 'service' => $service],
                    'snapshot' => ['customer' => $customer->only(['id', 'name', 'company', 'contact', 'email', 'address', 'tax_number']), 'service' => $service?->only(['id', 'type', 'category', 'description', 'duration', 'fee']), 'applicant_id' => $applicant?->id],
                    'foreign_keys' => ['employee_id' => null, 'customer_id' => $customerId, 'applicant_id' => $applicantId, 'company_id' => null],
                ];

            case 'other':
                return ['context' => [], 'snapshot' => [], 'foreign_keys' => ['employee_id' => null, 'customer_id' => null, 'company_id' => null]];

            default:
                // Custom types carry no mandatory entity; clear stale links.
                return [
                    'context' => [],
                    'snapshot' => [],
                    'foreign_keys' => [
                        'employee_id' => $employeeId,
                        'customer_id' => $customerId,
                        'company_id' => $companyId,
                    ],
                ];
        }
    }

    /**
     * Counterparty display fields derived from the selected entity so the
     * user never retypes data that already exists in the system.
     *
     * @return array{label: string, fields: array}
     */
    private function buildCounterparty(?string $slug, array $contextEntity): array
    {
        $fields = [
            'counterparty' => '',
            'counterparty_company' => null,
            'counterparty_email' => null,
            'counterparty_contact' => null,
            'counterparty_address' => null,
        ];
        $label = '';

        if (($employee = $contextEntity['context']['employee'] ?? null) !== null) {
            $label = $employee->name;
            $fields = [
                ...$fields,
                'counterparty' => $employee->name,
                'counterparty_company' => $employee->position?->title,
                'counterparty_email' => $employee->user?->email,
                'counterparty_contact' => $employee->contact,
                'counterparty_address' => $employee->addr,
            ];
        } elseif (($customer = $contextEntity['context']['customer'] ?? null) !== null) {
            $label = $customer->name;
            $fields = [
                ...$fields,
                'counterparty' => $customer->name,
                'counterparty_company' => $customer->company ?: null,
                'counterparty_email' => $customer->email,
                'counterparty_contact' => $customer->contact,
                'counterparty_address' => $customer->address,
            ];
        } elseif (($company = $contextEntity['context']['company'] ?? null) !== null) {
            $label = $company->legal_name;
            $representative = trim((string) $company->representative_name);
            $fields = [
                ...$fields,
                'counterparty' => $representative !== '' ? $representative : $company->legal_name,
                'counterparty_company' => $company->legal_name,
                'counterparty_email' => $company->representative_email ?: $company->email,
                'counterparty_contact' => $company->representative_phone ?: $company->phone,
                'counterparty_address' => collect([
                    $company->address_line, $company->city, $company->province,
                    $company->postal_code, $company->country,
                ])->filter()->implode(', ') ?: null,
            ];
        }

        return ['label' => $label, 'fields' => $fields];
    }

    private function employeeSnapshot(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'father_name' => $employee->father_name,
            'national_id' => $employee->id_number,
            'phone' => $employee->contact,
            'email' => $employee->user?->email,
            'address' => $employee->addr,
            'position' => $employee->position?->title,
            'department' => $employee->department,
            'office' => $employee->office?->name,
            'start_date' => $employee->join_date?->format('Y-m-d'),
            'salary' => $employee->salary,
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Contract not found',
        ], 404);
    }
}
