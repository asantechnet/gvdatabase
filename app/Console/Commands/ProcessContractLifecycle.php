<?php

namespace App\Console\Commands;

use App\Services\Contracts\ContractLifecycleService;
use App\Support\CompanyProfile;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ProcessContractLifecycle extends Command
{
    protected $signature = 'contracts:process-lifecycle';

    protected $description = 'Expire ended contracts and send idempotent expiration reminders';

    public function handle(ContractLifecycleService $service, CompanyProfile $profile): int
    {
        $result = $service->process(CarbonImmutable::now($profile->timezone()));

        $this->info("Expired {$result['expired']} contract(s); sent {$result['reminders']} reminder(s).");

        return self::SUCCESS;
    }
}
