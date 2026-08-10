<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\Invoice;
use App\Http\Resources\IncomeResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $query = Income::with('invoice');

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('currency') && $request->currency) {
            $query->where('currency', $request->currency);
        }

        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('description', 'like', '%'.$request->search.'%')
                  ->orWhere('type', 'like', '%'.$request->search.'%');
            });
        }

        $incomes = $query->latest()->get();

        return IncomeResource::collection($incomes);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'type' => 'required|string|max:255',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'currency' => 'required|string|max:3',
            'rate_to_base' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        if (!empty($data['invoice_id'])) {
            $data['customer_id'] = Invoice::findOrFail($data['invoice_id'])->customer_id;
        }

        $income = Income::create($data);

        return new IncomeResource($income);
    }

    public function show(Income $income)
    {
        return new IncomeResource($income);
    }

    public function update(Request $request, Income $income)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'type' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'amount' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:1',
            'currency' => 'sometimes|string|max:3',
            'rate_to_base' => 'sometimes|numeric|min:0',
            'date' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        if (!empty($data['invoice_id'])) {
            $data['customer_id'] = Invoice::findOrFail($data['invoice_id'])->customer_id;
        }

        $income->update($data);

        return new IncomeResource($income);
    }

    public function destroy(Income $income)
    {
        $income->delete();

        return response()->noContent();
    }
}
