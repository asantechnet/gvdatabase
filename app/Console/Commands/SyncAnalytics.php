<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Contract;
use App\Models\DailyAnalytics;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MonthlyAnalytics;
use App\Models\Task;
use App\Models\WorthyEmployee;
use App\Services\AutomationRunService;
use App\Support\CompanyProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class SyncAnalytics extends Command
{
    protected $signature = 'analytics:sync {--from=} {--to=} {--force : Recalculate a completed range}';
    protected $description = 'Idempotently build daily and monthly dashboard/report aggregates';

    public function handle(CompanyProfile $company, AutomationRunService $runs): int
    {
        try {
            $defaultDate = now($company->timezone())->subDay()->toDateString();
            $end = Carbon::parse($this->option('to') ?: $defaultDate, $company->timezone())->endOfDay();
            $start = Carbon::parse($this->option('from') ?: $defaultDate, $company->timezone())->startOfDay();
            if ($end->lt($start)) throw new \InvalidArgumentException('--to must be on or after --from.');
            $period = $start->toDateString().':'.$end->toDateString();
            $result = $runs->execute('analytics_sync', $period, ['from' => $start->toDateString(), 'to' => $end->toDateString()], (bool) $this->option('force'), function () use ($start, $end): array {
                $count = 0;
                for ($day = $start->copy(); $day->lte($end); $day->addDay()) { $this->syncDay($day); $count++; }
                $months = 0;
                for ($month = $start->copy()->startOfMonth(); $month->lte($end); $month->addMonthNoOverflow()) { $this->syncMonth($month); $months++; }
                return ['daily_rows' => $count, 'monthly_rows' => $months];
            });
            $this->info(json_encode($result, JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception); $this->error($exception->getMessage()); return self::FAILURE;
        }
    }

    private function syncDay(Carbon $day): void
    {
        $date = $day->toDateString(); $utcStart = $day->copy()->startOfDay()->utc(); $utcEnd = $day->copy()->endOfDay()->utc();
        $attendance = Attendance::query()->whereDate('date', $date);
        DailyAnalytics::updateOrCreate(['date' => $date], [
            'attendance_present' => (clone $attendance)->where('status', 'present')->count(),
            'attendance_absent' => (clone $attendance)->where('status', 'absent')->count(),
            'attendance_paid_leave' => (clone $attendance)->where('status', 'paid-leave')->count(),
            'attendance_incomplete' => (clone $attendance)->where('status', 'present')->where('missing_punch_state', '!=', 'none')->count(),
            'worked_minutes' => (int) (clone $attendance)->sum('net_worked_minutes'), 'overtime_minutes' => (int) (clone $attendance)->sum('overtime_minutes'),
            // These are booked snapshot totals for the analytics archive. Live
            // dashboard and report totals are converted from source currency.
            'income_total' => (float) Income::whereDate('date', $date)->sum('base_amount'), 'expense_total' => (float) Expense::whereDate('date', $date)->sum('base_amount'),
            'tasks_created' => Task::whereBetween('created_at', [$utcStart, $utcEnd])->count(), 'tasks_completed' => Task::whereBetween('completed_at', [$utcStart, $utcEnd])->count(),
            'contracts_created' => Contract::whereBetween('created_at', [$utcStart, $utcEnd])->count(), 'contracts_cancelled' => Contract::whereBetween('cancelled_at', [$utcStart, $utcEnd])->count(),
            'employees_onboarded' => Employee::whereDate('join_date', $date)->count(), 'bonus_nominations' => WorthyEmployee::whereBetween('created_at', [$utcStart, $utcEnd])->count(),
            'bonuses_granted' => WorthyEmployee::whereBetween('granted_at', [$utcStart, $utcEnd])->count(),
            'bonus_base_total' => (float) WorthyEmployee::whereBetween('granted_at', [$utcStart, $utcEnd])->sum('base_amount'), 'calculated_at' => now('UTC'),
        ]);
    }

    private function syncMonth(Carbon $month): void
    {
        $rows = DailyAnalytics::whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
        $fields = ['attendance_present', 'attendance_absent', 'attendance_paid_leave', 'attendance_incomplete', 'worked_minutes', 'overtime_minutes', 'income_total', 'expense_total', 'tasks_created', 'tasks_completed', 'contracts_created', 'contracts_cancelled', 'employees_onboarded', 'bonus_nominations', 'bonuses_granted', 'bonus_base_total'];
        $values = collect($fields)->mapWithKeys(fn ($field) => [$field => (float) (clone $rows)->sum($field)])->all();
        MonthlyAnalytics::updateOrCreate(['year' => $month->year, 'month' => $month->month], [...$values, 'calculated_at' => now('UTC')]);
    }
}
