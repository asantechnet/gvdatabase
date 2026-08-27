<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockResource;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Stock::query()->with(['staff', 'office']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                  ->orWhere('item', 'like', "%{$search}%");
            });
        }

        if ($request->has('model')) {
            $query->where('model', $request->input('model'));
        }

        if ($request->filled('office_id')) {
            $query->where('office_id', $request->integer('office_id'));
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->input('condition'));
        }

        $stocks = $query->get();

        return StockResource::collection($stocks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'serial_number' => 'required|string|max:255|unique:stocks',
            'item' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'model' => 'required|string|max:255',
            'condition' => ['required', Rule::in(Stock::conditions())],
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'staff_id' => 'nullable|exists:employees,id',
        ]);

        $stock = DB::transaction(fn () => Stock::create($validated));

        return new StockResource($stock->load(['staff', 'office']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Stock $stock)
    {
        return new StockResource($stock->load(['staff', 'office']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Stock $stock)
    {
        $validated = $request->validate([
            'serial_number' => 'sometimes|string|max:255|unique:stocks,serial_number,'.$stock->id,
            'item' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'model' => 'sometimes|string|max:255',
            'condition' => ['sometimes', Rule::in(Stock::conditions())],
            'office_id' => ['sometimes', 'required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'staff_id' => 'nullable|exists:employees,id',
        ]);

        DB::transaction(fn () => $stock->update($validated));

        return new StockResource($stock->load(['staff', 'office']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Stock $stock)
    {
        $stock->delete();

        return response()->noContent();
    }
}
