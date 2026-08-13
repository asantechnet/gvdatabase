<?php

namespace App\Http\Controllers;

use App\Http\Resources\SalaryDeductionResource;
use App\Models\Salary;
use App\Models\SalaryDeduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalaryDeductionController extends Controller
{
    public function index(Salary $salary): AnonymousResourceCollection
    {
        return SalaryDeductionResource::collection($salary->deductions()->orderBy('sort_order')->get());
    }

    public function store(Request $request, Salary $salary): SalaryDeductionResource
    {
        return new SalaryDeductionResource($salary->deductions()->create($this->validated($request)));
    }

    public function update(Request $request, Salary $salary, SalaryDeduction $deduction): SalaryDeductionResource
    {
        abort_unless($deduction->salary_id === $salary->id, 404);
        $deduction->update($this->validated($request));

        return new SalaryDeductionResource($deduction->fresh());
    }

    public function destroy(Salary $salary, SalaryDeduction $deduction): JsonResponse
    {
        abort_unless($deduction->salary_id === $salary->id, 404);
        $deduction->delete();

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
}
