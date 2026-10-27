<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Salary;
use App\Models\SalaryPayment;

class SalaryExpenseSynchronizer
{
    /** Keep the payroll expense in lockstep with the salary and deductions ledger. */
    public function sync(Salary $salary): void
    {
        $salary->loadMissing(['employee', 'deductions']);
        $netAmount = max(0, (float) $salary->total_amount - (float) $salary->deductions->sum('amount'));
        $salary->forceFill(['amount_due' => $netAmount])->saveQuietly();
        $this->refreshPaymentTotals($salary);
    }

    /** Record one actual payroll disbursement and its matching expense. */
    public function createPayment(Salary $salary, float $amount, string $date, ?string $remarks, ?int $recordedBy, ?int $pendingTransactionId = null): SalaryPayment
    {
        $salary->loadMissing(['employee', 'deductions']);
        $details = sprintf(
            'Payroll installment for %s (%s); worked: %.1f, total: %d, absent: %.1f, off days: %.1f of %s',
            $salary->employee?->name ?? 'employee', $salary->salary_month,
            $salary->worked_days, (int) $salary->total_days, $salary->absent_days, $salary->off_days, $salary->payslip_number,
        );

        $expense = Expense::create([
            'currency_id' => $salary->currency_id,
            'base_currency_id' => $salary->base_currency_id,
            'currency' => $salary->currency_code,
            'type' => 'Salaries',
            'description' => $details,
            'amount' => $amount,
            'quantity' => 1,
            'cnv_curr_shrt' => $salary->cnv_curr_shrt,
            'base_curr_shrt' => $salary->base_curr_shrt,
            'rate' => $salary->rate,
            'date' => $date,
            'recorded_by' => $recordedBy ?? $salary->recorded_by,
        ]);
        $payment = SalaryPayment::create([
            'salary_id' => $salary->id, 'expense_id' => $expense->id, 'pending_transaction_id' => $pendingTransactionId,
            'amount' => $amount, 'payment_date' => $date, 'remarks' => $remarks, 'recorded_by' => $recordedBy,
        ]);
        $this->refreshPaymentTotals($salary->fresh(['deductions']));

        return $payment;
    }

    public function refreshPaymentTotals(Salary $salary): void
    {
        $due = max(0, (float) ($salary->amount_due ?? ((float) $salary->total_amount - (float) $salary->deductions()->sum('amount'))));
        $paid = (float) $salary->payments()->whereNull('deleted_at')->sum('amount');
        $status = $paid <= 0 ? 'open' : ($paid + 0.00000001 >= $due ? 'paid' : 'partially_paid');
        $salary->forceFill(['amount_due' => $due, 'amount_paid_total' => $paid, 'payment_status' => $status])->saveQuietly();
    }
}
