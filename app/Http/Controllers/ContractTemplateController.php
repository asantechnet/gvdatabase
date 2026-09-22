<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractTemplateResource;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Services\Contracts\ContentSanitizer;
use App\Services\Contracts\PlaceholderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContractTemplateController extends Controller
{
    public function __construct(private readonly ContentSanitizer $sanitizer) {}

    public function index(): JsonResponse
    {
        $templates = ContractTemplate::with(['policies', 'contractType', 'position', 'versions.language', 'versions.country'])->latest('id')->get();

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

        return response()->json([
            'success' => true,
            'data' => new ContractTemplateResource($template->load(['policies', 'contractType', 'position'])),
        ], 201);
    }

    public function show(ContractTemplate $contractTemplate): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ContractTemplateResource($contractTemplate->load(['policies', 'contractType', 'position'])),
        ]);
    }

    public function update(Request $request, ContractTemplate $contractTemplate): JsonResponse
    {
        $data = $this->validated($request, partial: true, existing: $contractTemplate);

        $contractTemplate->update($this->payload($data, $contractTemplate));

        if (array_key_exists('policy_ids', $data)) {
            $contractTemplate->policies()->sync($this->policySyncData($data['policy_ids'] ?? []));
        }

        return response()->json([
            'success' => true,
            'data' => new ContractTemplateResource($contractTemplate->fresh(['policies', 'contractType', 'position'])),
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
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'cover_text' => ['nullable', 'string'],
            'content' => [...$presenceRules, 'string'],
            'policy_ids' => ['nullable', 'array'],
            'policy_ids.*' => ['integer', 'exists:contract_policies,id'],
        ]);

        $validator->after(function ($validator) use ($request, $partial, $existing) {
            $typeId = $request->input('contract_type_id');
            if ($partial && ! $request->filled('contract_type_id')) {
                return;
            }

            $type = ContractType::find($typeId);
            if (! $type) {
                return;
            }

            if ($type->slug === 'employment' && ! $request->filled('position_id')) {
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
        }

        // Keep the legacy display column populated for old consumers.
        if ($type && array_key_exists('contract_type_id', $data)) {
            $data['type'] = $type->name;
        }

        unset($data['policy_ids']);

        return $data;
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
