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
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalaryController extends Controller
{
    public function __construct(private readonly CurrencyConversionService $currencies)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Salary::with(['employee', 'currency', 'bonuses', 'deductions', 'expense', 'recorder']);

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
        $validated['recorded_by'] = $request->user()->id;

        $salary = DB::transaction(function () use ($validated) {
            $currency = Currency::query()->lockForUpdate()->findOrFail($validated['currency_id']);
            $bonuses = $this->availableGrantedBonusesQuery($validated['employee_id'], $validated['salary_month'])
                ->lockForUpdate()
                ->get();
            $rate = (float) $validated['rate'];
            $bonusBase = (float) $bonuses->sum(fn (WorthyEmployee $bonus) => $this->bonusAmountInSalaryCurrency($bonus, $currency));
            $validated['bonus'] = round($bonusBase, $currency->decimal_places);
            $validated['total_amount'] = $validated['amount_paid'] + $validated['bonus'];
            $validated = array_merge($validated, $this->attendanceBreakdown($validated['employee_id'], $validated['salary_month'], $validated['total_days']));
            $validated += $this->salaryCurrencySnapshot($currency, $validated['amount_paid'], $bonusBase, $rate);

            $salary = Salary::create($validated);
            foreach ($bonuses as $bonus) {
                $salary->bonuses()->attach($bonus->id, $this->conversionSnapshot($bonus, $currency));
            }
            $this->syncDeductions($salary, $validated['deductions']);
            $this->syncExpense($salary);

            return $salary;
        });

        return new SalaryResource($salary->load(['employee', 'currency', 'bonuses', 'deductions', 'expense', 'recorder']));
    }

    public function show(Salary $salary): SalaryResource
    {
        return new SalaryResource($salary->load(['employee', 'currency', 'bonuses', 'deductions', 'expense', 'recorder']));
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
            $rate = (float) $validated['rate'];
            $validated = array_merge($validated, $this->salaryCurrencySnapshot($salary->currency, (float) $validated['amount_paid'], (float) $salary->bonus, $rate));
            $validated = array_merge($validated, $this->attendanceBreakdown($validated['employee_id'], $validated['salary_month'], $validated['total_days']));

            $salary->update($validated);
            $this->syncDeductions($salary, $validated['deductions']);
            $this->syncExpense($salary);
        });

        // Returns updated resource to populate the form view in place
        return new SalaryResource($salary->fresh(['employee', 'currency', 'bonuses', 'deductions', 'expense', 'recorder']));
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
            'rate' => ['required', 'numeric', 'gt:0'],
            'salaryMonth' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amountPaid' => 'required|numeric|gt:0',
            'workedDays' => ['required', 'numeric', 'between:0,31', 'multiple_of:0.5'],
            'totalDays' => 'required|integer|between:1,31',
            'remarks' => 'nullable|string|max:255',
            'date' => 'required|date',
            'deductions' => ['present', 'array'],
            'deductions.*.id' => ['nullable', 'integer'],
            'deductions.*.description' => ['required', 'string', 'max:255'],
            'deductions.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->ensureSelectableEmployee($validated['employee_id']);
        // Payroll is recorded in the company's base currency.  A salary must
        // never carry a secondary-currency rate or conversion interpretation.
        $baseCurrency = Currency::query()->where('is_base', true)->firstOrFail();

        return [
            'employee_id' => $validated['employee_id'],
            'currency_id' => $baseCurrency->id,
            'rate' => 1,
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
            ->with(['employee', 'currency', 'baseCurrency'])
            ->where('employee_id', $employeeId)
            ->where('is_granted', true)
            ->whereDoesntHave('salaries')
            ->whereYear('bonus_month', (int) $year)
            ->whereMonth('bonus_month', (int) $month)
            ->orderBy('created_at');
    }

    private function validRate(Currency $currency): float
    {
        $rate = (float) $currency->rate;
        abort_unless($rate > 0, 422, 'business.currency_rate_invalid');

        return $rate;
    }

    private function salaryCurrencySnapshot(Currency $currency, float $amountPaid, float $bonusBase, float $rate): array
    {
        $base = Currency::query()->where('is_base', true)->firstOrFail();

        return [
            'currency_code' => $currency->code,
            'currency_name' => $currency->name,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_places' => $currency->decimal_places,
            'cnv_curr_shrt' => $currency->code,
            'base_curr_shrt' => $base->code,
            'base_currency_id' => $base->id,
            'rate' => $rate,
            'base_amount' => round(($amountPaid + $bonusBase) / $rate, 4),
            'currency_snapshot_source' => 'recorded_at_creation',
            'currency_snapshot_note' => null,
        ];
    }

    private function conversionSnapshot(WorthyEmployee $bonus, Currency $salaryCurrency): array
    {
        $sourceCurrency = $bonus->currency ?? $bonus->baseCurrency ?? $salaryCurrency;
        $sourceRate = (float) ($bonus->rate ?: $sourceCurrency->rate ?: 1);

        return [
            'source_amount' => $bonus->granted_amount,
            'source_currency_code' => $sourceCurrency->code,
            'source_rate' => $sourceRate,
            'salary_currency_code' => $salaryCurrency->code,
            'salary_rate' => (float) $salaryCurrency->rate,
            'converted_amount' => $this->bonusAmountInSalaryCurrency($bonus, $salaryCurrency),
            'conversion_formula' => '(source_amount ÷ source_rate_to_base) × salary_rate_to_base',
            'conversion_snapshot_source' => 'recorded_at_inclusion',
        ];
    }

    private function bonusAmountInSalaryCurrency(WorthyEmployee $bonus, Currency $salaryCurrency): float
    {
        if ($bonus->base_amount !== null && $bonus->baseCurrency) {
            return $this->currencies->convert(
                (float) $bonus->base_amount,
                $bonus->baseCurrency,
                $salaryCurrency,
                $salaryCurrency->decimal_places,
            );
        }

        return $this->currencies->convert(
            (float) $bonus->granted_amount,
            $bonus->currency ?? $salaryCurrency,
            $salaryCurrency,
            $salaryCurrency->decimal_places,
        );
    }

    private function attendanceBreakdown(int $employeeId, string $month, int $totalDays): array
    {
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $monthStart = Carbon::create($year, $monthNumber, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $workingDays = 0;
        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            if (! $day->isFriday()) $workingDays++;
        }
        $attendances = Attendance::query()->where('employee_id', $employeeId)
            ->whereYear('date', $year)->whereMonth('date', $monthNumber)->get();
        $present = (float) $attendances->sum(fn (Attendance $row) => $row->status === 'present'
            ? max(0, 1 - (float) $row->leave_duration_days) : 0);
        $leave = \App\Models\PaidTimeOff::query()->where('employee_id', $employeeId)
            ->where('is_granted', true)->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])->get();
        $paid = (float) $leave->sum('paid_days');
        $unpaid = (float) $leave->sum(fn ($row) => max(0, (float) $row->duration_days - (float) $row->paid_days));

        return [
            'total_days' => $workingDays,
            'worked_days' => round(min($workingDays, $present + $paid), 1),
            'paid_leave_days' => round($paid, 1),
            'unpaid_leave_days' => round($unpaid, 1),
            'absent_days' => round(max(0, $workingDays - $present - $paid - $unpaid), 1),
        ];
    }

    private function syncExpense(Salary $salary): void
    {
        $salary->loadMissing(['employee', 'deductions']);
        $netAmount = max(0, (float) $salary->total_amount - (float) $salary->deductions->sum('amount'));
        $details = sprintf(
            'Payroll %s for %s (%s); payable: %.1f, paid leave: %.1f, absent: %.1f, unpaid leave: %.1f',
            $salary->payslip_number, $salary->employee?->name ?? 'employee', $salary->salary_month,
            $salary->worked_days, $salary->paid_leave_days, $salary->absent_days, $salary->unpaid_leave_days,
        );
        $expense = Expense::firstOrNew(['salary_id' => $salary->id]);
        $expense->fill([
            'currency_id' => $salary->currency_id,
            'base_currency_id' => $salary->base_currency_id,
            'currency' => $salary->currency_code,
            'type' => 'Salaries',
            'description' => $details,
            'amount' => $netAmount,
            'quantity' => 1,
            'cnv_curr_shrt' => $salary->cnv_curr_shrt,
            'base_curr_shrt' => $salary->base_curr_shrt,
            'rate' => $salary->rate,
            'date' => $salary->payment_date,
            'recorded_by' => $salary->recorded_by,
        ]);
        $expense->save();
    }
}
