<?php

namespace App\Console\Commands;

use App\Services\EducationalWorkflowNotificationService;
use App\Support\CompanyProfile;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendApplicantReminders extends Command
{
    protected $signature = 'applicants:send-reminders';

    protected $description = 'Send idempotent reminders for educational visa cases stalled in one stage';

    public function handle(EducationalWorkflowNotificationService $notifications, CompanyProfile $profile): int
    {
        $sent = $notifications->sendStalledCaseReminders(CarbonImmutable::now($profile->timezone()));
        $this->info("Sent {$sent} applicant case reminder(s).");

        return self::SUCCESS;
    }
}
