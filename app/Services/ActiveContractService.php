<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for the Active Contract business rule
 * (Implementation Plan §21): a party is eligible for
 * applicant-specific operational expenses when the applicant exists
 * AND at least one relevant contract is active.
 *
 * Active means status `active` on a non-cancelled contract. The
 * definition lives here — never duplicated across controllers —
 * so a future change (e.g. treating `renewed` as active) lands once.
 */
class ActiveContractService
{
    public function query(?int $applicantId = null)
    {
        $query = Contract::query()->where('status', 'active')->where('is_cancelled', false);
        if ($applicantId !== null) {
            $query->where('applicant_id', $applicantId);
        }

        return $query;
    }

    public function hasActiveContract(int $applicantId): bool
    {
        return $this->query($applicantId)->exists();
    }

    public function activeContractCount(?int $applicantId = null): int
    {
        return $this->query($applicantId)->count();
    }

    /**
     * @throws ValidationException
     */
    public function requireActiveContractForExpense(?int $applicantId): void
    {
        if ($applicantId === null) {
            return;
        }

        if (! $this->hasActiveContract($applicantId)) {
            throw ValidationException::withMessages(['applicant_id' => ['Applicant-specific expenses require the applicant to have at least one active contract.']]);
        }
    }
}
