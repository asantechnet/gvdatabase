<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractResource;
use App\Models\Company;
use App\Models\ContractService;
use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractDocumentDesign;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Employee;
use App\Services\Contracts\ContentSanitizer;
use App\Services\Contracts\PlaceholderRegistry;
use App\Services\Contracts\PlaceholderResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractController extends Controller
{
    private const EDUCATIONAL_SLUGS = [];
    private const SUPPORTED_SLUGS = ['employment', 'customer', 'other'];

    public function __construct(
        private readonly PlaceholderResolver $resolver,
        private readonly ContentSanitizer $sanitizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'audience' => 'sometimes|string|in:customers,employees,other',
            'type_slug' => 'sometimes|string',
            'service_id' => 'sometimes|integer|exists:services,id',
            'service_ids' => 'sometimes|array',
            'service_ids.*' => 'integer|exists:services,id',
            'without_service' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:'.implode(',', Contract::STATUSES),
        ]);

        $query = Contract::with(['template.contractType', 'contractType', 'contractServices.service.currency', 'contractServices.currency', 'employee.position', 'employee.office', 'employee.user', 'applicant.contractServices.service.currency', 'company']);

        // Service filter: matches any contracted service line.
        $serviceIds = $request->input('service_ids', []);
        if ($request->filled('service_id')) {
            $serviceIds[] = $request->integer('service_id');
        }
        $serviceIds = collect($serviceIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($serviceIds !== []) {
            $query->whereHas('contractServices', fn ($lines) => $lines->whereIn('service_id', $serviceIds));
        } elseif ($request->boolean('without_service')) {
            $query->whereDoesntHave('contractServices');
        }
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
            'data' => new ContractResource($result->load(['template.contractType', 'contractType', 'contractServices.service.currency', 'contractServices.currency', 'employee', 'applicant.contractServices.service.currency', 'company'])),
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $contract = Contract::with(['template.contractType', 'contractType', 'contractServices.service.currency', 'contractServices.currency', 'employee.position', 'applicant.contractServices.service.currency', 'company'])->find($id);
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

        // Re-render only when the template or one of its input values changed.
        $renderInputs = [
            'template_id', 'contract_template_version_id', 'service_id', 'service_ids', 'service_lines', 'employee_id', 'applicant_id', 'company_id', 'content',
            'start_date', 'status', 'company_signatory_name',
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
                'applicant_id' => $data['applicant_id'] ?? $contract->applicant_id,
                'company_id' => $data['company_id'] ?? $contract->company_id,
                'contract_type_id' => $type?->id,
                'service_id' => $data['service_id'] ?? null,
                'service_ids' => $data['service_ids'] ?? $contract->contractServices()->pluck('service_id')->all(),
                'service_lines' => $data['service_lines'] ?? [],
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

        $contract->update(collect($data)->except(['service_ids', 'service_lines'])->all());

        if (array_key_exists('service_ids', $data) || array_key_exists('service_lines', $data)) {
            $contract->contractServices()->delete();
            $lines = $this->normalizeServiceLines($data);
            foreach ($lines as &$serviceLine) {
                $serviceLine['applicant_id'] ??= $data['applicant_id'] ?? $contract->applicant_id;
            }
            unset($serviceLine);
            $this->persistContractServices($contract, $lines);
            $snapshot = $contract->snapshot_data ?? [];
            $snapshot['entity']['services'] = collect($lines)->map(fn (array $line) => [
                'service_id' => $line['service_id'],
                'fee' => $line['fee'] ?? null,
                'currency_id' => $line['currency_id'] ?? null,
            ])->values()->all();
            $contract->update(['snapshot_data' => $snapshot]);
        }

        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract->load(['template.contractType', 'contractType', 'contractServices.service.currency', 'employee', 'applicant.contractServices.service.currency', 'company'])),
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
        $sometimes = $partial ? ['sometimes'] : [];

        $validator = Validator::make($request->all(), [
            'template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'contract_template_version_id' => ['nullable', 'integer', 'exists:contract_template_versions,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'contract_type_id' => ['nullable', 'integer', 'exists:contract_types,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            // Multi-select successor of `service_id`: only active catalogue
            // services may be registered on a contract.
            'service_ids' => ['nullable', 'array', 'max:1'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where('status', 'active')->whereNull('deleted_at')],
            // Per-service line overrides (fee/currency/notes/attribution).
            // Keys align with the `contract_service` pivot columns.
            'service_lines' => ['nullable', 'array', 'max:1'],
            'service_lines.*.service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'service_lines.*.fee' => ['nullable', 'numeric', 'min:0'],
            'service_lines.*.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'service_lines.*.applicant_id' => ['nullable', 'integer', 'exists:applicants,id'],
            'service_lines.*.customer_package_id' => ['nullable', 'integer', 'exists:customer_packages,id'],
            'service_lines.*.sold_by_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'service_lines.*.marketer_id' => ['nullable', 'integer', 'exists:employees,id'],
            'service_lines.*.outsourcing_company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'service_lines.*.outsourcing_percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100'],
            'service_lines.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'service_lines.*.education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'service_lines.*.language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'service_lines.*.notes' => ['nullable', 'string', 'max:2000'],
            'service_lines.*.employee_ids' => ['nullable', 'array'],
            'service_lines.*.employee_ids.*' => ['integer', 'exists:employees,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'sold_by_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'marketer_id' => ['nullable', 'integer', 'exists:employees,id'],
            'applicant_id' => ['nullable', 'integer', 'exists:applicants,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'is_custom' => ['prohibited'],
            'name' => [...$sometimes, 'string', 'max:255'],
            'cover_text' => ['nullable', 'string'],
            'content' => [...$sometimes, 'string'],
            'status' => [...$sometimes, 'string', 'in:draft,active,completed,terminated'],
            'start_date' => [$partial ? 'sometimes' : 'nullable', 'date'],
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


        if (! $partial && empty($data['start_date'])) {
            $data['start_date'] = now()->toDateString();
        }

        // Signatory is the editable account Full Name (users.name). Staff are
        // hard-locked to their own account name; super-admin is auto-filled
        // but remains editable.
        $actor = $request->user();
        if ($actor) {
            if (! $actor->isSuperAdmin()) {
                $data['company_signatory_name'] = $actor->name;
            } elseif (! $partial || array_key_exists('company_signatory_name', $data)) {
                if (empty(trim((string) ($data['company_signatory_name'] ?? '')))) {
                    $data['company_signatory_name'] = $actor->name;
                }
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $snapshotMetadata */
    private function createContract(array $data, array $snapshotMetadata = []): Contract|JsonResponse
    {
        [$template, $type] = $this->resolveTemplateAndType($data);
        // Single-service shorthand: an explicit service_id behaves like a
        // one-item service_ids list; the template's own service is the
        // fallback so template-bound contracts stay linked automatically.
        $fallbackServiceId = $data['service_id'] ?? $template?->service_id;
        unset($data['service_id']);
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
        $serviceLines = $this->normalizeServiceLines($data, $fallbackServiceId);
        foreach ($serviceLines as &$serviceLine) {
            $serviceLine['applicant_id'] ??= $data['applicant_id'] ?? null;
        }
        unset($serviceLine);
        $design = ContractDocumentDesign::query()
            ->where('contract_type_id', $type?->id)
            ->where(fn ($query) => $query->where('service_id', $serviceLines[0]['service_id'] ?? null)->orWhereNull('service_id'))
            ->orderByRaw('service_id IS NULL')
            ->first();
        if ($serviceLines !== []) {
            $contextEntity['snapshot']['services'] = collect($serviceLines)->map(fn (array $line) => [
                'service_id' => $line['service_id'],
                'fee' => $line['fee'] ?? null,
                'currency_id' => $line['currency_id'] ?? null,
                'notes' => $line['notes'] ?? null,
            ])->values()->all();
        }

        $contract = Contract::create([
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

        $this->persistContractServices($contract, $serviceLines);

        return $contract->load(['contractServices.service.currency', 'contractServices.currency']);
    }

    /**
     * Normalize the multi-select service input into `contract_service` rows.
     * Explicit `service_lines` win; bare `service_ids` become fee-inheriting
     * lines; otherwise the legacy singular link is mirrored as one line so
     * the new structure stays complete for old single-service contracts.
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeServiceLines(array $data, mixed $fallbackServiceId = null): array
    {
        if (! empty($data['service_lines'])) {
            $lines = collect($data['service_lines']);
        } elseif (! empty($data['service_ids'])) {
            $lines = collect($data['service_ids'])->map(fn ($id) => ['service_id' => (int) $id]);
        } elseif ($fallbackServiceId) {
            $lines = collect([['service_id' => (int) $fallbackServiceId]]);
        } else {
            return [];
        }

        return $lines
            ->map(fn ($line) => is_array($line) ? $line : ['service_id' => (int) $line])
            ->unique(fn ($line) => (int) $line['service_id'])
            ->values()
            ->all();
    }

    /** @param list<array<string, mixed>> $lines */
    private function persistContractServices(Contract $contract, array $lines): void
    {
        foreach ($lines as $line) {
            $employeeIds = collect($line['employee_ids'] ?? [])
                ->map(fn ($id) => (int) $id)->unique()->values()->all();
            unset($line['employee_ids']);

            /** @var ContractService $row */
            $row = $contract->contractServices()->create($line);

            if ($employeeIds === []) {
                continue;
            }

            $percentages = Employee::query()
                ->with(['commissionServices' => fn ($query) => $query->whereKey($row->service_id)])
                ->whereIn('id', $employeeIds)
                ->get()
                ->mapWithKeys(function (Employee $employee) use ($row) {
                    $capability = $employee->commissionServices->firstWhere('id', $row->service_id);

                    return [$employee->id => ['percentage' => $capability?->pivot->percentage]];
                })->all();

            $row->deliveryEmployees()->sync($percentages);
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
     * employment ⇒ employee, customer ⇒ applicant, company ⇒ company.
     *
     * @return array{context: array, snapshot: array, foreign_keys: array}
     */
    private function resolveContextEntity(?string $slug, array $data): array
    {
        $employeeId = isset($data['employee_id']) ? (int) $data['employee_id'] : null;
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
                    'foreign_keys' => ['employee_id' => $employeeId, 'applicant_id' => null, 'company_id' => null],
                ];

            case 'customer':
                if (! $applicantId) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'An applicant is required for a customer contract.',
                    ], 422));
                }
                // The `customer` contract type keeps its slug, but the party it
                // points at is now the applicant, which absorbed Customer.
                $applicant = Applicant::findOrFail($applicantId);
                $multiIds = collect($data['service_lines'] ?? [])
                    ->map(fn ($line) => (int) ($line['service_id'] ?? 0))->filter()
                    ->merge(collect($data['service_ids'] ?? [])->map(fn ($id) => (int) $id))
                    ->merge(! empty($data['service_id']) ? [(int) $data['service_id']] : [])
                    ->unique()->values()->all();
                if (count($multiIds) !== 1) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'A customer contract must have exactly one service.',
                    ], 422));
                }
                $services = \App\Models\Service::query()->where('status', 'active')->whereIn('id', $multiIds)->get();
                if ($services->count() !== count($multiIds)) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'Only active services can be registered on a contract.',
                    ], 422));
                }
                // `customer` stays as a context alias so the `customer.*`
                // placeholder set keeps resolving (see warning.md).
                return [
                    'context' => ['applicant' => $applicant, 'customer' => $applicant, 'services' => $services],
                    'snapshot' => ['customer' => $applicant->only(['id', 'name', 'company', 'contact', 'email', 'address', 'tax_number']), 'applicant_id' => $applicant->id],
                    'foreign_keys' => ['employee_id' => null, 'applicant_id' => $applicantId, 'company_id' => null],
                ];

            case 'other':
                $otherIds = collect($data['service_lines'] ?? [])
                    ->map(fn ($line) => (int) ($line['service_id'] ?? 0))->filter()
                    ->merge(collect($data['service_ids'] ?? [])->map(fn ($id) => (int) $id))
                    ->unique()->values()->all();
                $otherServices = $otherIds !== []
                    ? \App\Models\Service::query()->where('status', 'active')->whereIn('id', $otherIds)->get()
                    : collect();

                return [
                    'context' => ['services' => $otherServices],
                    'snapshot' => [],
                    'foreign_keys' => ['employee_id' => null, 'applicant_id' => $applicantId, 'company_id' => $companyId],
                ];

            default:
                // Custom types carry no mandatory entity; clear stale links.
                return [
                    'context' => [],
                    'snapshot' => [],
                    'foreign_keys' => [
                        'employee_id' => $employeeId,
                        'applicant_id' => $applicantId,
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
        } elseif (($applicant = $contextEntity['context']['applicant'] ?? $contextEntity['context']['customer'] ?? null) !== null) {
            $label = $applicant->name;
            $fields = [
                ...$fields,
                'counterparty' => $applicant->name,
                'counterparty_company' => $applicant->company ?: null,
                'counterparty_email' => $applicant->email,
                'counterparty_contact' => $applicant->contact,
                'counterparty_address' => $applicant->address,
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
