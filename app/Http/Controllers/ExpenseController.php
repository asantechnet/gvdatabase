<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Http\Resources\ExpenseResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::query();

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

        // Ensure we're getting all records with proper ordering
        $expenses = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();

        return ExpenseResource::collection($expenses);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
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

        // Calculate total_amount before creating the expense
        $data = $request->all();
        $data['total_amount'] = $data['amount'] * $data['quantity'] * $data['rate_to_base'];

        $expense = Expense::create($data);

        // Return the created expense with 201 status code
        return response()->json(new ExpenseResource($expense), 201);
    }

    public function show(Expense $expense)
    {
        return new ExpenseResource($expense);
    }

    public function update(Request $request, Expense $expense)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
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

        $data = $request->all();

        // Recalculate total_amount if any of the relevant fields changed
        if ($request->hasAny(['amount', 'quantity', 'rate_to_base'])) {
            $data['total_amount'] = $request->input('amount', $expense->amount) *
                                  $request->input('quantity', $expense->quantity) *
                                  $request->input('rate_to_base', $expense->rate_to_base);
        }

        $expense->update($data);

        return new ExpenseResource($expense);
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return response()->noContent();
    }
}
