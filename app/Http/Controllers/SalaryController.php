<?php

namespace App\Http\Controllers;

use App\Http\Resources\SalaryResource;
use App\Http\Resources\WorthyEmployeeResource;
use App\Models\Attendance;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Salary;
use App\Models\WorthyEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalaryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Salary::with(['employee', 'currency', 'bonuses', 'deductions', 'expense']);

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
            $currency = Currency::query()->lockForUpdate()->findOrFail($validated['currency_id']);
            $bonuses = $this->availableGrantedBonusesQuery($validated['employee_id'], $validated['salary_month'])
                ->lockForUpdate()
                ->get();
            $rate = $this->validRate($currency);
            $bonusBase = (float) $bonuses->sum(fn (WorthyEmployee $bonus) => (float) $bonus->granted_base_amount);
            $validated['bonus'] = round($bonusBase / $rate, $currency->decimal_places);
            $validated['total_amount'] = $validated['amount_paid'] + $validated['bonus'];
            $validated = array_merge($validated, $this->attendanceBreakdown($validated['employee_id'], $validated['salary_month'], $validated['total_days']));
            $validated += $this->salaryCurrencySnapshot($currency, $validated['amount_paid'], $bonusBase);

            $salary = Salary::create($validated);
            foreach ($bonuses as $bonus) {
                $salary->bonuses()->attach($bonus->id, $this->conversionSnapshot($bonus, $currency));
            }
            $this->syncDeductions($salary, $validated['deductions']);
            $this->syncExpense($salary);

            return $salary;
        });

        return new SalaryResource($salary->load(['employee', 'currency', 'bonuses', 'deductions', 'expense']));
    }

    public function show(Salary $salary): SalaryResource
    {
        return new SalaryResource($salary->load(['employee', 'currency', 'bonuses', 'deductions', 'expense']));
    }

    public function update(Request $request, Salary $salary): SalaryResource
    {
        $validated = $this->validateSalaryRequest($request, $salary->id);
        abort_if((int) $validated['currency_id'] !== (int) $salary->currency_id, 422, 'business.salary_currency_immutable');

        DB::transaction(function () use ($salary, $validated) {
            // A bonus is assigned only when its payment is first created. This
            // keeps partial-payment edits from duplicating bonus amounts.
            $validated['bonus'] = $salary->bonus;
            $validated['total_amount'] = $validated['amount_paid'] + $validated['bonus'];
            $rate = $this->storedSalaryRate($salary);
            $validated['amount_paid_base'] = round((float) $validated['amount_paid'] * $rate, 4);
            $validated['bonus_base'] = $salary->bonus_base;
            $validated['total_amount_base'] = round((float) $validated['amount_paid_base'] + (float) $salary->bonus_base, 4);
            $validated = array_merge($validated, $this->attendanceBreakdown($validated['employee_id'], $validated['salary_month'], $validated['total_days']));

            $salary->update($validated);
            $this->syncDeductions($salary, $validated['deductions']);
            $this->syncExpense($salary);
        });

        // Returns updated resource to populate the form view in place
        return new SalaryResource($salary->fresh(['employee', 'currency', 'bonuses', 'deductions', 'expense']));
    }

    public function destroy(Salary $salary): JsonResponse
    {
        DB::transaction(function () use ($salary) {
            $salary->expense?->delete();
            $salary->delete();
        });

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

        $this->ensureSelectableEmployee($validated['employee_id']);

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
            'currency_id' => [
                'required',
                'integer',
                $ignoreId
                    ? Rule::exists('currencies', 'id')
                    : Rule::exists('currencies', 'id')->where('is_active', true),
            ],
            'salaryMonth' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amountPaid' => 'required|numeric|gt:0',
            'workedDays' => 'required|integer|between:0,31',
            'totalDays' => 'required|integer|between:1,31',
            'remarks' => 'nullable|string|max:255',
            'date' => 'required|date',
            'deductions' => ['present', 'array'],
            'deductions.*.id' => ['nullable', 'integer'],
            'deductions.*.description' => ['required', 'string', 'max:255'],
            'deductions.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->ensureSelectableEmployee($validated['employee_id']);

        return [
            'employee_id' => $validated['employee_id'],
            'currency_id' => $validated['currency_id'],
            'salary_month' => $validated['salaryMonth'],
            'amount_paid' => $validated['amountPaid'],
            'worked_days' => $validated['workedDays'],
            'total_days' => $validated['totalDays'] ?? 30,
            'remarks' => $validated['remarks'] ?? null,
            'payment_date' => $validated['date'],
            'deductions' => $validated['deductions'],
        ];
    }

    private function ensureSelectableEmployee(int $employeeId): void
    {
        $employee = Employee::selectable()->find($employeeId);
        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_id' => ['business.employee_not_selectable'],
            ]);
        }
        if ($employee->employment_type === 'commission') {
            throw ValidationException::withMessages([
                'employee_id' => ['Commission employees are paid through service income and cannot receive salary records.'],
            ]);
        }
    }

    private function syncDeductions(Salary $salary, array $deductions): void
    {
        $existingIds = $salary->deductions()->pluck('id')->all();
        $submittedIds = collect($deductions)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        // Never allow an update payload to modify deductions belonging to a different salary.
        if (array_diff($submittedIds, $existingIds)) {
            throw ValidationException::withMessages([
                'deductions' => ['business.deduction_not_owned'],
            ]);
        }

        $salary->deductions()->whereNotIn('id', $submittedIds)->delete();

        foreach (array_values($deductions) as $sortOrder => $deduction) {
            $attributes = [
                'description' => $deduction['description'],
                'amount' => $deduction['amount'],
                'sort_order' => $sortOrder,
            ];

            if (! empty($deduction['id'])) {
                $salary->deductions()->whereKey($deduction['id'])->update($attributes);
            } else {
                $salary->deductions()->create($attributes);
            }
        }
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

    private function validRate(Currency $currency): float
    {
        $rate = (float) $currency->rate_against_base;
        abort_unless($rate > 0, 422, 'business.currency_rate_invalid');

        return $rate;
    }

    private function storedSalaryRate(Salary $salary): float
    {
        $rate = (float) $salary->exchange_rate_to_base;
        abort_unless($rate > 0, 422, 'business.currency_rate_invalid');

        return $rate;
    }

    private function salaryCurrencySnapshot(Currency $currency, float $amountPaid, float $bonusBase): array
    {
        $rate = $this->validRate($currency);

        return [
            'currency_code' => $currency->code,
            'currency_name' => $currency->name,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_places' => $currency->decimal_places,
            'exchange_rate_to_base' => $rate,
            'amount_paid_base' => round($amountPaid * $rate, 4),
            'bonus_base' => round($bonusBase, 4),
            'total_amount_base' => round(($amountPaid * $rate) + $bonusBase, 4),
            'currency_snapshot_source' => 'recorded_at_creation',
            'currency_snapshot_note' => null,
        ];
    }

    private function conversionSnapshot(WorthyEmployee $bonus, Currency $salaryCurrency): array
    {
        $sourceRate = (float) $bonus->granted_rate_to_base;
        $targetRate = $this->validRate($salaryCurrency);
        abort_unless($sourceRate > 0 && $bonus->granted_amount !== null, 422, 'business.bonus_currency_snapshot_invalid');
        $baseAmount = (float) $bonus->granted_base_amount;

        return [
            'source_amount' => $bonus->granted_amount,
            'source_currency_code' => $bonus->granted_currency_code,
            'source_rate_to_base' => $sourceRate,
            'base_amount' => $baseAmount,
            'salary_currency_code' => $salaryCurrency->code,
            'salary_rate_to_base' => $targetRate,
            'converted_amount' => round($baseAmount / $targetRate, $salaryCurrency->decimal_places),
            'conversion_formula' => '(source_amount × source_rate_to_base) ÷ salary_rate_to_base',
            'conversion_snapshot_source' => 'recorded_at_inclusion',
        ];
    }

    private function attendanceBreakdown(int $employeeId, string $month, int $totalDays): array
    {
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $counts = Attendance::query()->where('employee_id', $employeeId)
            ->whereYear('date', $year)->whereMonth('date', $monthNumber)
            ->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $present = (int) ($counts['present'] ?? 0);
        $paid = (int) ($counts['paid-off-day'] ?? 0);
        $unpaid = (int) ($counts['unpaid-off-day'] ?? 0);

        return [
            'worked_days' => min($totalDays, $present + $paid),
            'paid_off_days' => $paid,
            'unpaid_off_days' => $unpaid,
            'absent_days' => max(0, $totalDays - $present - $paid - $unpaid),
        ];
    }

    private function syncExpense(Salary $salary): void
    {
        $salary->loadMissing(['employee', 'deductions']);
        $netAmount = max(0, (float) $salary->total_amount - (float) $salary->deductions->sum('amount'));
        $details = sprintf(
            'Payroll %s for %s (%s); present/paid: %d, paid off: %d, absent: %d, unpaid off: %d',
            $salary->payslip_number, $salary->employee?->name ?? 'employee', $salary->salary_month,
            $salary->worked_days, $salary->paid_off_days, $salary->absent_days, $salary->unpaid_off_days,
        );
        $expense = Expense::firstOrNew(['salary_id' => $salary->id]);
        $expense->fill([
            'currency_id' => $salary->currency_id,
            'currency' => $salary->currency_code,
            'type' => 'Salary payment',
            'description' => $details,
            'amount' => $netAmount,
            'quantity' => 1,
            'rate_to_base' => $salary->exchange_rate_to_base,
            'date' => $salary->payment_date,
        ]);
        $expense->save();
    }
}
