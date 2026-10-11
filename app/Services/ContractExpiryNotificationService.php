<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Warns the people responsible for a contract when its end date falls inside
 * the reminder window (default one month).
 *
 * Notifications are keyed by contract and deadline, so the command can run
 * daily without repeating the same warning for the same end date. When a
 * contract is renewed and its end date moves, a fresh notification is allowed.
 */
class ContractExpiryNotificationService
{
    public const WINDOW_DAYS = 30;

    /**
     * @return int the number of new notifications created (delivery may be
     *             suppressed by a recipient's notification preferences)
     */
    public function sendExpiringReminders(CarbonImmutable $today, int $windowDays = self::WINDOW_DAYS): int
    {
        $from = $today->startOfDay();
        $until = $from->addDays(max(1, $windowDays));
        $sent = 0;

        Contract::query()
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $from->toDateString())
            ->whereDate('end_date', '<=', $until->toDateString())
            ->where('is_cancelled', false)
            ->whereIn('status', ['active', 'completed'])
            ->with([
                'contractType:id,slug',
                'employee.user:id',
                'soldByEmployee.user:id',
                'applicant.responsibleEmployee.user:id',
            ])
            ->orderBy('id')
            ->each(function (Contract $contract) use ($from, &$sent): void {
                $endDate = CarbonImmutable::instance($contract->end_date)->startOfDay();
                $daysRemaining = (int) $from->diffInDays($endDate);
                $recipients = $this->recipientUserIds($contract);
                if ($recipients->isEmpty()) {
                    return;
                }

                $created = $this->notifyOnce(
                    "contract.expiring.{$contract->id}.{$endDate->toDateString()}",
                    'warning',
                    'Contract deadline approaching',
                    "{$contract->contract_number} ends on {$endDate->toDateString()} ({$daysRemaining} day(s) left).",
                    [
                        'translation_key' => 'contract.expiring',
                        'contract_id' => $contract->id,
                        'contract_number' => $contract->contract_number,
                        'service' => $contract->service,
                        'counterparty' => $contract->counterparty,
                        'end_date' => $endDate->toDateString(),
                        'days_remaining' => $daysRemaining,
                    ],
                    $this->urlFor($contract),
                    $recipients,
                );

                if ($created) {
                    $sent++;
                }
            });

        return $sent;
    }

    /** @return Collection<int, int> */
    private function recipientUserIds(Contract $contract): Collection
    {
        $ids = collect([
            $contract->employee?->user_id,
            $contract->soldByEmployee?->user_id,
            $contract->applicant?->responsibleEmployee?->user_id,
        ])->filter()->map(fn ($id) => (int) $id)->unique();

        if ($ids->isNotEmpty()) {
            return $ids;
        }

        // Ownerless contracts still matter to whoever administers contracts.
        return $this->usersWithPermission('view_contracts');
    }

    private function urlFor(Contract $contract): string
    {
        if ($contract->service !== null) {
            return '/contracts/'.str_replace('_', '-', $contract->service).'?contractId='.$contract->id;
        }

        return ($contract->contractType?->slug === 'employment' ? '/employee-contracts' : '/other-contracts')
            .'?contractId='.$contract->id;
    }

    /** @return Collection<int, int> */
    private function usersWithPermission(string $permission): Collection
    {
        return User::query()
            ->where('acc_status', 'active')
            ->where(function ($query) use ($permission): void {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))
                    ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->where('name', $permission));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /** @param Collection<int, int> $recipientIds */
    private function notifyOnce(string $key, string $type, string $title, string $message, array $data, string $url, Collection $recipientIds): bool
    {
        $recipientIds = $recipientIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($recipientIds->isEmpty()) {
            return false;
        }

        $notification = Notification::query()->firstOrCreate(
            ['notification_key' => $key],
            compact('type', 'title', 'message', 'data') + ['date_time' => now(), 'go_to_url' => $url],
        );
        if ($notification->wasRecentlyCreated) {
            $notification->deliverTo($recipientIds);
        }

        return $notification->wasRecentlyCreated;
    }
}
