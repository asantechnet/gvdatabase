<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceSettlement;
use App\Models\Salary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recovers salary advances from a payroll period.
 *
 * An advance is cash that already left the company. Recovering it from the
 * salary is therefore a deduction, never a second expense: the automatic
 * `advance_recovery` deduction lowers the salary's payable amount, and the part
 * it withholds is written to the advance settlement ledger.
 *
 * An advance bigger than the payslip's payable balance is recovered partially
 * and carries over to the next period; the deduction is always capped at
 * `total_amount - other deductions`, so net pay never goes negative. A
 * re-calculation (edit, new advance, manual toggle) undoes this payslip's
 * allocations first, so a balance is never counted twice.
 */
class SalaryAdvanceService
{
    public const DEDUCTION_CODE = 'advance_recovery';

    public function __construct(private readonly SalaryExpenseSynchronizer $expenses) {}

    /**
     * Attach the employee's open advances to this salary, up to the payable
     * balance that remains after the other deductions.
     */
    public function apply(Salary $salary): void
    {
        DB::transaction(function () use ($salary): void {
            $salary = Salary::query()->with(['deductions', 'employee'])->lockForUpdate()->findOrFail($salary->id);

            // Re-running the calculation (an edit) starts from a clean slate.
            $this->detachRecoveries($salary);

            $periodEnd = $this->periodEnd($salary);

            $candidates = EmployeeAdvance::query()
                ->where('employee_id', $salary->employee_id)
                ->whereColumn('settled_amount', '<', 'amount')
                ->whereDate('advance_date', '<=', $periodEnd)
                ->orderBy('advance_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $otherDeductions = (float) $salary->deductions
                ->where('code', '!=', self::DEDUCTION_CODE)
                ->sum('amount');
            // Never withhold more than this payslip can pay.
            $remaining = max(0, (float) $salary->total_amount - $otherDeductions);
            $recovered = 0.0;
            $numbers = [];

            foreach ($candidates as $advance) {
                $claim = round(min($advance->remainingAmount(), $remaining), 8);

                if ($claim <= 0.00000001) {
                    continue;
                }

                $advance->settlements()->create([
                    'salary_id' => $salary->id,
                    'kind' => EmployeeAdvanceSettlement::KIND_PAYROLL,
                    'amount' => $claim,
                    'date' => $periodEnd,
                    'remarks' => sprintf('Recovered from payslip %s', $salary->payslip_number),
                    'recorded_by' => $salary->recorded_by,
                ]);

                $advance->forceFill([
                    'salary_id' => $salary->id,
                    'recovered_at' => now(),
                ])->saveQuietly();
                $advance->refreshSettlementState();

                $numbers[] = $advance->advance_number;
                $recovered += $claim;
                $remaining -= $claim;
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

    /** Release every advance this salary recovered (edit/delete/opt-out). */
    public function release(Salary $salary): void
    {
        DB::transaction(function () use ($salary): void {
            $salary = Salary::query()->lockForUpdate()->findOrFail($salary->id);

            $this->detachRecoveries($salary);

            $salary->deductions()->where('code', self::DEDUCTION_CODE)->delete();
            $salary->forceFill(['advance_recovery' => 0])->saveQuietly();
            $this->expenses->sync($salary->fresh(['deductions', 'employee']));
        });
    }

    /**
     * Undo this payslip's part of every advance it withheld, leaving the
     * allocations of other periods untouched.
     */
    private function detachRecoveries(Salary $salary): void
    {
        $settlements = EmployeeAdvanceSettlement::query()
            ->where('salary_id', $salary->id)
            ->where('kind', EmployeeAdvanceSettlement::KIND_PAYROLL)
            ->lockForUpdate()
            ->get();

        foreach ($settlements as $settlement) {
            $advanceId = $settlement->employee_advance_id;
            $settlement->delete();

            $advance = EmployeeAdvance::query()->lockForUpdate()->find($advanceId);

            if (! $advance) {
                continue;
            }

            // A later period may already point at the advance; only this
            // payslip's pointer is cleared.
            if ((int) $advance->salary_id === (int) $salary->id) {
                $advance->forceFill(['salary_id' => null, 'recovered_at' => null])->saveQuietly();
            }

            $advance->refreshSettlementState();
        }
    }

    private function periodEnd(Salary $salary): string
    {
        [$year, $month] = array_map('intval', explode('-', $salary->salary_month));

        return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
    }
}
