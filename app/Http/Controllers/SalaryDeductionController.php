<?php

namespace App\Http\Controllers;

use App\Http\Resources\SalaryDeductionResource;
use App\Models\Salary;
use App\Models\SalaryDeduction;
use App\Services\SalaryExpenseSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryDeductionController extends Controller
{
    public function __construct(private readonly SalaryExpenseSynchronizer $expenses) {}

    public function index(Salary $salary): AnonymousResourceCollection
    {
        return SalaryDeductionResource::collection($salary->deductions()->orderBy('sort_order')->get());
    }

    public function store(Request $request, Salary $salary): SalaryDeductionResource
    {
        $attributes = $this->validated($request);
        $deduction = DB::transaction(function () use ($salary, $attributes) {
            $salary = Salary::query()->lockForUpdate()->findOrFail($salary->id);
            $this->ensureWithinPayable($salary, (float) $attributes['amount']);
            $deduction = $salary->deductions()->create($attributes);
            $this->expenses->sync($salary->fresh());

            return $deduction;
        });

        return new SalaryDeductionResource($deduction);
    }

    public function update(Request $request, Salary $salary, SalaryDeduction $deduction): SalaryDeductionResource
    {
        abort_unless((int) $deduction->salary_id === (int) $salary->id, 404);
        $attributes = $this->validated($request);
        DB::transaction(function () use ($salary, $deduction, $attributes): void {
            $salary = Salary::query()->lockForUpdate()->findOrFail($salary->id);
            $deduction = SalaryDeduction::query()->where('salary_id', $salary->id)->lockForUpdate()->findOrFail($deduction->id);
            $this->ensureWithinPayable($salary, (float) $attributes['amount'], $deduction->id);
            $deduction->update($attributes);
            $this->expenses->sync($salary->fresh());
        });

        return new SalaryDeductionResource($deduction->fresh());
    }

    public function destroy(Salary $salary, SalaryDeduction $deduction): JsonResponse
    {
        abort_unless($deduction->salary_id === $salary->id, 404);
        DB::transaction(function () use ($salary, $deduction): void {
            $salary = Salary::query()->lockForUpdate()->findOrFail($salary->id);
            SalaryDeduction::query()
                ->where('salary_id', $salary->id)
                ->lockForUpdate()
                ->findOrFail($deduction->id)
                ->delete();
            $this->expenses->sync($salary->fresh());
        });

        return response()->json(['message' => 'Salary deduction deleted.']);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ];
    }

    private function ensureWithinPayable(Salary $salary, float $amount, ?int $ignoreDeductionId = null): void
    {
        $existing = $salary->deductions()
            ->when($ignoreDeductionId, fn ($query) => $query->whereKeyNot($ignoreDeductionId))
            ->sum('amount');
        if (round((float) $existing + $amount, 8) > round((float) $salary->total_amount, 8)) {
            throw ValidationException::withMessages(['amount' => ['business.deductions_exceed_payable']]);
        }
    }
}
