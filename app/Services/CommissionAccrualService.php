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
                ->with('commissionRecipients')
                ->get();

            foreach ($lines as $line) {
                $discountShare = (float) $invoice->subtotal > 0
                    ? min(1, (float) $invoice->discount / (float) $invoice->subtotal)
                    : 0;
                $eligibleLineAmount = (float) $line->line_total * (1 - $discountShare);
                $eligibleBase = (float) $invoice->amount > 0
                    ? (float) $income->base_amount * ($eligibleLineAmount / (float) $invoice->amount)
                    : 0;
                $recipients = $line->commissionRecipients->mapWithKeys(fn ($employee) => [
                    "delivery:{$employee->id}" => [
                        'role' => 'delivery',
                        'employee_id' => $employee->id,
                        'percentage' => $employee->pivot->percentage,
                    ],
                ])->all() + [
                    'marketing' => ['employee_id' => $line->marketer_employee_id, 'percentage' => $line->snapshot_marketer_commission_percentage],
                ];

                // Legacy invoice lines predate recipient snapshots. Their
                // single delivery fields remain a valid source of accruals.
                if ($line->commissionRecipients->isEmpty() && $line->employee_id) {
                    $recipients['delivery:'.$line->employee_id] = [
                        'role' => 'delivery',
                        'employee_id' => $line->employee_id,
                        'percentage' => $line->snapshot_commission_percentage,
                    ];
                }

                foreach ($recipients as $key => $recipient) {
                    $role = $recipient['role'] ?? $key;
                    if (! $recipient['employee_id'] || $recipient['percentage'] === null) {
                        continue;
                    }

                    $commission = $eligibleBase * ((float) $recipient['percentage'] / 100);
                    $accrual = CommissionAccrual::withTrashed()->updateOrCreate(
                    [
                        'income_id' => $income->id,
                        'invoice_service_id' => $line->id,
                        'employee_id' => $recipient['employee_id'],
                        'commission_role' => $role,
                    ],
                    [
                        'invoice_id' => $invoice->id,
                        'employee_id' => $recipient['employee_id'],
                        'service_id' => $line->service_id,
                        'customer_service_id' => $line->customer_service_id,
                        'base_currency_id' => $income->base_currency_id,
                        'commission_settlement_id' => null,
                        'entry_type' => 'earned',
                        'commission_role' => $role,
                        'earned_at' => $income->date,
                        'source_payment_base_amount' => $income->base_amount,
                        'eligible_base_amount' => round($eligibleBase, 6),
                        'percentage' => $recipient['percentage'],
                        'commission_base_amount' => round($commission, 6),
                        'status' => 'pending',
                        'deleted_at' => null,
                    ],
                    );
                    $eligibleIds[] = $accrual->id;
                }
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
