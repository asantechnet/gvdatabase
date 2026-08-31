<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Salary;

class SalaryExpenseSynchronizer
{
    /** Keep the payroll expense in lockstep with the salary and deductions ledger. */
    public function sync(Salary $salary): void
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
