<?php

namespace App\Services\CustomerServices;

use App\Models\CustomerService;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerServiceLifecycleService
{
    /** @return array{paused: int, notifications: int} */
    public function process(CarbonImmutable $today): array
    {
        $recipients = $this->recipientIds();
        $paused = 0;
        $notifications = 0;

        CustomerService::query()
            ->with(['customer', 'service'])
            ->where('status', 'active')
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<=', $today->toDateString())
            ->each(function (CustomerService $assignment) use (&$paused, &$notifications, $recipients, $today): void {
                DB::transaction(function () use ($assignment, &$paused, &$notifications, $recipients, $today): void {
                    $locked = CustomerService::query()->lockForUpdate()->find($assignment->id);
                    if (! $locked || $locked->status !== 'active' || ! $locked->deadline?->lte($today)) {
                        return;
                    }

                    $locked->update(['status' => 'paused']);
                    $paused++;

                    $notification = Notification::firstOrCreate(
                        ['notification_key' => "customer_service.deadline.{$locked->id}.{$locked->deadline->toDateString()}"],
                        [
                            'type' => 'warning',
                            'title' => 'Customer service paused',
                            'message' => ($assignment->customer?->name ?? 'Customer').' service '.($assignment->service?->type ?? 'service').' reached its deadline and was paused.',
                            'data' => ['customer_id' => $locked->customer_id, 'service_id' => $locked->service_id, 'deadline' => $locked->deadline->toDateString()],
                            'date_time' => now(),
                            'go_to_url' => '/all-customers?customerId='.$locked->customer_id,
                        ],
                    );
                    $notification->deliverTo($recipients);
                    if ($notification->wasRecentlyCreated) {
                        $notifications++;
                    }
                });
            });

        return compact('paused', 'notifications');
    }

    /** @return Collection<int, int> */
    private function recipientIds(): Collection
    {
        return User::query()->where('acc_status', 'active')->where(function ($query) {
            $query->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))
                ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->whereIn('name', ['view_customers', 'manage_customer_services']));
        })->pluck('id');
    }
}
