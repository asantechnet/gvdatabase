<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractPolicyResource;
use App\Models\ContractPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContractPolicy::query()->with(['country', 'language']);
        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return response()->json(['success' => true, 'data' => ContractPolicyResource::collection(
            $query->orderBy('type')->orderBy('label')->get()
        )]);
    }

    public function show(ContractPolicy $contractPolicy): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new ContractPolicyResource($contractPolicy)]);
    }

    public function store(Request $request): JsonResponse
    {
        $policy = ContractPolicy::create($this->validated($request));

        return response()->json(['success' => true, 'data' => new ContractPolicyResource($policy)], 201);
    }

    public function update(Request $request, ContractPolicy $contractPolicy): JsonResponse
    {
        $contractPolicy->update($this->validated($request, true));

        return response()->json(['success' => true, 'data' => new ContractPolicyResource($contractPolicy)]);
    }

    public function destroy(ContractPolicy $contractPolicy): JsonResponse
    {
        if ($contractPolicy->templates()->exists()) {
            return response()->json(['success' => false, 'code' => 'business.contract_policy_in_use'], 409);
        }
        $contractPolicy->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'label' => ($partial ? 'sometimes' : 'required').'|string|max:255',
            // A policy clause is reusable only within one of the contract
            // contexts the resolver understands. Keep API callers aligned
            // with the restricted select shown in the policy editor.
            'type' => [$partial ? 'sometimes' : 'required', 'string', Rule::in(['employment', 'customer', 'other'])],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'is_active' => ['sometimes', 'boolean'],
            'header' => 'nullable|string|max:255',
            'content' => ($partial ? 'sometimes' : 'required').'|string',
        ]);
    }
}
