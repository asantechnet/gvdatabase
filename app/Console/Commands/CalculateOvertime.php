<?php

namespace App\Console\Commands;

use App\Services\MonthlyAttendanceProcessor;
use App\Support\CompanyProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class CalculateOvertime extends Command
{
    protected $signature = 'attendance:calculate-overtime {--from=} {--to=} {--force}';
    protected $description = 'Recalculate changed attendance sessions without creating missing attendance rows';

    public function handle(MonthlyAttendanceProcessor $processor, CompanyProfile $company): int
    {
        try {
            $end = $this->option('to') ? Carbon::parse((string) $this->option('to'), $company->timezone())->endOfDay() : now($company->timezone())->subDay()->endOfDay();
            $start = $this->option('from') ? Carbon::parse((string) $this->option('from'), $company->timezone())->startOfDay() : $end->copy()->startOfMonth();
            if ($end->lt($start)) throw new \InvalidArgumentException('--to must be on or after --from.');
            $result = $processor->process($start, $end, false, (bool) $this->option('force'), false);
            $this->info("Recalculated {$result['recalculated']} changed sessions and refreshed {$result['summaries']} summaries.");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
