<?php

namespace App\Services;

use App\Models\CommissionAccrual;
use App\Models\Income;
use App\Models\InvoiceService;
use Illuminate\Validation\ValidationException;

class CommissionAccrualService
{
    public function assertIncomeMutable(Income $income): void
    {
        if ($income->commissionAccruals()->whereNotNull('commission_settlement_id')->exists()) {
            throw ValidationException::withMessages([
                'income' => ['business.commission_payment_reserved'],
            ]);
        }
    }

    public function syncForIncome(Income $income): void
    {
        $this->assertIncomeMutable($income);
        $income->loadMissing(['invoice']);

        $eligibleIds = [];
        if ($income->invoice && $income->invoice_id) {
            $invoice = $income->invoice;
            $lines = InvoiceService::query()
                ->where('invoice_id', $invoice->id)
                ->whereNotNull('employee_id')
                ->whereNotNull('snapshot_commission_percentage')
                ->get();

            foreach ($lines as $line) {
                $discountShare = (float) $invoice->subtotal > 0
                    ? min(1, (float) $invoice->discount / (float) $invoice->subtotal)
                    : 0;
                $eligibleLineAmount = (float) $line->line_total * (1 - $discountShare);
                $eligibleBase = (float) $invoice->amount > 0
                    ? (float) $income->base_amount * ($eligibleLineAmount / (float) $invoice->amount)
                    : 0;
                $commission = $eligibleBase * ((float) $line->snapshot_commission_percentage / 100);

                $accrual = CommissionAccrual::withTrashed()->updateOrCreate(
                    ['income_id' => $income->id, 'invoice_service_id' => $line->id],
                    [
                        'invoice_id' => $invoice->id,
                        'employee_id' => $line->employee_id,
                        'service_id' => $line->service_id,
                        'customer_service_id' => $line->customer_service_id,
                        'base_currency_id' => $income->base_currency_id,
                        'commission_settlement_id' => null,
                        'entry_type' => 'earned',
                        'earned_at' => $income->date,
                        'source_payment_base_amount' => $income->base_amount,
                        'eligible_base_amount' => round($eligibleBase, 6),
                        'percentage' => $line->snapshot_commission_percentage,
                        'commission_base_amount' => round($commission, 6),
                        'status' => 'pending',
                        'deleted_at' => null,
                    ],
                );
                $eligibleIds[] = $accrual->id;
            }
        }

        $stale = $income->commissionAccruals()->whereNull('commission_settlement_id');
        if ($eligibleIds !== []) {
            $stale->whereNotIn('id', $eligibleIds);
        }
        $stale->update(['status' => 'void']);
    }

    public function voidForIncome(Income $income): void
    {
        $this->assertIncomeMutable($income);
        $income->commissionAccruals()->whereNull('commission_settlement_id')->update(['status' => 'void']);
    }
}
