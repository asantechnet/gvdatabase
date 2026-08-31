<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockResource;
use App\Models\Stock;
use App\Models\Employee;
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
        $query = Stock::query()->with(['allocations.employee', 'office']);

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
            'allocations' => ['sometimes', 'array'],
            'allocations.*.employee_id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'allocations.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $stock = DB::transaction(function () use ($validated) {
            $allocations = $validated['allocations'] ?? [];
            unset($validated['allocations']);
            $stock = Stock::create($validated);
            $this->syncAllocations($stock, $allocations);

            return $stock;
        });

        return new StockResource($stock->load(['allocations.employee', 'office']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Stock $stock)
    {
        return new StockResource($stock->load(['allocations.employee', 'office']));
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
            'allocations' => ['sometimes', 'array'],
            'allocations.*.employee_id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'allocations.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($stock, $validated) {
            $locked = Stock::query()->lockForUpdate()->findOrFail($stock->id);
            $allocations = $validated['allocations'] ?? null;
            unset($validated['allocations']);
            $locked->update($validated);
            if ($allocations !== null) {
                $this->syncAllocations($locked, $allocations);
            } elseif ($locked->allocations()->sum('quantity') > $locked->quantity) {
                abort(422, 'business.stock_allocation_exceeds_quantity');
            }
        });

        return new StockResource($stock->fresh()->load(['allocations.employee', 'office']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Stock $stock)
    {
        $stock->delete();

        return response()->noContent();
    }

    /** @param array<int, array{employee_id: int, quantity: int}> $allocations */
    private function syncAllocations(Stock $stock, array $allocations): void
    {
        $total = array_sum(array_map(fn (array $allocation) => (int) $allocation['quantity'], $allocations));
        abort_if($total > $stock->quantity, 422, 'business.stock_allocation_exceeds_quantity');

        $employeeIds = collect($allocations)->pluck('employee_id')->map(fn ($id) => (int) $id)->all();
        abort_unless(Employee::selectable()->whereIn('id', $employeeIds)->count() === count($employeeIds), 422, 'business.employee_not_selectable');
        $stock->allocations()->delete();
        $stock->allocations()->createMany($allocations);
    }
}
