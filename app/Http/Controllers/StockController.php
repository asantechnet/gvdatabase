<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockResource;
use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Stock::query()->with('staff');

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

        if ($request->has('office')) {
            $query->where('office', $request->input('office'));
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
            'model' => 'required|string|max:255',
            'office' => 'required|string|max:255',
            'staff_id' => 'nullable|exists:employees,id',
        ]);

        $stock = Stock::create($validated);

        return new StockResource($stock->load('staff'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Stock $stock)
    {
        return new StockResource($stock->load('staff'));
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
            'model' => 'sometimes|string|max:255',
            'office' => 'sometimes|string|max:255',
            'staff_id' => 'nullable|exists:employees,id',
        ]);

        $stock->update($validated);

        return new StockResource($stock->load('staff'));
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
