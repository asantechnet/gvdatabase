<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    use PaginatesLists;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Asset::query()->with(['allocations.employee', 'office']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
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

        $query->orderByDesc('created_at')->orderByDesc('id');

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true] + $this->paginatedPayload($request, $query, fn ($rows) => AssetResource::collection($rows)->resolve()));
        }

        return AssetResource::collection($query->get());
    }

    /**
     * The distinct models already in use, for the inventory filter. The list is
     * small and independent of paging, so it stays its own request.
     */
    public function models(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Asset::query()->whereNotNull('model')->distinct()->orderBy('model')->pluck('model')->all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'serial_number' => 'required|string|max:255|unique:assets',
            'item' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'model' => 'required|string|max:255',
            'condition' => ['required', Rule::in(Asset::conditions())],
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'allocations' => ['sometimes', 'array'],
            'allocations.*.employee_id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'allocations.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $asset = DB::transaction(function () use ($validated) {
            $allocations = $validated['allocations'] ?? [];
            unset($validated['allocations']);
            $asset = Asset::create($validated);
            $this->syncAllocations($asset, $allocations);

            return $asset;
        });

        return new AssetResource($asset->load(['allocations.employee', 'office']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Asset $asset)
    {
        return new AssetResource($asset->load(['allocations.employee', 'office']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'serial_number' => 'sometimes|string|max:255|unique:assets,serial_number,'.$asset->id,
            'item' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'model' => 'sometimes|string|max:255',
            'condition' => ['sometimes', Rule::in(Asset::conditions())],
            'office_id' => ['sometimes', 'required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'allocations' => ['sometimes', 'array'],
            'allocations.*.employee_id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'allocations.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($asset, $validated) {
            $locked = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            $allocations = $validated['allocations'] ?? null;
            unset($validated['allocations']);
            $locked->update($validated);
            if ($allocations !== null) {
                $this->syncAllocations($locked, $allocations);
            } elseif ($locked->allocations()->sum('quantity') > $locked->quantity) {
                abort(422, 'business.asset_allocation_exceeds_quantity');
            }
        });

        return new AssetResource($asset->fresh()->load(['allocations.employee', 'office']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Asset $asset)
    {
        abort_if($asset->allocations()->exists(), 422, 'business.asset_still_allocated');
        $asset->delete();

        return response()->noContent();
    }

    /** @param array<int, array{employee_id: int, quantity: int}> $allocations */
    private function syncAllocations(Asset $asset, array $allocations): void
    {
        $total = array_sum(array_map(fn (array $allocation) => (int) $allocation['quantity'], $allocations));
        abort_if($total > $asset->quantity, 422, 'business.asset_allocation_exceeds_quantity');

        $employeeIds = collect($allocations)->pluck('employee_id')->map(fn ($id) => (int) $id)->all();
        abort_unless(Employee::selectable()->whereIn('id', $employeeIds)->count() === count($employeeIds), 422, 'business.employee_not_selectable');
        $asset->allocations()->delete();
        $asset->allocations()->createMany($allocations);
    }
}
