<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

/**
 * Records and removes employee salary advances.
 *
 * An advance paid to an employee is real cash out, so it always carries a
 * matching payroll expense (`type` Salaries) linked to the employee — the same
 * convention payroll installments follow in SalaryExpenseSynchronizer. Removing
 * an advance deletes that expense, which is how a repayment is recorded.
 *
 * Advances are base-currency only, matching the salary rule; the base currency
 * snapshot is stored on the advance so the row stays readable even if the
 * currency catalogue changes.
 */
class EmployeeAdvanceService
{
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

            $advance = EmployeeAdvance::create([
                'employee_id' => $employee->id,
                'currency_id' => $currency->id,
                'base_currency_id' => $currency->id,
                'currency_code' => $currency->code,
                'currency_symbol' => $currency->symbol,
                'rate' => $rate,
                'amount' => $amount,
                'base_amount' => round($amount / $rate, 8),
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

            return $advance->fresh(['employee', 'currency', 'recorder']);
        });
    }

    /** Delete an advance and the payroll expense it created (employee repayment). */
    public function remove(EmployeeAdvance $advance): void
    {
        DB::transaction(function () use ($advance): void {
            $locked = EmployeeAdvance::query()->lockForUpdate()->findOrFail($advance->id);
            $locked->expense?->delete();
            $locked->delete();
        });
    }
}
