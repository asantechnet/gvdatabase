<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\CommissionAccrual;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Employee;
use App\Support\BuiltInService;
use Illuminate\Support\Carbon;

/**
 * Turns completed visa cases into employee commission accruals.
 *
 * The trigger is the applicant workflow itself: commission is earned when a
 * case reaches the terminal `completed` stage (see `ApplicantStage`), which is
 * the point where the office considers the case finished and the contracted
 * services delivered. Every service line of every non-cancelled contract on
 * that case then earns at the employee's per-service rate, converted to the
 * base currency.
 *
 * A case that is anywhere else — still in the pipeline, or closed at one of the
 * exit stages (`cancelled`, `rejected`, `withdrawn`) — earns nothing, and any
 * accrual that is still unsettled is voided. Money already settled or paid is
 * never reversed.
 *
 * The former `CommissionAccrualService` synced from invoices and income
 * receipts, neither of which exists any more; the pre-2026 ledger also keyed on
 * a completed *contract*, which nothing in the workflow ever set.
 */
class CommissionService
{
    /**
     * (Re)build the unsettled accruals for one case.
     *
     * Safe to call repeatedly: accruals are keyed on the contract line and the
     * employee, so re-running updates the amounts instead of duplicating them.
     * Settled accruals are never touched.
     *
     * @param  Carbon|string|null  $earnedAt  When the case completed. Stage
     *                                        transitions pass the timestamp of
     *                                        their history row so an accrual is
     *                                        dated by the completion, not by
     *                                        whenever the row was written.
     */
    public function syncForApplicant(Applicant $applicant, Carbon|string|null $earnedAt = null): void
    {
        if (! $applicant->isCaseCompleted()) {
            $this->voidForApplicant($applicant);

            return;
        }

        $applicant->loadMissing([
            'contracts.applicantContracts.currency',
            'contracts.applicantContracts.soldByEmployee',
        ]);

        // Nothing can be valued without a base currency. Skip instead of
        // failing: completing a case must never break because the finance
        // settings are incomplete, and the reconcile action rebuilds the case
        // once a base currency exists.
        $base = Currency::query()->where('is_base', true)->first();
        if (! $base) {
            return;
        }

        $earnedOn = $this->earnedOn($applicant, $earnedAt);
        $eligibleIds = [];

        foreach ($applicant->contracts as $contract) {
            if (! $this->earnsCommission($contract)) {
                continue;
            }

            foreach ($this->lineAccruals($contract, $base, $earnedOn) as $id) {
                $eligibleIds[] = $id;
            }
        }

        $this->voidStale($applicant, $eligibleIds);
    }

    /**
     * Void the unsettled accruals of a case that is no longer completed
     * (re-opened, cancelled, rejected, withdrawn).
     */
    public function voidForApplicant(Applicant $applicant): void
    {
        // Retired test-offer/academy accruals are historical money. A case
        // stage change must never void them merely because CASE_FREE now owns
        // no workflow.
        $this->accrualsFor($applicant)
            ->whereNull('commission_settlement_id')
            ->whereHas('applicantContract', fn ($query) => $query
                ->whereIn('service', BuiltInService::CASE_BEARING))
            ->update(['status' => 'void']);
    }

    /**
     * Re-sync the case behind a contract. Kept so contract-side paths (status
     * changes, manual reconciliation) reach the same rule: the case decides.
     */
    public function syncForContract(Contract $contract): void
    {
        $applicant = $contract->applicant;

        if (! $applicant) {
            // Employment contracts have no case and never accrue commission.
            $this->voidForContract($contract);

            return;
        }

        $this->syncForApplicant($applicant);
    }

    /**
     * Void the unsettled accruals of a contract that is no longer earning
     * (cancelled or terminated). Settled money is never reversed here.
     */
    public function voidForContract(Contract $contract): void
    {
        CommissionAccrual::query()
            ->where('contract_id', $contract->id)
            ->where('entry_type', 'earned')
            ->whereNull('commission_settlement_id')
            ->update(['status' => 'void']);
    }

    /**
     * Whether a contract's service lines may earn at all.
     *
     * A draft is not issued yet and can still be deleted, so it starts earning
     * when it is issued (the contract hook re-syncs on that status change). An
     * ended agreement — cancelled or terminated — stops earning immediately.
     */
    private function earnsCommission(Contract $contract): bool
    {
        return ! $contract->is_cancelled
            && in_array($contract->status, ['active', 'completed'], true);
    }

    /**
     * Build the accruals for every service line of one contract and return the
     * ids that are still eligible (the rest are voided by `voidStale`).
     *
     * @return list<int>
     */
    private function lineAccruals(Contract $contract, Currency $base, string $earnedOn): array
    {
        $eligibleIds = [];

        foreach ($contract->applicantContracts as $line) {
            $employeeIds = array_values(array_unique(array_filter([
                $contract->employee_id,
                $contract->sold_by_employee_id,
                $line->sold_by_employee_id,
            ])));

            if ($employeeIds === []) {
                continue;
            }

            $eligibleBase = $this->lineBaseAmount($line, $base);
            if ($eligibleBase <= 0) {
                continue;
            }

            $lineService = $line->service ?: $contract->service;

            // A case-free service never earns new commission. Preserve any
            // accrual it already has instead of letting voidStale retire it.
            if (BuiltInService::isCaseFree($lineService)) {
                $eligibleIds = [...$eligibleIds, ...CommissionAccrual::query()
                    ->where('applicant_contract_id', $line->id)
                    ->where('entry_type', 'earned')
                    ->whereNull('commission_settlement_id')
                    ->pluck('id')->all()];

                continue;
            }

            foreach ($employeeIds as $employeeId) {
                $employee = Employee::with('serviceCommissions')->find($employeeId);
                if (! $employee || ! $employee->is_commission_based) {
                    continue;
                }

                $rate = $employee->serviceCommissions->firstWhere('service', $lineService);
                $percentage = $rate ? (float) $rate->percentage : 0.0;
                if ($percentage <= 0) {
                    continue;
                }

                $commission = round($eligibleBase * ($percentage / 100), 6);

                $accrual = CommissionAccrual::withTrashed()->firstOrNew([
                    'contract_id' => $contract->id,
                    'applicant_contract_id' => $line->id,
                    'employee_id' => $employee->id,
                ]);

                // Money already attached to a settlement is immutable. Keeping
                // it in the eligible set prevents `voidStale` from voiding it,
                // and skipping the write prevents a sync from detaching or
                // resurrecting it as a pending accrual.
                if ($accrual->exists && $accrual->commission_settlement_id !== null) {
                    $eligibleIds[] = $accrual->id;

                    continue;
                }

                $accrual->fill([
                    'service' => $lineService,
                    'base_currency_id' => $base->id,
                    'commission_settlement_id' => null,
                    'entry_type' => 'earned',
                    'earned_at' => $earnedOn,
                    'source_base_amount' => $eligibleBase,
                    'eligible_base_amount' => $eligibleBase,
                    'percentage' => $percentage,
                    'commission_base_amount' => $commission,
                    'status' => 'pending',
                    'deleted_at' => null,
                ])->save();

                $eligibleIds[] = $accrual->id;
            }
        }

        return $eligibleIds;
    }

    /**
     * Void this case's unsettled accruals that no longer earn. Accruals already
     * attached to a settlement are left alone.
     *
     * @param  list<int>  $eligibleIds
     */
    private function voidStale(Applicant $applicant, array $eligibleIds): void
    {
        $stale = $this->accrualsFor($applicant)->whereNull('commission_settlement_id');

        if ($eligibleIds !== []) {
            $stale->whereNotIn('id', $eligibleIds);
        }

        $stale->update(['status' => 'void']);
    }

    /** Earned accruals belonging to any contract of this case. */
    private function accrualsFor(Applicant $applicant)
    {
        return CommissionAccrual::query()
            ->whereIn('contract_id', $applicant->contracts()->select('contracts.id'))
            ->where('entry_type', 'earned');
    }

    /**
     * The date the case completed. Falls back to today when a caller completes
     * a case without a stage history row (the workflow always writes one).
     */
    private function earnedOn(Applicant $applicant, Carbon|string|null $earnedAt): string
    {
        if ($earnedAt) {
            return Carbon::parse($earnedAt)->toDateString();
        }

        $completedAt = ApplicantStageHistory::query()
            ->where('applicant_id', $applicant->id)
            ->whereHas('newStage', fn ($stage) => $stage->where('key', ApplicantStage::COMPLETED_KEY))
            ->latest('changed_at')
            ->value('changed_at');

        return $completedAt ? Carbon::parse($completedAt)->toDateString() : now()->toDateString();
    }

    /** The agreed line amount (`fee − discount`) expressed in the base currency. */
    private function lineBaseAmount(ApplicantContract $line, Currency $base): float
    {
        $amount = $line->payableTotal();
        if ($amount <= 0) {
            return 0.0;
        }

        $rate = (float) ($line->currency_rate ?? 0);
        if ($rate <= 0) {
            $rate = $line->currency
                ? ($line->currency->is($base) ? 1.0 : (float) $line->currency->rate)
                : 1.0;
        }
        if ($rate <= 0) {
            $rate = 1.0;
        }

        return round($amount / $rate, 6);
    }
}
