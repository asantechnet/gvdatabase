<?php

namespace App\Console\Commands;

use App\Services\RecurringEvents\RecurringEventService;
use Illuminate\Console\Command;

class ProcessRecurringEvents extends Command
{
    protected $signature = 'recurring-events:process';
    protected $description = 'Create notifications for due recurring event occurrences';

    public function handle(RecurringEventService $service): int
    {
        $this->info("Processed {$service->processDueEvents()} recurring event(s).");
        return self::SUCCESS;
    }
}
