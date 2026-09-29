<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractTemplateResource;
use App\Http\Resources\PackageResource;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Services\Contracts\ContentSanitizer;
use App\Services\Contracts\PlaceholderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContractTemplateController extends Controller
{
    public function __construct(private readonly ContentSanitizer $sanitizer) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'contract_type_id' => ['sometimes', 'integer', 'exists:contract_types,id'],
            'include_generic' => ['sometimes', 'boolean'],
        ]);

        $templates = ContractTemplate::with(['policies', 'contractType', 'position', 'service', 'services.variants', 'package', 'versions.language', 'versions.country'])
            ->when($request->filled('contract_type_id'), fn ($query) => $query->where('contract_type_id', $request->integer('contract_type_id')))
            // Print/create-page picker: when services are selected, show only
            // templates linked to those services (plus generic templates with
            // no linked service unless explicitly excluded).
            ->when($request->filled('service_ids'), function ($query) use ($request) {
                $ids = collect($request->input('service_ids'))->map(fn ($id) => (int) $id)->all();
                $query->where(function ($q) use ($ids, $request) {
                    $q->whereHas('services', fn ($services) => $services->whereIn('services.id', $ids))->orWhereIn('service_id', $ids);
                    if ($request->boolean('include_generic', true)) {
                        $q->orWhereNull('service_id');
                    }
                });
            })
            ->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => ContractTemplateResource::collection($templates),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $template = ContractTemplate::create($this->payload($data));
        $template->policies()->sync($this->policySyncData($data['policy_ids'] ?? []));
        $this->syncBinding($template, $data);

        return response()->json([
            'success' => true,
            'data' => new ContractTemplateResource($template->load(['policies', 'contractType', 'position', 'services.variants', 'package'])),
        ], 201);
    }

    public function show(ContractTemplate $contractTemplate): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ContractTemplateResource($contractTemplate->load(['policies', 'contractType', 'position', 'services.variants', 'package'])),
        ]);
    }

    public function update(Request $request, ContractTemplate $contractTemplate): JsonResponse
    {
        $data = $this->validated($request, partial: true, existing: $contractTemplate);

        // Content belongs to a country/language version.  Keep accepting the
        // legacy field for old clients, but never let it overwrite a template
        // that has entered the versioned workflow.
        if ($contractTemplate->versions()->exists()) {
            unset($data['content']);
        }

        $contractTemplate->update($this->payload($data, $contractTemplate));

        if (array_key_exists('policy_ids', $data)) {
            $contractTemplate->policies()->sync($this->policySyncData($data['policy_ids'] ?? []));
        }
        if ($request->hasAny(['service_id', 'service_ids', 'service_scopes', 'package_id', 'contract_type_id'])) $this->syncBinding($contractTemplate, $data);

        return response()->json([
            'success' => true,
            'data' => new ContractTemplateResource($contractTemplate->fresh(['policies', 'contractType', 'position', 'services.variants', 'package'])),
        ]);
    }

    public function destroy(ContractTemplate $contractTemplate): JsonResponse
    {
        if ($contractTemplate->contracts()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This template is used by existing contracts and cannot be deleted.',
                'code' => 'business.contract_template_in_use',
            ], 409);
        }

        $contractTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contract template deleted successfully',
        ]);
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, bool $partial = false, ?ContractTemplate $existing = null): array
    {
        $presenceRules = $partial ? ['sometimes', 'required'] : ['required'];

        $validator = Validator::make($request->all(), [
            'name' => [...$presenceRules, 'string', 'max:255'],
            'contract_type_id' => [...$presenceRules, 'integer', 'exists:contract_types,id'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'service_ids' => ['nullable', 'array', 'min:1'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'service_scopes' => ['nullable', 'array', 'min:1'],
            'service_scopes.*.service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'service_scopes.*.service_variant_id' => ['nullable', 'integer', 'exists:service_variants,id'],
            // A customer template binds to exactly one catalogue subject: a
            // single service (single-select, never multi-selected here) or a
            // single package from the educational packages catalogue.
            'package_id' => ['nullable', 'integer', Rule::exists('packages', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'cover_text' => ['nullable', 'string'],
            'content' => [$partial ? 'sometimes' : 'nullable', 'string'],
            'policy_ids' => ['nullable', 'array'],
            'policy_ids.*' => ['integer', 'exists:contract_policies,id'],
        ]);

        $validator->after(function ($validator) use ($request, $partial, $existing) {
            $type = $request->filled('contract_type_id')
                ? ContractType::find($request->input('contract_type_id'))
                : $existing?->contractType;
            if (! $type) {
                return;
            }

            // Customer templates are always bound to exactly one catalogue
            // subject: a single service or a single package. Employment
            // templates bind to exactly one position. The invariant is
            // enforced when creating a template or when a partial update
            // actually touches the binding fields — never for unrelated
            // edits on legacy templates.
            $touchesBinding = ! $partial || $request->hasAny(['contract_type_id', 'service_id', 'service_ids', 'service_scopes', 'package_id', 'position_id']);
            // The editor sends the subject pair explicitly: when a package is
            // selected, `service_id` arrives null (and the service scope
            // lists are omitted); when a service is selected, `package_id`
            // arrives null. A request that keeps the binding untouched
            // falls back to the template's stored binding.
            $packageProvided = $request->has('package_id');
            $serviceProvided = $request->hasAny(['service_id', 'service_ids', 'service_scopes']);
            $packageId = $packageProvided ? $request->input('package_id') : $existing?->package_id;
            if ($serviceProvided) {
                $scopes = $request->input('service_scopes') ?? collect($request->input('service_ids', []))->map(fn ($id) => ['service_id' => $id])->all();
                if ($scopes === [] && $request->filled('service_id')) $scopes = [['service_id' => $request->input('service_id')]];
            } elseif ($packageProvided) {
                $scopes = [];
            } else {
                $scopes = $existing ? $existing->services()->get()->map(fn ($service) => ['service_id' => $service->id, 'service_variant_id' => $service->pivot->service_variant_id])->all() : [];
                if ($scopes === [] && $existing?->service_id) $scopes = [['service_id' => $existing->service_id]];
            }
            if ($touchesBinding && $type->slug === 'customer') {
                if ($scopes !== [] && $packageId) {
                    $validator->errors()->add('service_id', 'A customer template can be for a service or a package, not both.');
                } elseif ($scopes === [] && ! $packageId) {
                    $validator->errors()->add('service_id', 'Choose a service or a package for a customer template.');
                }
            }
            foreach ($scopes as $scope) if (!empty($scope['service_variant_id']) && (int) \App\Models\ServiceVariant::whereKey($scope['service_variant_id'])->value('service_id') !== (int) $scope['service_id']) $validator->errors()->add('service_scopes', 'Every selected variant must belong to its selected service.');

            $positionId = $request->input('position_id', $existing?->position_id);
            if ($touchesBinding && $type->slug === 'employment' && ! $positionId) {
                $validator->errors()->add('position_id', 'A position is required for an employment template.');
            }

            if ($request->has('policy_ids')) {
                $existingPolicyIds = $existing?->policies()->pluck('contract_policies.id')->all() ?? [];
                $invalidIds = \App\Models\ContractPolicy::query()
                    ->whereIn('id', $request->input('policy_ids', []))
                    ->where(function ($query) use ($type, $existingPolicyIds) {
                        $query->where('type', '!=', $type->slug)
                            // Inactive clauses cannot be added to a new template,
                            // but an already-issued template may retain one for a
                            // stable document history.
                            ->orWhere(fn ($inactive) => $inactive->where('is_active', false)->whereNotIn('id', $existingPolicyIds));
                    })
                    ->pluck('id');

                if ($invalidIds->isNotEmpty()) {
                    $validator->errors()->add('policy_ids', 'Only active policies matching the selected contract type can be added.');
                }
            }
        });

        $validator->validate();

        return $validator->validated();
    }

    private function policySyncData(array $ids): array
    {
        return collect($ids)
            ->values()
            ->mapWithKeys(fn ($id, $index) => [(int) $id => ['sort_order' => (int) $index]])
            ->all();
    }

    private function payload(array $data, ?ContractTemplate $existing = null): array
    {
        $type = ContractType::find($data['contract_type_id'] ?? null)
            ?? $existing?->contractType;

        // Position only makes sense for employment templates; never leave
        // employment-only data silently attached to other template types.
        if ($type?->slug !== 'employment') {
            $data['position_id'] = null;
        }

        if (array_key_exists('content', $data)) {
            $data['content'] = $this->sanitizeContent((string) $data['content'], $type?->slug);
        } elseif (! $existing) {
            // The legacy column remains non-null for backwards compatibility;
            // newly created templates receive their real document only through
            // their initial country/language version.
            $data['content'] = '';
        }

        // Keep the legacy display column populated for old consumers.
        if ($type && array_key_exists('contract_type_id', $data)) {
            $data['type'] = $type->name;
        }

        // The catalogue subject is exclusive: a package-bound template never
        // keeps a service binding, and vice versa.
        if (array_key_exists('package_id', $data) && $data['package_id'] !== null) {
            $data['service_id'] = null;
        }

        unset($data['policy_ids'], $data['service_ids'], $data['service_scopes']);

        return $data;
    }

    /**
     * Sync the template's catalogue binding after create/update. A customer
     * template owns exactly one binding: either one package (no service
     * scope) or one service (single scope row, optional variant).
     */
    private function syncBinding(ContractTemplate $template, array $data): void
    {
        if ($template->contractType?->slug !== 'customer') { $template->services()->detach(); $template->updateQuietly(['service_id' => null, 'package_id' => null]); return; }
        // A package-bound template owns no service scope.
        if ($template->package_id) { $template->services()->detach(); $template->updateQuietly(['service_id' => null]); return; }
        $scopes = $data['service_scopes'] ?? collect($data['service_ids'] ?? [])->map(fn ($id) => ['service_id' => $id])->all();
        if ($scopes === [] && !empty($data['service_id'])) $scopes = [['service_id' => $data['service_id']]];
        if ($scopes === []) {
            // A partial request that does not mention service scope must leave
            // it untouched; an explicit empty value is already rejected by
            // validation for customer templates.
            return;
        }
        $template->services()->detach();
        foreach (collect($scopes)->unique(fn ($scope) => ($scope['service_id'] ?? '').':'.($scope['service_variant_id'] ?? '')) as $scope) $template->services()->attach((int) $scope['service_id'], ['service_variant_id' => $scope['service_variant_id'] ?? null]);
        $template->updateQuietly(['service_id' => $scopes[0]['service_id']]);
    }

    /**
     * Validate placeholders against the contract type before saving a
     * template so unknown or out-of-context placeholders are rejected early.
     */
    private function sanitizeContent(string $content, ?string $slug): string
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_.]+)\s*}}/', $content, $matches);
        $allowed = PlaceholderRegistry::keysForSlug($slug);
        $unknown = collect($matches[1] ?? [])
            ->map(fn ($key) => strtolower($key))
            ->unique()
            ->reject(fn ($key) => in_array($key, $allowed, true));

        if ($unknown->isNotEmpty()) {
            abort(response()->json([
                'success' => false,
                'message' => 'The template contains placeholders that are not valid for this contract type: '
                    .$unknown->implode(', '),
                'code' => 'business.unresolved_placeholders',
                'placeholders' => $unknown->values()->all(),
            ], 422));
        }

        return $this->sanitizer->clean($content);
    }
}
