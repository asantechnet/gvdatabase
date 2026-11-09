<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractPolicyResource;
use App\Models\ContractPolicy;
use App\Support\BuiltInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContractPolicy::query()->with(['country', 'language', 'position']);
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
        $contractPolicy->update($this->validated($request, true, $contractPolicy));

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

    private function validated(Request $request, bool $partial = false, ?ContractPolicy $current = null): array
    {
        $data = $request->validate([
            'label' => ($partial ? 'sometimes' : 'required').'|string|max:255',
            // A policy clause is reusable only within one of the contract
            // contexts the resolver understands. Keep API callers aligned
            // with the restricted select shown in the policy editor.
            'type' => [$partial ? 'sometimes' : 'required', 'string', Rule::exists('contract_types', 'slug')->whereNull('deleted_at')->where('is_active', true)],
            'service' => ['nullable', Rule::in(BuiltInService::enabled())],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->whereNull('deleted_at')],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'is_active' => ['sometimes', 'boolean'],
            'header' => 'nullable|string|max:255',
            'content' => ($partial ? 'sometimes' : 'required').'|string',
        ]);
        $type = $data['type'] ?? $current?->type;
        $service = array_key_exists('service', $data) ? $data['service'] : $current?->service;
        $position = array_key_exists('position_id', $data) ? $data['position_id'] : $current?->position_id;
        if ($service && $type !== 'customer') {
            throw ValidationException::withMessages(['service' => 'Service clauses must use the applicant contract context.']);
        }
        if ($position && $type !== 'employment') {
            throw ValidationException::withMessages(['position_id' => 'Position clauses must use the employment contract context.']);
        }

        return $data;
    }
}
