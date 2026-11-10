<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\SalaryResource;
use App\Http\Resources\WorthyEmployeeResource;
use App\Models\Attendance;
use App\Models\Currency;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Salary;
use App\Models\WorthyEmployee;
use App\Services\CurrencyConversionService;
use App\Services\MonthlyAttendanceProcessor;
use App\Services\SalaryAdvanceService;
use App\Services\SalaryExpenseSynchronizer;
use App\Support\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalaryController extends Controller
{
    use PaginatesLists;
    public function __construct(
        private readonly CurrencyConversionService $currencies,
        private readonly SalaryExpenseSynchronizer $expenses,
        private readonly SalaryAdvanceService $advances,
        private readonly MonthlyAttendanceProcessor $attendance,
        private readonly CompanyProfile $company,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $query = Salary::with(['employee', 'currency', 'bonuses', 'deductions', 'payments', 'advanceSettlements.advance', 'recorder']);

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

        $query->latest('payment_date')->latest('id');

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true]
                + $this->paginatedPayload($request, $query, fn ($rows) => SalaryResource::collection($rows)->resolve()));
        }

        return SalaryResource::collection($query->get());
    }

    /**
     * One page of the payroll register for a month: each active employee with
     * that month's payslip (or none, which is how a missing payslip shows).
     *
     * The register is employee-driven, so the table cannot simply page the
     * salary rows: the KPI totals are computed in the database over the whole
     * month and ship with the page.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $search = $data['search'] ?? null;

        $employees = Employee::selectable()->excludePrimaryRecord()
            ->where('status', Employee::STATUS_ACTIVE)
            ->with(['position', 'office', 'user', 'departments', 'serviceCommissions'])
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('contact', 'like', "%{$search}%")
                ->orWhereHas('position', fn ($position) => $position->where('title', 'like', "%{$search}%"))))
            ->orderBy('name')->orderBy('id');

        $page = $employees->paginate($this->requestedPerPage($request) ?? 25)->withQueryString();

        $salaries = Salary::with(['employee', 'currency', 'bonuses', 'deductions', 'payments', 'advanceSettlements.advance', 'recorder'])
            ->where('salary_month', $data['month'])
            ->whereIn('employee_id', $page->getCollection()->pluck('id'))
            ->get()->keyBy('employee_id');

        // Same figures the payslip rows show: net is the total minus its
        // manual/automatic deductions, paid is what was actually paid out.
        // They cover the whole month's register, independent of the search.
        $sums = Salary::where('salary_month', $data['month'])->selectRaw(
            'COUNT(*) as payslip_count,'
            .' COALESCE(SUM(total_amount - COALESCE((SELECT SUM(sd.amount) FROM salary_deductions sd WHERE sd.salary_id = salaries.id), 0)), 0) as net_total,'
            .' COALESCE(SUM(COALESCE(amount_paid_total, amount_paid)), 0) as paid_total'
        )->first();
        $net = (float) $sums->net_total;
        $paid = (float) $sums->paid_total;

        $missing = Employee::selectable()->excludePrimaryRecord()
            ->where('status', Employee::STATUS_ACTIVE)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('salaries')
                ->whereColumn('salaries.employee_id', 'employees.id')
                ->where('salaries.salary_month', $data['month'])
                ->whereNull('salaries.deleted_at'))
            ->count();

        return response()->json([
            'success' => true,
            'data' => $page->getCollection()->map(fn (Employee $employee) => [
                'employee' => (new EmployeeResource($employee))->resolve(),
                'salary' => ($salary = $salaries->get($employee->id)) ? (new SalaryResource($salary))->resolve() : null,
            ])->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'totals' => [
                'net' => $net,
                'paid' => $paid,
                'outstanding' => max(0, $net - $paid),
                'generated' => (int) $sums->payslip_count,
                'missing' => $missing,
            ],
        ]);
    }

    public function store(Request $request): SalaryResource
    {
        $validated = $this->validateSalaryRequest($request);
        $validated['recorded_by'] = $request->user()->id;

        $salary = DB::transaction(fn () => $this->createSalary($validated));

        return new SalaryResource($salary->load($this->relations()));
    }

    /**
     * Create the missing payslips for a month from the current employee
     * roster. Existing payslips, employees without a base salary and employees
     * who had not joined yet are skipped; the response reports both counts.
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'salaryMonth' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $month = $data['salaryMonth'];
        $monthStart = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $baseCurrency = Currency::query()->where('is_base', true)->firstOrFail();
        $paymentDate = isset($data['date'])
            ? Carbon::parse($data['date'])->toDateString()
            : ($monthEnd->isFuture() ? now($this->company->timezone())->toDateString() : $monthEnd->toDateString());

        $employees = Employee::selectable()
            ->where('status', Employee::STATUS_ACTIVE)
            ->whereDate('join_date', '<=', $monthEnd->toDateString())
            ->when($data['employee_ids'] ?? null, fn ($query, $ids) => $query->whereIn('id', $ids))
            ->orderBy('id')
            ->get();

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($employees as $employee) {
            if ($employee->salary === null || (float) $employee->salary <= 0) {
                $skipped++;

                continue;
            }
            if (Salary::query()->where('employee_id', $employee->id)->where('salary_month', $month)->exists()) {
                $skipped++;

                continue;
            }

            try {
                DB::transaction(fn () => $this->createSalary([
                    'employee_id' => $employee->id,
                    'currency_id' => $baseCurrency->id,
                    'rate' => 1,
                    'salary_month' => $month,
                    'amount_paid' => (float) $employee->salary,
                    'initial_payment' => 0,
                    'remarks' => null,
                    'payment_date' => $paymentDate,
                    'deductions' => [],
                    'recorded_by' => $request->user()->id,
                ]));
                $created++;
            } catch (ValidationException $exception) {
                $errors[$employee->id] = collect($exception->errors())->flatten()->first();
                $skipped++;
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    public function show(Salary $salary): SalaryResource
    {
        return new SalaryResource($salary->load($this->relations()));
    }

    /**
     * Opt a payslip in or out of advance recovery.
     *
     * The deduction is optional while the payslip is unpaid; once any amount
     * has been paid out the net is settled and the recovery can no longer be
     * changed under it.
     */
    public function setAdvanceRecovery(Request $request, Salary $salary): SalaryResource
    {
        $validated = $request->validate(['applied' => ['required', 'boolean']]);

        DB::transaction(function () use ($salary, $validated): void {
            $salary = Salary::query()->lockForUpdate()->findOrFail($salary->id);

            if ($salary->payments()->exists()) {
                throw ValidationException::withMessages([
                    'applied' => ['business.advance_recovery_locked'],
                ]);
            }

            $validated['applied']
                ? $this->advances->apply($salary)
                : $this->advances->release($salary);
        });

        return new SalaryResource($salary->fresh($this->relations()));
    }

    public function update(Request $request, Salary $salary): SalaryResource
    {
        $validated = $this->validateSalaryRequest($request, $salary->id);
        abort_if((int) $validated['employee_id'] !== (int) $salary->employee_id, 422, 'business.salary_employee_immutable');
        abort_if($validated['salary_month'] !== $salary->salary_month, 422, 'business.salary_period_immutable');
        abort_if((int) $validated['currency_id'] !== (int) $salary->currency_id, 422, 'business.salary_currency_immutable');
        // The agreed base amount is a snapshot of the employment terms. Only
        // payments (and deduction lines) may change after the payslip exists.
        abort_if(abs((float) $validated['amount_paid'] - (float) $salary->amount_paid) > 0.00000001, 422, 'business.salary_amount_immutable');

        DB::transaction(function () use ($salary, $validated) {
            $salary = Salary::query()->lockForUpdate()->findOrFail($salary->id);
            // A bonus is assigned only when its payment is first created. This
            // keeps partial-payment edits from duplicating bonus amounts.
            $validated['bonus'] = $salary->bonus;
            $validated['total_amount'] = $validated['amount_paid'] + $salary->bonus;
            $validated['deductions'] = $this->stripAutomaticDeductions($salary, $validated['deductions']);
            $this->ensureDeductionsWithinPayable($validated['deductions'], (float) $validated['total_amount']);
            $rate = (float) $validated['rate'];
            $validated = array_merge($validated, $this->salaryCurrencySnapshot($salary->currency, (float) $validated['amount_paid'], (float) $salary->bonus, $rate));
            $validated = array_merge($validated, $this->attendanceCounts($validated['employee_id'], $validated['salary_month']));

            $salary->update($validated);
            $this->syncDeductions($salary, $validated['deductions']);
            $this->syncAutomaticDeduction($salary);
            $this->advances->apply($salary);
        });

        return new SalaryResource($salary->fresh($this->relations()));
    }

    public function destroy(Salary $salary): JsonResponse
    {
        DB::transaction(function () use ($salary) {
            $this->advances->release($salary);
            $salary->payments()->with('expense')->get()->each(function ($payment): void {
                $payment->expense?->delete();
            });
            $salary->delete();
        });

        return response()->json(['message' => 'Salary record successfully deleted.']);
    }

    /** Granted bonus records that will be included in a salary for a period. */
    public function grantedBonuses(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'salaryMonth' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $this->ensureSelectableEmployee($validated['employee_id']);
        validator(
            ['salary_month' => $validated['salaryMonth']],
            ['salary_month' => [
                Rule::unique('salaries', 'salary_month')
                    ->where(fn ($query) => $query->where('employee_id', $validated['employee_id'])),
            ]],
        )->validate();

        return WorthyEmployeeResource::collection(
            $this->availableGrantedBonusesQuery($validated['employee_id'], $validated['salaryMonth'])->get()
        );
    }

    /** Attendance-derived working-day breakdown for the payroll workspace. */
    public function attendanceBreakdown(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'salaryMonth' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $this->ensureSelectableEmployee($validated['employee_id']);
        $counts = $this->attendanceCounts($validated['employee_id'], $validated['salaryMonth']);

        return response()->json(['data' => [
            'workedDays' => $counts['worked_days'],
            'totalDays' => $counts['total_days'],
            'offDays' => $counts['off_days'],
            'absentDays' => $counts['absent_days'],
        ]]);
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['employee', 'currency', 'bonuses', 'deductions', 'payments', 'advanceSettlements.advance', 'recorder'];
    }

    /**
     * Shared salary creation used by the manual form and month generation.
     * Worked/total/off/absent days always come from the attendance ledger.
     */
    private function createSalary(array $validated): Salary
    {
        $currency = Currency::query()->lockForUpdate()->findOrFail($validated['currency_id']);
        $bonuses = $this->availableGrantedBonusesQuery($validated['employee_id'], $validated['salary_month'])
            ->lockForUpdate()
            ->get();
        $rate = (float) $validated['rate'];
        $bonusBase = (float) $bonuses->sum(fn (WorthyEmployee $bonus) => $this->bonusAmountInSalaryCurrency($bonus, $currency));
        $validated['bonus'] = round($bonusBase, $currency->decimal_places);
        $validated['total_amount'] = $validated['amount_paid'] + $validated['bonus'];
        $this->ensureDeductionsWithinPayable($validated['deductions'], (float) $validated['total_amount']);
        $counts = $this->attendanceCounts($validated['employee_id'], $validated['salary_month']);
        $validated = array_merge($validated, $counts);
        $validated['advance_recovery'] = 0;
        $validated += $this->salaryCurrencySnapshot($currency, $validated['amount_paid'], $bonusBase, $rate);

        $salary = Salary::create($validated);
        foreach ($bonuses as $bonus) {
            $salary->bonuses()->attach($bonus->id, $this->conversionSnapshot($bonus, $currency));
        }
        $this->syncDeductions($salary, $validated['deductions']);
        $this->syncAutomaticDeduction($salary);
        // Advance recovery is a deduction; it owns the payable balance from
        // here so the initial payment is checked against the real net due.
        $this->advances->apply($salary);
        // Recovery updates the salary through its own locked instance, so the
        // payable balance must be re-read before the first payment is validated.
        $salary->refresh()->load(['deductions']);
        $initialPayment = (float) ($validated['initial_payment'] ?? 0);
        $netDue = max(0, (float) $salary->amount_due);
        if ($initialPayment > $netDue + 0.00000001) {
            throw ValidationException::withMessages(['initialPayment' => ['The initial payment cannot exceed the payroll balance.']]);
        }
        if ($initialPayment > 0) {
            $this->expenses->createPayment($salary, $initialPayment, $validated['payment_date'], $validated['remarks'], $validated['recorded_by']);
        }

        return $salary;
    }

    /**
     * Validation mirroring the Payrolls form. Worked and total days are no
     * longer accepted: they are attendance-derived and read-only.
     */
    private function validateSalaryRequest(Request $request, $ignoreId = null): array
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'currency_id' => [
                'required',
                'integer',
                $ignoreId
                    ? Rule::exists('currencies', 'id')
                    : Rule::exists('currencies', 'id'),
            ],
            'rate' => ['sometimes', 'numeric', 'gt:0'],
            'salaryMonth' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amountPaid' => 'required|numeric|gt:0',
            'initialPayment' => ['nullable', 'numeric', 'min:0'],
            'remarks' => 'nullable|string|max:255',
            'date' => ['required', 'date', 'before_or_equal:today'],
            'deductions' => ['present', 'array'],
            'deductions.*.id' => ['nullable', 'integer'],
            'deductions.*.description' => ['required', 'string', 'max:255'],
            'deductions.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->ensureSelectableEmployee($validated['employee_id']);
        validator(
            ['salary_month' => $validated['salaryMonth']],
            ['salary_month' => [
                Rule::unique('salaries', 'salary_month')
                    ->where(fn ($query) => $query->where('employee_id', $validated['employee_id']))
                    ->ignore($ignoreId),
            ]],
        )->validate();
        // Payroll is recorded in the company's base currency. A salary must
        // never carry a secondary-currency rate or conversion interpretation.
        $baseCurrency = Currency::query()->where('is_base', true)->firstOrFail();
        if ((int) $validated['currency_id'] !== (int) $baseCurrency->id) {
            throw ValidationException::withMessages([
                'currency_id' => ['business.salary_base_currency_required'],
            ]);
        }
        if ($validated['salaryMonth'] > substr($validated['date'], 0, 7)) {
            throw ValidationException::withMessages([
                'date' => ['business.salary_payment_before_period'],
            ]);
        }

        return [
            'employee_id' => $validated['employee_id'],
            'currency_id' => $baseCurrency->id,
            'rate' => 1,
            'salary_month' => $validated['salaryMonth'],
            'amount_paid' => $validated['amountPaid'],
            'initial_payment' => $validated['initialPayment'] ?? 0,
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
        // Automatic rows are owned by the payroll calculation; drop any attempt
        // to submit them as ordinary manual line items.
        $deductions = $this->stripAutomaticDeductions($salary, $deductions);

        $existingIds = $salary->deductions()->where('is_automatic', false)->pluck('id')->all();
        $submittedIds = collect($deductions)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        // Never allow an update payload to modify deductions belonging to a different salary.
        if (array_diff($submittedIds, $existingIds)) {
            throw ValidationException::withMessages([
                'deductions' => ['business.deduction_not_owned'],
            ]);
        }

        $salary->deductions()->where('is_automatic', false)->whereNotIn('id', $submittedIds)->delete();

        foreach ($deductions as $sortOrder => $deduction) {
            $attributes = [
                'description' => $deduction['description'],
                'amount' => $deduction['amount'],
                // Reserve 0 for the automatic line so it always sorts first.
                'sort_order' => $sortOrder + 1,
            ];

            if (! empty($deduction['id'])) {
                $salary->deductions()->whereKey($deduction['id'])->update($attributes);
            } else {
                $salary->deductions()->create($attributes);
            }
        }
    }

    /**
     * Remove automatic deduction rows from a client-submitted list so they can
     * never be edited, deleted or counted as manual line items.
     */
    private function stripAutomaticDeductions(Salary $salary, array $deductions): array
    {
        $automaticIds = $salary->deductions()->where('is_automatic', true)->pluck('id')->all();
        if ($automaticIds === []) {
            return array_values($deductions);
        }

        return array_values(array_filter($deductions, function (array $deduction) use ($automaticIds): bool {
            return empty($deduction['id']) || ! in_array((int) $deduction['id'], $automaticIds, true);
        }));
    }

    /**
     * Regenerate the locked absence deduction from the attendance snapshot:
     * base salary × absent days ÷ total working days. Off days and present days
     * are never deducted; the bonus is intentionally left untouched.
     */
    private function syncAutomaticDeduction(Salary $salary): void
    {
        $base = (float) $salary->amount_paid;
        $total = (int) $salary->total_days;
        $absent = (float) $salary->absent_days;
        $amount = $total > 0 ? round($base * $absent / $total, (int) ($salary->currency_decimal_places ?? 2)) : 0.0;

        // Advance recovery has its own automatic row; keep the attendance
        // proration scoped to its code so the two never overwrite each other.
        $automatic = $salary->deductions()->where('is_automatic', true)
            ->where(fn ($query) => $query->whereNull('code')->orWhere('code', 'attendance_proration'))
            ->first();

        if ($amount <= 0) {
            $automatic?->delete();

            return;
        }

        $attributes = [
            'description' => sprintf('Absent (%s days)', rtrim(rtrim(number_format($absent, 1, '.', ''), '0'), '.')),
            'amount' => $amount,
            'code' => 'attendance_proration',
            'sort_order' => 0,
        ];

        if ($automatic) {
            $automatic->update($attributes);
        } else {
            $salary->deductions()->create($attributes + ['is_automatic' => true]);
        }
    }

    private function ensureDeductionsWithinPayable(array $deductions, float $payable): void
    {
        $deductionsTotal = round((float) collect($deductions)->sum(fn (array $row): float => (float) $row['amount']), 8);
        if ($deductionsTotal > round($payable, 8)) {
            throw ValidationException::withMessages([
                'deductions' => ['business.deductions_exceed_payable'],
            ]);
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
            'conversion_formula' => '(source_amount / source_rate_to_base) * salary_rate_to_base',
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

    /**
     * Attendance-derived day counts for an employee and pay month.
     *
     * `total_days` is the number of non-Friday workdays (clipped to the join
     * date), `worked_days` is the present fraction, `off_days` is the part of
     * an absence covered by an allowance or company holiday, and `absent_days`
     * is the remainder that payroll deducts.
     */
    private function attendanceCounts(int $employeeId, string $month): array
    {
        $employee = Employee::query()->findOrFail($employeeId);
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $monthStart = Carbon::create($year, $monthNumber, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        if ($employee->join_date && $employee->join_date->gt($monthEnd)) {
            throw ValidationException::withMessages(['salaryMonth' => ['business.salary_period_before_employment']]);
        }

        // Refresh missing rows and summaries for the period so a payslip never
        // freezes a ledger that has not accounted for an elapsed workday yet.
        $this->attendance->process($monthStart, $monthEnd, createMissing: true, force: false, alwaysSummarize: false);

        // Future days (and today, which is still in progress) are not absences.
        // This mirrors MonthlyAttendanceProcessor::materializeMissingDays so a
        // mid-month payslip cannot deduct days that have not happened yet.
        $elapsedEnd = $monthEnd->copy()->min(now($this->company->timezone())->subDay()->endOfDay());
        $total = $elapsedEnd->lt($monthStart) ? 0 : $this->attendance->workingDays($employee, $monthStart, $elapsedEnd);
        $attendances = Attendance::query()->where('employee_id', $employeeId)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])->get();
        $present = $attendances->sum(fn (Attendance $row) => $row->status === 'present'
            ? max(0, 1 - (float) $row->leave_days) : 0);
        $offDays = $attendances->sum(fn (Attendance $row) => (float) $row->off_day_days);
        $absent = max(0, $total - $present - $offDays);

        return [
            'total_days' => $total,
            'worked_days' => round($present, 1),
            'off_days' => round($offDays, 1),
            'absent_days' => round($absent, 1),
        ];
    }
}
