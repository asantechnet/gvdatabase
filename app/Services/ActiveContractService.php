<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for the Active Contract business rule
 * (Implementation Plan §21): a customer is eligible for
 * customer-specific operational expenses when the customer exists
 * AND at least one relevant contract is active.
 *
 * Active means status `active` on a non-cancelled contract. The
 * definition lives here — never duplicated across controllers —
 * so a future change (e.g. treating `renewed` as active) lands once.
 */
class ActiveContractService
{
    public function query(?int $customerId = null)
    {
        $query = Contract::query()->where('status', 'active')->where('is_cancelled', false);
        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        return $query;
    }

    public function hasActiveContract(int $customerId): bool
    {
        return $this->query($customerId)->exists();
    }

    public function activeContractCount(?int $customerId = null): int
    {
        return $this->query($customerId)->count();
    }

    /**
     * @throws ValidationException
     */
    public function requireActiveContractForExpense(?int $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        if (! $this->hasActiveContract($customerId)) {
            throw ValidationException::withMessages(['customer_id' => ['Customer-specific expenses require the customer to have at least one active contract.']]);
        }
    }
}
