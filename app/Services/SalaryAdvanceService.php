<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\Salary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recovers salary advances from a payroll period.
 *
 * An advance is cash that already left the company. Recovering it from the
 * salary is therefore a deduction, never a second expense: the automatic
 * `advance_recovery` deduction lowers the salary's payable amount, and the
 * advance keeps pointing at the payroll row that recovered it. Each advance is
 * recovered exactly once; if it does not fit within the payable balance it is
 * left outstanding for the next period.
 */
class SalaryAdvanceService
{
    public const DEDUCTION_CODE = 'advance_recovery';

    public function __construct(private readonly SalaryExpenseSynchronizer $expenses) {}

    /**
     * Attach the employee's outstanding advances to this salary, up to the
     * payable balance that remains after the other deductions.
     */
    public function apply(Salary $salary): void
    {
        DB::transaction(function () use ($salary): void {
            $salary = Salary::query()->with(['deductions', 'employee'])->lockForUpdate()->findOrFail($salary->id);

            // Re-running the calculation (an edit) starts from a clean slate.
            EmployeeAdvance::query()->where('salary_id', $salary->id)
                ->lockForUpdate()
                ->update(['salary_id' => null, 'recovered_at' => null]);

            [, $monthEnd] = array_map('intval', explode('-', $salary->salary_month));
            $periodEnd = Carbon::create((int) substr($salary->salary_month, 0, 4), $monthEnd, 1)->endOfMonth()->toDateString();

            $candidates = EmployeeAdvance::query()
                ->where('employee_id', $salary->employee_id)
                ->whereNull('salary_id')
                ->whereDate('advance_date', '<=', $periodEnd)
                ->orderBy('advance_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $otherDeductions = (float) $salary->deductions
                ->where('code', '!=', self::DEDUCTION_CODE)
                ->sum('amount');
            $remaining = max(0, (float) $salary->total_amount - $otherDeductions);
            $recovered = 0.0;
            $numbers = [];

            foreach ($candidates as $advance) {
                $amount = round((float) $advance->amount, 8);

                // Only a fully coverable advance is recovered; a partial claim
                // would need a repayment ledger the business does not keep.
                if ($amount <= 0 || $amount > $remaining + 0.00000001) {
                    continue;
                }

                $advance->forceFill([
                    'salary_id' => $salary->id,
                    'recovered_at' => now(),
                ])->saveQuietly();

                $numbers[] = $advance->advance_number;
                $recovered += $amount;
                $remaining -= $amount;
            }

            $recovered = round($recovered, 8);
            $automatic = $salary->deductions()->where('code', self::DEDUCTION_CODE)->first();

            if ($recovered > 0) {
                $description = sprintf(
                    'Salary advance recovery (%s)',
                    implode(', ', array_filter($numbers)) ?: 'advance',
                );
                $attributes = [
                    'description' => mb_substr($description, 0, 255),
                    'amount' => $recovered,
                    'code' => self::DEDUCTION_CODE,
                    'is_automatic' => true,
                    'sort_order' => 1,
                ];
                $automatic ? $automatic->update($attributes) : $salary->deductions()->create($attributes);
            } elseif ($automatic) {
                $automatic->delete();
            }

            $salary->forceFill(['advance_recovery' => $recovered])->saveQuietly();
            $this->expenses->sync($salary->fresh(['deductions', 'employee']));
        });
    }

    /** Release every advance this salary recovered (edit/delete). */
    public function release(Salary $salary): void
    {
        DB::transaction(function () use ($salary): void {
            EmployeeAdvance::query()->where('salary_id', $salary->id)
                ->update(['salary_id' => null, 'recovered_at' => null]);

            $salary->deductions()->where('code', self::DEDUCTION_CODE)->delete();
            $salary->forceFill(['advance_recovery' => 0])->saveQuietly();
        });
    }
}
