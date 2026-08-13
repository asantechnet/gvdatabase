<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\ContractTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Contract::with('template');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('contract_number', 'like', "%{$search}%")
                    ->orWhere('counterparty', 'like', "%{$search}%");
            });
        }

        if ($request->has('only-cancelled') && $request->input('only-cancelled') === 'true') {
            $query->where('is_cancelled', true);
        }

        $contracts = $query->get();
        return response()->json([
            'success' => true,
            'data' => ContractResource::collection($contracts)
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:contract_templates,id',
            'status' => 'required|string|in:Active,Expired,Pending',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'counterparty' => 'required|string|max:255',
            'counterparty_company' => 'nullable|string|max:255',
            'counterparty_email' => 'nullable|email|max:255',
            'counterparty_contact' => 'nullable|string|max:255',
            'counterparty_address' => 'nullable|string|max:2000',
            'is_cancelled' => 'sometimes|boolean',
        ]);

        $validator->validate();

        $contract = Contract::create($validator->validated());
        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract->load('template'))
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $contract = Contract::with('template')->find($id);
        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contract not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract)
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $contract = Contract::find($id);
        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contract not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'template_id' => 'sometimes|exists:contract_templates,id',
            'status' => 'sometimes|string|in:Active,Expired,Pending',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'counterparty' => 'sometimes|string|max:255',
            'counterparty_company' => 'nullable|string|max:255',
            'counterparty_email' => 'nullable|email|max:255',
            'counterparty_contact' => 'nullable|string|max:255',
            'counterparty_address' => 'nullable|string|max:2000',
            'is_cancelled' => 'sometimes|boolean',
        ]);

        $validator->validate();

        $contract->update($validator->validated());
        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract->load('template'))
        ]);
    }

    public function cancel($id): JsonResponse
    {
        $contract = Contract::find($id);
        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contract not found'
            ], 404);
        }

        $contract->update(['is_cancelled' => true, 'cancelled_at' => now('UTC')]);
        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract->load('template'))
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $contract = Contract::find($id);
        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contract not found'
            ], 404);
        }

        $contract->delete();
        return response()->json([
            'success' => true,
            'message' => 'Contract deleted successfully'
        ]);
    }

    public function templates(): JsonResponse
    {
        $templates = ContractTemplate::all();
        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }
}
