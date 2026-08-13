<?php

namespace App\Console\Commands;

use App\Services\AutomationRunService;
use App\Services\TaskBonusCalculationService;
use App\Support\CompanyProfile;
use Illuminate\Console\Command;
use Throwable;

class CalculateTaskBonuses extends Command
{
    protected $signature = 'bonuses:calculate {--month=} {--force : Recalculate an already completed month}';
    protected $description = 'Score task contributions and create or update approval-required monthly bonus nominations';

    public function handle(TaskBonusCalculationService $bonuses, AutomationRunService $runs, CompanyProfile $company): int
    {
        try {
            $month = $this->option('month') ?: now($company->timezone())->subMonthNoOverflow()->format('Y-m');
            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) throw new \InvalidArgumentException('Use --month=YYYY-MM.');
            $result = $runs->execute('bonus_calculation', $month, ['month' => $month], (bool) $this->option('force'), fn () => $bonuses->calculate($month));
            $this->info(json_encode($result, JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception); $this->error($exception->getMessage()); return self::FAILURE;
        }
    }
}
