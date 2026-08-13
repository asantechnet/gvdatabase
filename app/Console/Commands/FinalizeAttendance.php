<?php

namespace App\Console\Commands;

use App\Services\MonthlyAttendanceProcessor;
use App\Support\CompanyProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class FinalizeAttendance extends Command
{
    protected $signature = 'attendance:finalize {--from=} {--to=} {--force : Recalculate unchanged sessions} {--no-missing : Do not create absent/leave rows}';
    protected $description = 'Finalize attendance sessions and idempotently build monthly employee summaries';

    public function handle(MonthlyAttendanceProcessor $processor, CompanyProfile $company): int
    {
        try {
            [$start, $end] = $this->range($company->timezone());
            $this->info("Processing attendance {$start->toDateString()} through {$end->toDateString()} ({$company->timezone()}).");
            $result = $processor->process($start, $end, ! $this->option('no-missing'), (bool) $this->option('force'));
            $this->info("Created {$result['created_missing']} missing rows, recalculated {$result['recalculated']} sessions, updated {$result['summaries']} summaries.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function range(string $timezone): array
    {
        if ((bool) $this->option('from') xor (bool) $this->option('to')) {
            throw new \InvalidArgumentException('Use --from and --to together.');
        }
        if ($this->option('from')) {
            $start = Carbon::parse((string) $this->option('from'), $timezone)->startOfDay();
            $end = Carbon::parse((string) $this->option('to'), $timezone)->endOfDay();
            if ($end->lt($start)) throw new \InvalidArgumentException('--to must be on or after --from.');
            return [$start, $end];
        }
        $previous = now($timezone)->subMonthNoOverflow();
        return [$previous->copy()->startOfMonth(), $previous->copy()->endOfMonth()];
    }
}
