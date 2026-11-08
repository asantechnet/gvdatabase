<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceSettlement;
use App\Models\Expense;
use App\Models\Salary;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records, repays and removes employee salary advances.
 *
 * An advance paid to an employee is real cash out, so it always carries a
 * matching payroll expense (`type` Salaries) linked to the employee — the same
 * convention payroll installments follow in SalaryExpenseSynchronizer.
 *
 * The debt is reduced by two kinds of settlement, both kept in
 * `employee_advance_settlements`: the part a payslip withheld (`payroll`) and
 * money the employee handed back (`cash`). A cash repayment also shrinks the
 * linked expense, because the expense is the net cash the company has out; once
 * the whole advance is returned the expense is removed. A payroll deduction
 * never touches the expense: the advance was genuinely paid out, the payslip
 * only withholds the repayment.
 *
 * Advances are base-currency only, matching the salary rule; the base currency
 * snapshot is stored on the advance so the row stays readable even if the
 * currency catalogue changes.
 */
class EmployeeAdvanceService
{
    public function __construct(private readonly SalaryAdvanceService $salaryAdvances) {}

    /**
     * @param  array{employee_id: int, amount: float|int|string, date: string, remarks?: string|null}  $data
     */
    public function record(array $data, ?int $recordedBy): EmployeeAdvance
    {
        return DB::transaction(function () use ($data, $recordedBy): EmployeeAdvance {
            $currency = Currency::query()->where('is_base', true)->lockForUpdate()->firstOrFail();
            $employee = Employee::query()->findOrFail($data['employee_id']);
            $amount = (float) $data['amount'];
            $rate = 1.0;

            $this->ensureMonthPayrollIsOpen($employee->id, (string) $data['date']);

            $advance = EmployeeAdvance::create([
                'employee_id' => $employee->id,
                'currency_id' => $currency->id,
                'base_currency_id' => $currency->id,
                'currency_code' => $currency->code,
                'currency_symbol' => $currency->symbol,
                'rate' => $rate,
                'amount' => $amount,
                'base_amount' => round($amount / $rate, 8),
                'settled_amount' => 0,
                'status' => EmployeeAdvance::STATUS_OUTSTANDING,
                'advance_date' => $data['date'],
                'remarks' => $data['remarks'] ?? null,
                'recorded_by' => $recordedBy,
            ]);

            $expense = Expense::create([
                'employee_id' => $employee->id,
                'currency_id' => $currency->id,
                'base_currency_id' => $currency->id,
                'currency' => $currency->code,
                'type' => 'Salaries',
                'description' => sprintf('Salary advance for %s (%s)', $employee->name, $data['date']),
                'amount' => $amount,
                'quantity' => 1,
                'cnv_curr_shrt' => $currency->code,
                'base_curr_shrt' => $currency->code,
                'rate' => $rate,
                'date' => $data['date'],
                'recorded_by' => $recordedBy,
            ]);

            // Keep the link without re-triggering the audit trail for a
            // bookkeeping-only column.
            $advance->forceFill(['expense_id' => $expense->id])->saveQuietly();

            $this->recoverFromOpenPayslip($advance);

            return $advance->fresh(['employee', 'currency', 'recorder', 'settlements']);
        });
    }

    /**
     * Record money the employee handed back against the advance.
     *
     * The linked expense is the net cash the company has out, so a repayment
     * shrinks it by the same amount and a full repayment removes it. The status
     * closes itself once nothing is owed.
     *
     * @param  array{amount: float|int|string, date: string, remarks?: string|null}  $data
     */
    public function settle(EmployeeAdvance $advance, array $data, ?int $recordedBy): EmployeeAdvance
    {
        return DB::transaction(function () use ($advance, $data, $recordedBy): EmployeeAdvance {
            $locked = EmployeeAdvance::query()->lockForUpdate()->findOrFail($advance->id);
            $amount = round((float) $data['amount'], 8);

            if ($amount > $locked->remainingAmount() + 0.00000001) {
                throw ValidationException::withMessages([
                    'amount' => ['business.advance_repayment_exceeds_balance'],
                ]);
            }

            $locked->settlements()->create([
                'kind' => EmployeeAdvanceSettlement::KIND_CASH,
                'amount' => $amount,
                'date' => $data['date'],
                'remarks' => $data['remarks'] ?? null,
                'recorded_by' => $recordedBy,
            ]);

            $this->shrinkExpense($locked, $amount);
            $locked->refreshSettlementState();

            return $locked->fresh(['employee', 'currency', 'recorder', 'settlements']);
        });
    }

    /**
     * Edit the advance itself. The amount can never drop below what has
     * already been paid back, and a payslip that already withheld money is
     * re-planned so its deduction always matches the new balance.
     *
     * @param  array{amount: float|int|string, date: string, remarks?: string|null}  $data
     */
    public function update(EmployeeAdvance $advance, array $data): EmployeeAdvance
    {
        return DB::transaction(function () use ($advance, $data): EmployeeAdvance {
            $locked = EmployeeAdvance::query()->lockForUpdate()->findOrFail($advance->id);
            $amount = round((float) $data['amount'], 8);

            if ($amount + 0.00000001 < (float) $locked->settled_amount) {
                throw ValidationException::withMessages([
                    'amount' => ['business.advance_amount_below_settled'],
                ]);
            }

            $salaryId = $locked->salary_id;

            $locked->update([
                'amount' => $amount,
                'advance_date' => $data['date'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            if ($salaryId && ($salary = Salary::query()->find($salaryId)) && ! $salary->payments()->exists()) {
                // The new amount may or may not fit this payslip any more.
                $this->salaryAdvances->apply($salary);
            }

            $locked->refreshSettlementState();

            return $locked->fresh(['employee', 'currency', 'recorder', 'settlements']);
        });
    }

    /** Delete an advance, its repayments and the payroll expense it created. */
    public function remove(EmployeeAdvance $advance): void
    {
        DB::transaction(function () use ($advance): void {
            $locked = EmployeeAdvance::query()->lockForUpdate()->findOrFail($advance->id);

            if ($locked->salary_id) {
                $salary = Salary::query()->find($locked->salary_id);

                // A settled payslip already withheld this money; deleting the
                // advance underneath it would leave a deduction for a debt that
                // no longer exists.
                if ($salary && $salary->payments()->exists()) {
                    throw ValidationException::withMessages([
                        'advance' => ['business.advance_locked_by_payroll'],
                    ]);
                }

                if ($salary) {
                    $this->salaryAdvances->release($salary);
                }
            }

            $locked->settlements()->delete();
            $locked->expense?->delete();
            $locked->delete();
        });
    }

    /**
     * Put a freshly recorded advance on the same month's payslip when that
     * payslip has not paid anything out yet.
     *
     * A payslip generated earlier in the month still owes this money, so the
     * advance belongs on it straight away rather than waiting a whole period.
     * A payslip that already paid (part of) its net is never rewritten:
     * raising its deductions afterwards would turn the recorded payment into
     * an overpayment, so the advance stays outstanding for the next open
     * period.
     */
    private function recoverFromOpenPayslip(EmployeeAdvance $advance): void
    {
        $salary = Salary::query()
            ->where('employee_id', $advance->employee_id)
            ->where('salary_month', $advance->advance_date->format('Y-m'))
            ->lockForUpdate()
            ->first();

        if (! $salary || $salary->payments()->exists()) {
            return;
        }

        $this->salaryAdvances->apply($salary);
    }

    /**
     * An advance belongs to the month it was handed out in. Adding one to a
     * month whose payslip already paid out (even partially) would retroactively
     * cut a net the employee has already received.
     */
    private function ensureMonthPayrollIsOpen(int $employeeId, string $date): void
    {
        $salary = Salary::query()
            ->where('employee_id', $employeeId)
            ->where('salary_month', substr($date, 0, 7))
            ->first();

        if (! $salary || ($salary->payment_status !== 'paid' && ! $salary->payments()->exists())) {
            return;
        }

        throw ValidationException::withMessages([
            'date' => ['business.advance_month_payroll_paid'],
        ]);
    }

    /** Cash came back in, so the expense that represented cash out shrinks. */
    private function shrinkExpense(EmployeeAdvance $advance, float $amount): void
    {
        $expense = $advance->expense;

        if (! $expense) {
            return;
        }

        $remaining = round((float) $expense->amount - $amount, 8);

        if ($remaining > 0.00000001) {
            $expense->update(['amount' => $remaining]);

            return;
        }

        $expense->delete();
        $advance->forceFill(['expense_id' => null])->saveQuietly();
    }
}
