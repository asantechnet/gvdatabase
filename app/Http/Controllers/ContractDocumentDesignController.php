<?php

namespace App\Http\Controllers;

use App\Models\ContractDocumentDesign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractDocumentDesignController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['contract_type_id' => ['nullable', 'exists:contract_types,id'], 'service_id' => ['nullable', 'exists:services,id']]);
        return response()->json(['success' => true, 'data' => ContractDocumentDesign::query()->with(['contractType', 'service'])
            ->when($request->filled('contract_type_id'), fn ($q) => $q->where('contract_type_id', $request->integer('contract_type_id')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))->get()->map(fn (ContractDocumentDesign $design) => $this->present($design))]);
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
        $design->fill($data)->save();
        return response()->json(['success' => true, 'data' => $this->present($design)], 201);
    }

    public function update(Request $request, ContractDocumentDesign $contractDocumentDesign)
    {
        $data = $this->validated($request, true);
        $contractDocumentDesign->update($data);
        return response()->json(['success' => true, 'data' => $this->present($contractDocumentDesign->fresh())]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];
        $data = $request->validate([
            'contract_type_id' => [...$required, 'integer', 'exists:contract_types,id'], 'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'letterhead' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'], 'cover_design' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_letterhead' => ['sometimes', 'boolean'], 'remove_cover_design' => ['sometimes', 'boolean'],
            'safe_x' => ['sometimes', 'numeric', 'between:0,95'], 'safe_y' => ['sometimes', 'numeric', 'between:0,95'], 'safe_width' => ['sometimes', 'numeric', 'between:5,100'], 'safe_height' => ['sometimes', 'numeric', 'between:5,100'],
        ]);
        foreach (['letterhead', 'cover_design'] as $field) {
            if ($request->hasFile($field)) $data[$field] = $request->file($field)->storeAs('contract-designs', Str::uuid().'.'.$request->file($field)->extension(), 'public');
        }
        if ($request->boolean('remove_letterhead')) $data['letterhead'] = null;
        if ($request->boolean('remove_cover_design')) $data['cover_design'] = null;
        unset($data['remove_letterhead'], $data['remove_cover_design']);
        return $data;
    }

    private function present(ContractDocumentDesign $design): array
    {
        return [...$design->toArray(),
            'letterhead_url' => $design->letterhead ? Storage::disk('public')->url($design->letterhead) : null,
            'cover_design_url' => $design->cover_design ? Storage::disk('public')->url($design->cover_design) : null,
        ];
    }
}
