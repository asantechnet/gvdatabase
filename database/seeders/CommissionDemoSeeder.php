<?php

namespace Database\Seeders;

use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeServiceCommission;
use Illuminate\Database\Seeder;

/**
 * A small, idempotent commission ledger for local/demo environments.
 *
 * It deliberately uses adjustment rows: they are valid commission accruals
 * without inventing applicant cases or mutating issued contract snapshots.
 * The two pending rows remain unassigned so the Create Settlement picker has
 * meaningful sample content as well.
 */
class CommissionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $currency = Currency::query()->where('is_base', true)->first() ?? Currency::query()->first();
        $employees = Employee::query()->where('status', Employee::STATUS_ACTIVE)->orderBy('id')->take(3)->get();

        if (! $currency || $employees->isEmpty()) {
            return;
        }

        $employees->each(function (Employee $employee): void {
            if (! $employee->is_commission_based) {
                $employee->update(['is_commission_based' => true]);
            }

            EmployeeServiceCommission::updateOrCreate(
                ['employee_id' => $employee->id, 'service' => 'scholarship'],
                ['percentage' => 8],
            );
        });

        $primaryEmployee = $employees->first();
        $secondEmployee = $employees->get(1) ?? $primaryEmployee;
        $thirdEmployee = $employees->get(2) ?? $primaryEmployee;

        $draft = $this->settlement($primaryEmployee, $currency, 'draft', 'Demo commission settlement — awaiting approval', 2850, '2026-09-01', '2026-09-30');
        $approved = $this->settlement($secondEmployee, $currency, 'approved', 'Demo commission settlement — approved for payout', 3400, '2026-08-01', '2026-08-31');
        $paid = $this->settlement($thirdEmployee, $currency, 'paid', 'Demo commission settlement — paid', 1950, '2026-07-01', '2026-07-31', '2026-08-05');

        $this->accrual($primaryEmployee, $currency, 'Demo commission — settlement draft', 2850, '2026-09-24', 'pending', $draft->id);
        $this->accrual($secondEmployee, $currency, 'Demo commission — settlement approved', 3400, '2026-08-22', 'approved', $approved->id);
        $this->accrual($thirdEmployee, $currency, 'Demo commission — settlement paid', 1950, '2026-07-19', 'paid', $paid->id);

        // These stay pending and unassigned so the Select Accruals table is
        // populated whenever a new settlement is opened for the employee.
        $this->accrual($primaryEmployee, $currency, 'Demo commission — eligible reconciliation', 1250, '2026-10-03', 'pending');
        $this->accrual($primaryEmployee, $currency, 'Demo commission — service adjustment', 800, '2026-10-12', 'pending');
        $this->accrual($secondEmployee, $currency, 'Demo commission — voided correction', -220, '2026-06-28', 'void');
    }

    private function settlement(Employee $employee, Currency $currency, string $status, string $notes, float $amount, string $start, string $end, ?string $paymentDate = null): CommissionSettlement
    {
        return CommissionSettlement::firstOrCreate(
            ['notes' => $notes],
            [
                'employee_id' => $employee->id,
                'base_currency_id' => $currency->id,
                'period_start' => $start,
                'period_end' => $end,
                'gross_base_amount' => $amount,
                'status' => $status,
                'payment_date' => $paymentDate,
                'paid_at' => $paymentDate ? $paymentDate.' 12:00:00' : null,
            ],
        );
    }

    private function accrual(Employee $employee, Currency $currency, string $reason, float $amount, string $earnedAt, string $status, ?int $settlementId = null): void
    {
        CommissionAccrual::firstOrCreate(
            ['entry_type' => 'adjustment', 'adjustment_reason' => $reason],
            [
                'employee_id' => $employee->id,
                'base_currency_id' => $currency->id,
                'commission_settlement_id' => $settlementId,
                'earned_at' => $earnedAt,
                'source_base_amount' => 0,
                'eligible_base_amount' => 0,
                'percentage' => 0,
                'commission_base_amount' => $amount,
                'status' => $status,
            ],
        );
    }
}
