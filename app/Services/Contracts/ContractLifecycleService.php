<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContractLifecycleService
{
    /** @var list<int> */
    private const REMINDER_THRESHOLDS = [0, 1, 3, 7, 14, 30];

    /**
     * Expire ended contracts and issue one reminder per urgency threshold.
     * Notification keys include the end date, so changing a draft's term starts
     * a fresh, idempotent reminder sequence without duplicating old reminders.
     *
     * @return array{expired: int, reminders: int}
     */
    public function process(CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $recipientIds = $this->contractManagerIds();
        $expiredCount = 0;
        $reminderCount = 0;

        Contract::query()
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->where('is_cancelled', false)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today->toDateString())
            ->orderBy('id')
            ->each(function (Contract $contract) use (&$expiredCount, $recipientIds, $today): void {
                DB::transaction(function () use ($contract, &$expiredCount, $recipientIds, $today): void {
                    $locked = Contract::query()->lockForUpdate()->find($contract->id);
                    if (! $locked || strtolower($locked->status) !== 'active' || $locked->is_cancelled || ! $locked->end_date?->lt($today)) {
                        return;
                    }

                    $locked->update(['status' => 'expired']);
                    $expiredCount++;

                    $this->notifyOnce(
                        key: "contract.expired.{$locked->id}.{$locked->end_date->toDateString()}",
                        type: 'error',
                        title: 'Contract expired',
                        message: $this->label($locked).' expired on '.$locked->end_date->toDateString().'. Review or renew it.',
                        contract: $locked,
                        recipientIds: $recipientIds,
                        daysRemaining: (int) $today->diffInDays($locked->end_date, false),
                    );
                });
            });

        if ($recipientIds->isEmpty()) {
            return ['expired' => $expiredCount, 'reminders' => 0];
        }

        Contract::query()
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->where('is_cancelled', false)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $today->addDays(30)->toDateString()])
            ->orderBy('end_date')
            ->orderBy('id')
            ->each(function (Contract $contract) use (&$reminderCount, $recipientIds, $today): void {
                $daysRemaining = (int) $today->diffInDays($contract->end_date, false);
                $threshold = collect(self::REMINDER_THRESHOLDS)->first(fn (int $days) => $daysRemaining <= $days);
                if ($threshold === null) {
                    return;
                }

                $created = $this->notifyOnce(
                    key: "contract.expiry.{$contract->id}.{$contract->end_date->toDateString()}.{$threshold}",
                    type: $threshold <= 7 ? 'error' : 'warning',
                    title: $daysRemaining === 0 ? 'Contract expires today' : 'Contract expiration approaching',
                    message: $daysRemaining === 0
                        ? $this->label($contract).' expires today.'
                        : $this->label($contract)." expires in {$daysRemaining} ".($daysRemaining === 1 ? 'day' : 'days').'.',
                    contract: $contract,
                    recipientIds: $recipientIds,
                    daysRemaining: $daysRemaining,
                    threshold: $threshold,
                );

                if ($created) {
                    $reminderCount++;
                }
            });

        return ['expired' => $expiredCount, 'reminders' => $reminderCount];
    }

    /** @param Collection<int, int> $recipientIds */
    private function notifyOnce(
        string $key,
        string $type,
        string $title,
        string $message,
        Contract $contract,
        Collection $recipientIds,
        int $daysRemaining,
        ?int $threshold = null,
    ): bool {
        if ($recipientIds->isEmpty()) {
            return false;
        }

        $notification = Notification::query()->firstOrCreate(
            ['notification_key' => $key],
            [
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => [
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'end_date' => $contract->end_date?->toDateString(),
                    'days_remaining' => $daysRemaining,
                    'reminder_threshold' => $threshold,
                ],
                'date_time' => now(),
                'go_to_url' => $this->contractUrl($contract),
            ],
        );

        $notification->deliverTo($recipientIds);

        return $notification->wasRecentlyCreated;
    }

    /** @return Collection<int, int> */
    private function contractManagerIds(): Collection
    {
        return User::query()
            ->where('acc_status', 'active')
            ->where(function ($query) {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))
                    ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->where('name', 'view_contracts'));
            })
            ->pluck('id');
    }

    private function label(Contract $contract): string
    {
        return collect([$contract->contract_number, $contract->name, $contract->counterparty])
            ->filter()
            ->implode(' · ') ?: "Contract #{$contract->id}";
    }

    private function contractUrl(Contract $contract): string
    {
        $path = match ($contract->type_slug) {
            'employment' => '/employee-contracts',
            'company' => '/company-contracts',
            default => '/contracts',
        };

        return $path.'?contractId='.$contract->id;
    }
}
