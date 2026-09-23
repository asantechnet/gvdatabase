<?php

namespace App\Services;

use App\Models\Income;
use App\Models\InvoiceService;
use App\Models\OutsourcingAccrual;
use Illuminate\Validation\ValidationException;

class OutsourcingAccrualService
{
    public function assertIncomeMutable(Income $income): void
    {
        if ($income->outsourcingAccruals()->whereNotNull('outsourcing_settlement_id')->exists()) {
            throw ValidationException::withMessages([
                'income' => ['business.outsourcing_payment_reserved'],
            ]);
        }
    }

    public function syncForIncome(Income $income): void
    {
        $this->assertIncomeMutable($income);
        $income->loadMissing('invoice');
        $eligibleIds = [];

        if ($income->invoice && $income->invoice_id) {
            $invoice = $income->invoice;
            $lines = InvoiceService::query()->where('invoice_id', $invoice->id)->get();

            foreach ($lines as $line) {
                if (! $line->outsourcing_company_id || $line->snapshot_outsourcing_percentage === null) {
                    continue;
                }

                $discountShare = (float) $invoice->subtotal > 0
                    ? min(1, (float) $invoice->discount / (float) $invoice->subtotal)
                    : 0;
                $eligibleLineAmount = (float) $line->line_total * (1 - $discountShare);
                $eligibleBase = (float) $invoice->amount > 0
                    ? (float) $income->base_amount * ($eligibleLineAmount / (float) $invoice->amount)
                    : 0;
                $amount = $eligibleBase * ((float) $line->snapshot_outsourcing_percentage / 100);
                $accrual = OutsourcingAccrual::withTrashed()->updateOrCreate(
                    ['income_id' => $income->id, 'invoice_service_id' => $line->id],
                    [
                        'invoice_id' => $invoice->id, 'company_id' => $line->outsourcing_company_id,
                        'service_id' => $line->service_id, 'contract_service_id' => $line->contract_service_id,
                        'base_currency_id' => $income->base_currency_id, 'outsourcing_settlement_id' => null,
                        'earned_at' => $income->date, 'source_payment_base_amount' => $income->base_amount,
                        'eligible_base_amount' => round($eligibleBase, 6), 'percentage' => $line->snapshot_outsourcing_percentage,
                        'outsourcing_base_amount' => round($amount, 6), 'entry_type' => 'earned',
                        'adjustment_reason' => null, 'status' => 'pending', 'deleted_at' => null,
                    ],
                );
                $eligibleIds[] = $accrual->id;
            }
        }

        $stale = $income->outsourcingAccruals()->whereNull('outsourcing_settlement_id');
        if ($eligibleIds !== []) {
            $stale->whereNotIn('id', $eligibleIds);
        }
        $stale->update(['status' => 'void']);
    }

    public function voidForIncome(Income $income): void
    {
        $this->assertIncomeMutable($income);
        $income->outsourcingAccruals()->whereNull('outsourcing_settlement_id')->update(['status' => 'void']);
    }
}
