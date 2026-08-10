<?php

namespace App\Http\Controllers;

use App\Models\Salary;
use App\Models\WorthyEmployee;
use App\Http\Resources\SalaryResource;
use App\Http\Resources\WorthyEmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalaryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Salary::with(['employee', 'currency', 'bonuses']);

        // Handle frontend filtering
        if ($request->filled('salaryMonthFilter')) {
            $query->where('salary_month', $request->salaryMonthFilter);
        }

        if ($request->filled('selectedSalaryAmountRange')) {
            $range = $request->selectedSalaryAmountRange;
            if ($range === 'low') {
                $query->where('total_amount', '<', 40000);
            } elseif ($range === 'mid') {
                $query->whereBetween('total_amount', [40000, 70000]);
            } elseif ($range === 'high') {
                $query->where('total_amount', '>', 70000);
            }
        }

        if ($request->filled('searchQuery')) {
            $search = $request->searchQuery;
            $query->where(function ($q) use ($search) {
                $q->where('payslip_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return SalaryResource::collection($query->latest('payment_date')->get());
    }

    public function store(Request $request): SalaryResource
    {
        $validated = $this->validateSalaryRequest($request);

        $salary = DB::transaction(function () use ($validated) {
            $bonuses = $this->availableGrantedBonusesQuery($validated['employee_id'], $validated['salary_month'])
                ->lockForUpdate()
                ->get();
            $validated['bonus'] = (float) $bonuses->sum('selected_amount');
            // Calculate all payroll values on the server; bonuses are derived from granted bonus records.
            $validated['total_amount'] = $validated['amount_paid'] + $validated['bonus'];

            $salary = Salary::create($validated);
            $salary->bonuses()->attach($bonuses->modelKeys());

            return $salary;
        });

        return new SalaryResource($salary->load(['employee', 'currency', 'bonuses']));
    }

    public function show(Salary $salary): SalaryResource
    {
        return new SalaryResource($salary->load(['employee', 'currency', 'bonuses']));
    }

    public function update(Request $request, Salary $salary): SalaryResource
    {
        $validated = $this->validateSalaryRequest($request, $salary->id);

        DB::transaction(function () use ($salary, $validated) {
            // A bonus is assigned only when its payment is first created. This
            // keeps partial-payment edits from duplicating bonus amounts.
            $validated['bonus'] = $salary->bonus;
            $validated['total_amount'] = $validated['amount_paid'] + $validated['bonus'];

            $salary->update($validated);
        });

        // Returns updated resource to populate the form view in place
        return new SalaryResource($salary->fresh(['employee', 'currency', 'bonuses']));
    }

    public function destroy(Salary $salary): JsonResponse
    {
        $salary->delete();

        return response()->json(['message' => 'Salary record successfully deleted.']);
    }

    /**
     * Return the granted bonus records that will be included in a salary for a
     * particular employee and pay month. A separate endpoint keeps the salary
     * form from loading and filtering the complete bonus list client-side.
     */
    public function grantedBonuses(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'salaryMonth' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        return WorthyEmployeeResource::collection(
            $this->availableGrantedBonusesQuery($validated['employee_id'], $validated['salaryMonth'])->get()
        );
    }

    /**
     * Reusable validation mirroring Vue constraints.
     */
    private function validateSalaryRequest(Request $request, $ignoreId = null): array
    {
        // We map frontend camelCase inputs to backend snake_case array keys
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'salaryMonth' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amountPaid' => 'required|numeric|gt:0',
            'workedDays' => 'required|integer|between:0,31',
            'totalDays' => 'required|integer|between:1,31',
            'remarks' => 'nullable|string|max:255',
            'date' => 'required|date',
        ]);

        return [
            'employee_id' => $validated['employee_id'],
            'currency_id' => $validated['currency_id'],
            'salary_month' => $validated['salaryMonth'],
            'amount_paid' => $validated['amountPaid'],
            'worked_days' => $validated['workedDays'],
            'total_days' => $validated['totalDays'] ?? 30,
            'remarks' => $validated['remarks'] ?? null,
            'payment_date' => $validated['date'],
        ];
    }

    private function availableGrantedBonusesQuery(int $employeeId, string $salaryMonth)
    {
        [$year, $month] = explode('-', $salaryMonth);

        return WorthyEmployee::query()
            ->with('employee')
            ->where('employee_id', $employeeId)
            ->where('is_granted', true)
            ->whereDoesntHave('salaries')
            ->whereYear('bonus_month', (int) $year)
            ->whereMonth('bonus_month', (int) $month)
            ->orderBy('created_at');
    }
}
