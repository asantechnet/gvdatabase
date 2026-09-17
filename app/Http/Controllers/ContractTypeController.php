<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractTypeResource;
use App\Models\ContractType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContractType::query()
            ->withCount(['templates', 'contracts'])
            ->orderBy('is_system', 'desc')
            ->orderBy('name');

        if ($request->boolean('include_inactive') !== true) {
            $query->where('is_active', true)->whereNull('deleted_at');
        }

        return response()->json([
            'success' => true,
            'data' => ContractTypeResource::collection($query->get()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:contract_types,name'], 'description' => ['nullable', 'string', 'max:5000'], 'is_active' => ['sometimes', 'boolean']]);
        $type = ContractType::create($data);
        return response()->json(['success' => true, 'data' => new ContractTypeResource($type)], 201);
    }

    public function update(Request $request, ContractType $contractType): JsonResponse
    {
        abort_if($contractType->isSystemType() && $request->has('name'), 422, 'System type names are protected.');
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:255', 'unique:contract_types,name,'.$contractType->id], 'description' => ['nullable', 'string', 'max:5000'], 'is_active' => ['sometimes', 'boolean']]);
        $contractType->update($data);
        return response()->json(['success' => true, 'data' => new ContractTypeResource($contractType->fresh())]);
    }

    public function show(ContractType $contractType): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ContractTypeResource($contractType->loadCount(['templates', 'contracts'])),
        ]);
    }

    public function destroy(ContractType $contractType): JsonResponse
    {
        // System types are protected application vocabulary.
        if ($contractType->isSystemType()) {
            return response()->json([
                'success' => false,
                'message' => 'System contract types cannot be deleted.',
                'code' => 'business.contract_type_protected',
            ], 409);
        }

        $templates = $contractType->templates()->count();
        $contracts = $contractType->contracts()->count();

        // Referenced types are deactivated instead of deleted so historical
        // contracts keep a valid type association.
        if ($templates > 0 || $contracts > 0) {
            $contractType->update(['is_active' => false]);

            return response()->json([
                'success' => false,
                'message' => "This contract type is used by {$templates} template(s) and {$contracts} contract(s). It has been deactivated instead of deleted.",
                'code' => 'business.contract_type_in_use',
                'data' => new ContractTypeResource($contractType->refresh()),
            ], 409);
        }

        $contractType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contract type deleted successfully.',
        ]);
    }
}
