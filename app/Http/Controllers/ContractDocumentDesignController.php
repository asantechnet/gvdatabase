<?php

namespace App\Http\Controllers;

use App\Models\ContractDocumentDesign;
use App\Services\ContractDocumentDesignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Contract artwork scoped by contract type — and, for customer contracts only,
 * by service. Employment / other / custom types carry exactly one design that
 * applies to every contract of that type (see contract-template-sanifier.md).
 */
class ContractDocumentDesignController extends Controller
{
    public function __construct(private readonly ContractDocumentDesignService $documentDesigns) {}

    public function index(Request $request)
    {
        $request->validate(['contract_type_id' => ['nullable', 'exists:contract_types,id'], 'service_id' => ['nullable', 'exists:services,id']]);

        $designs = ContractDocumentDesign::query()->with(['contractType', 'service'])
            ->when($request->filled('contract_type_id'), fn ($q) => $q->where('contract_type_id', $request->integer('contract_type_id')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            ->orderBy('contract_type_id')
            ->orderByRaw('service_id IS NOT NULL')
            ->get();

        return response()->json(['success' => true, 'data' => $designs->map(fn (ContractDocumentDesign $design) => $this->present($design))]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        // SQL unique constraints treat NULL values as distinct, so an
        // explicit lookup is required for the type-wide (no service) design.
        $design = ContractDocumentDesign::query()->firstOrNew([
            'contract_type_id' => $data['contract_type_id'],
            'service_id' => $data['service_id'] ?? null,
        ]);
        $superseded = $this->supersededArtwork($design, $data);

        $design->fill($data)->save();
        $this->deleteArtwork($superseded);

        return response()->json(['success' => true, 'data' => $this->present($design)], 201);
    }

    public function update(Request $request, ContractDocumentDesign $contractDocumentDesign)
    {
        $data = $this->validated($request, $contractDocumentDesign);
        $superseded = $this->supersededArtwork($contractDocumentDesign, $data);

        $contractDocumentDesign->update($data);
        $this->deleteArtwork($superseded);

        return response()->json(['success' => true, 'data' => $this->present($contractDocumentDesign->fresh())]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ContractDocumentDesign $design = null): array
    {
        $data = $request->validate([
            'contract_type_id' => [($design ? 'sometimes' : 'required'), 'integer', 'exists:contract_types,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'letterhead' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'cover_design' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_letterhead' => ['sometimes', 'boolean'],
            'remove_cover_design' => ['sometimes', 'boolean'],
            'safe_x' => ['sometimes', 'numeric', 'between:0,95'],
            'safe_y' => ['sometimes', 'numeric', 'between:0,95'],
            'safe_width' => ['sometimes', 'numeric', 'between:5,100'],
            'safe_height' => ['sometimes', 'numeric', 'between:5,100'],
        ]);

        $typeId = (int) ($data['contract_type_id'] ?? $design?->contract_type_id);
        $serviceId = array_key_exists('service_id', $data) ? $data['service_id'] : $design?->service_id;
        $serviceScoped = $this->documentDesigns->supportsServiceScope($typeId);

        // Service scoping exists for customer contracts only: employment and
        // other contracts share one cover and letterhead for the whole type.
        if ($serviceId && ! $serviceScoped) {
            throw ValidationException::withMessages([
                'service_id' => ['Only customer contracts can scope their artwork to a single service.'],
            ]);
        }

        if (! $serviceScoped) {
            $data['service_id'] = null;
        }

        foreach (['letterhead', 'cover_design'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->storeAs('contract-designs', Str::uuid().'.'.$request->file($field)->extension(), 'public');
            }
        }

        if ($request->boolean('remove_letterhead')) {
            $data['letterhead'] = null;
        }

        if ($request->boolean('remove_cover_design')) {
            $data['cover_design'] = null;
        }

        unset($data['remove_letterhead'], $data['remove_cover_design']);

        return $data;
    }

    /**
     * Paths the incoming payload replaces or clears and may therefore be
     * deleted from disk. Every upload gets a unique file name, so a superseded
     * path is never referenced by another design row.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function supersededArtwork(ContractDocumentDesign $design, array $data): array
    {
        $paths = [];

        foreach (['letterhead', 'cover_design'] as $field) {
            if (! array_key_exists($field, $data) || ! $design->{$field}) {
                continue;
            }

            if ($design->{$field} !== $data[$field]) {
                $paths[] = $design->{$field};
            }
        }

        return $paths;
    }

    /** @param list<string> $paths */
    private function deleteArtwork(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('public')->delete(array_values(array_unique($paths)));
        }
    }

    private function present(ContractDocumentDesign $design): array
    {
        return [...$design->toArray(),
            'letterhead_url' => $design->letterhead ? Storage::disk('public')->url($design->letterhead) : null,
            'cover_design_url' => $design->cover_design ? Storage::disk('public')->url($design->cover_design) : null,
        ];
    }
}
