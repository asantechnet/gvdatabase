<?php

namespace App\Console\Commands;

use App\Services\ContractExpiryNotificationService;
use App\Support\CompanyProfile;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendContractExpiryAlerts extends Command
{
    protected $signature = 'contracts:send-expiry-alerts {--days=30 : The reminder window in days}';

    protected $description = 'Notify contract owners when a contract deadline is inside the reminder window';

    public function handle(ContractExpiryNotificationService $notifications, CompanyProfile $profile): int
    {
        $window = max(1, (int) $this->option('days'));
        $sent = $notifications->sendExpiringReminders(CarbonImmutable::now($profile->timezone()), $window);
        $this->info("Sent {$sent} contract expiry reminder(s).");

        return self::SUCCESS;
    }
}
