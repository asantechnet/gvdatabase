<?php

namespace App\Services;

use App\Models\ApplicantContract;
use App\Models\Income;
use App\Support\PaymentStatus;
use Illuminate\Support\Collection;

/**
 * Keeps the payment state of a contracted service line in step with the
 * receipts recorded against it.
 *
 * `amount_paid` is the sum of the linked incomes expressed in the line's own
 * currency; `payment_status` is derived from that amount and the payable
 * total (fee − discount). Both can be pinned by a finance user through the
 * `*_overridden` flags, in which case the automatic value is not written.
 */
class ApplicantContractPaymentService
{
    /** Money owed for the line, after the agreed discount. */
    public function payableTotal(ApplicantContract $line): float
    {
        return max(0.0, (float) ($line->fee ?? 0) - (float) ($line->discount ?? 0));
    }

    /** The rate the line is priced in, preferring the stored contract rate. */
    public function lineRate(ApplicantContract $line): float
    {
        $stored = (float) ($line->currency_rate ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        $line->loadMissing('currency');
        $rate = (float) ($line->currency?->rate ?? 0);

        return $rate > 0 ? $rate : 1.0;
    }

    /**
     * Total recorded against the line, expressed in the line currency. Pass an
     * income id to exclude it, e.g. when validating an edit of that receipt.
     */
    public function paidTotal(ApplicantContract $line, ?int $excludingIncomeId = null): float
    {
        $rate = $this->lineRate($line);
        $incomes = $this->incomesFor($line);
        if ($excludingIncomeId !== null) {
            $incomes = $incomes->reject(fn (Income $income) => (int) $income->id === $excludingIncomeId);
        }

        return round($incomes->sum(fn (Income $income) => $this->convert($income, $rate)), 6);
    }

    /** Overrides change the collectible balance, never the receipt ledger. */
    public function effectivePaidTotal(ApplicantContract $line, ?Income $excluding = null): float
    {
        if (! $line->amount_paid_overridden) {
            return $this->paidTotal($line, $excluding?->id);
        }

        $credit = $excluding && (int) $excluding->applicant_contract_id === (int) $line->id
            ? $this->convert($excluding, $this->lineRate($line)) : 0;

        return max(0, round((float) $line->amount_paid - $credit, 6));
    }

    /**
     * Express a raw payment in the line currency so it can be compared with the
     * remaining balance. Rates are units of local currency per base unit.
     */
    public function paymentInLineCurrency(ApplicantContract $line, float $amount, int $quantity = 1, ?float $receiptRate = null): float
    {
        $lineRate = $this->lineRate($line);
        $sourceRate = (float) ($receiptRate ?? 0);
        $sourceRate = $sourceRate > 0 ? $sourceRate : $lineRate;

        if ($sourceRate <= 0 || $lineRate <= 0) {
            return round($amount * $quantity, 6);
        }

        return round($amount * $quantity / $sourceRate * $lineRate, 6);
    }

    /**
     * Recalculate and persist the line. Explicit overrides are respected; a
     * line with no override flags is fully derived from its receipts.
     */
    public function recalculate(ApplicantContract $line): ApplicantContract
    {
        $paid = $this->paidTotal($line);
        $payable = $this->payableTotal($line);

        $updates = [];
        if (! $line->amount_paid_overridden) {
            $updates['amount_paid'] = $paid;
        }

        $effectivePaid = $line->amount_paid_overridden ? (float) ($line->amount_paid ?? 0) : $paid;
        if (! $line->payment_status_overridden) {
            $updates['payment_status'] = PaymentStatus::derive($effectivePaid, $payable);
        }

        if ($updates !== []) {
            $line->forceFill($updates);
            if ($line->isDirty(['amount_paid', 'payment_status'])) {
                $line->save();
            }
        }

        return $line;
    }

    /**
     * Refresh the line an income points at, plus the line it used to point at
     * when the receipt was re-linked.
     */
    public function refreshForIncome(Income $income, ?int $previousLineId = null): void
    {
        $ids = array_filter(array_unique([
            $income->applicant_contract_id ? (int) $income->applicant_contract_id : null,
            $previousLineId,
        ]));

        if ($ids === []) {
            return;
        }

        ApplicantContract::withTrashed()
            ->whereIn('id', $ids)
            ->orderBy('id')->lockForUpdate()
            ->get()
            ->each(fn (ApplicantContract $line) => $this->recalculate($line));
    }

    /**
     * Rates are stored as units of local currency per base unit, so a receipt
     * is converted with `amount / receiptRate * lineRate`.
     */
    private function convert(Income $income, float $lineRate): float
    {
        $amount = (float) $income->amount * (int) $income->quantity;
        $receiptRate = (float) ($income->rate ?? 0);
        $receiptRate = $receiptRate > 0 ? $receiptRate : $lineRate;

        if ($receiptRate <= 0 || $lineRate <= 0) {
            return round($amount, 6);
        }

        return round($amount / $receiptRate * $lineRate, 6);
    }

    /** @return Collection<int, Income> */
    private function incomesFor(ApplicantContract $line): Collection
    {
        return Income::query()
            ->where('applicant_contract_id', $line->id)
            ->get();
    }
}
