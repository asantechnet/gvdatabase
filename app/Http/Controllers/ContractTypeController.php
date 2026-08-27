<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractTypeResource;
use App\Models\ContractType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:contract_types,name',
        ]);

        $validator->validate();

        $type = ContractType::create([
            'name' => $validator->validated()['name'],
            'slug' => ContractType::uniqueSlug($validator->validated()['name']),
            'is_system' => false,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => new ContractTypeResource($type),
        ], 201);
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
