<?php

namespace App\Console\Commands;

use App\Services\CustomerServices\CustomerServiceLifecycleService;
use App\Support\CompanyProfile;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ProcessCustomerServiceLifecycle extends Command
{
    protected $signature = 'customer-services:process-lifecycle';
    protected $description = 'Pause customer services that reach their deadline and notify authorized users';

    public function handle(CustomerServiceLifecycleService $service, CompanyProfile $profile): int
    {
        $result = $service->process(CarbonImmutable::now($profile->timezone())->startOfDay());
        $this->info("Paused {$result['paused']} customer service(s); sent {$result['notifications']} notification(s).");
        return self::SUCCESS;
    }
}
