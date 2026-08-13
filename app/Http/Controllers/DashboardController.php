<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Task;
use App\Models\User;
use App\Models\DailyAnalytics;
use App\Support\CompanyProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request, CompanyProfile $companyProfile): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $now = now($companyProfile->timezone());
        $tasks = $this->visibleTasks($user);
        $canViewAllTasks = $user->hasPermission('view_all_tasks');
        $activeTasks = (clone $tasks)->where('progress', '<', 100);
        $canViewFinance = $user->hasPermission('view_financial_reports')
            || $user->hasPermission('view_incomes');
        $canViewAttendance = $user->hasPermission('view_attendance');

        $weeklyActivity = $this->weeklyTaskActivity($tasks, $now, $canViewAllTasks);
        $dailyTaskCompletion = $this->dailyTaskCompletion($tasks, $now, $canViewAllTasks);
        $attendanceTrend = $canViewAttendance ? $this->attendanceTrend($now) : null;
        $cashFlow = $canViewFinance ? $this->cashFlow($now) : null;
        $baseCurrency = Currency::query()->where('is_base', true)->first()
            ?? Currency::query()->orderBy('id')->first();

        $currentMonthIncome = $canViewFinance
            ? (float) Income::query()->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->sum('total_amount')
            : null;
        $previousMonthIncome = $canViewFinance
            ? (float) Income::query()->whereBetween('date', [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ])->sum('total_amount')
            : null;

        $currentMonthAttendance = $canViewAttendance
            ? $this->attendanceSummary($now->copy()->startOfMonth(), $now->copy()->endOfMonth())
            : null;

        $activeTaskRows = (clone $activeTasks)
            ->with('board:id,title')
            ->orderByDesc('progress')
            ->orderBy('due_date')
            ->limit(8)
            ->get()
            ->map(fn (Task $task) => $this->taskRow($task, $now))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'capabilities' => [
                    'finance' => $canViewFinance,
                    'attendance' => $canViewAttendance,
                ],
                'currency' => [
                    'code' => $baseCurrency?->code ?? 'USD',
                    'symbol' => $baseCurrency?->symbol ?? '$',
                    'decimal_places' => (int) ($baseCurrency?->decimal_places ?? 2),
                ],
                'summary' => [
                    'monthly_income' => $currentMonthIncome,
                    'previous_month_income' => $previousMonthIncome,
                    'active_tasks' => (clone $activeTasks)->count(),
                    'nearing_completion_tasks' => (clone $activeTasks)->where('progress', '>=', 75)->count(),
                    'completed_tasks_this_month' => (clone $tasks)
                        ->where('progress', 100)
                        ->whereBetween('completed_at', $this->utcRange($now->copy()->startOfMonth(), $now->copy()->endOfMonth()))
                        ->count(),
                    'overdue_tasks' => (clone $activeTasks)->whereDate('due_date', '<', $now->toDateString())->count(),
                    'average_task_progress' => round((float) ((clone $tasks)->avg('progress') ?? 0), 1),
                    'task_completion_rate' => $this->percentage(
                        (clone $tasks)->where('progress', 100)->count(),
                        (clone $tasks)->count(),
                    ),
                    'attendance_rate' => $currentMonthAttendance['rate'] ?? null,
                    'attendance_present' => $currentMonthAttendance['present'] ?? null,
                    'attendance_marked' => $currentMonthAttendance['marked'] ?? null,
                ],
                'sparklines' => [
                    'income' => $canViewFinance ? $this->dailyIncome($now) : $dailyTaskCompletion['completed'],
                    'active_tasks' => $dailyTaskCompletion['created'],
                    'attendance' => $attendanceTrend['rates'] ?? $dailyTaskCompletion['completion_rates'],
                ],
                'weekly_activity' => $weeklyActivity,
                'secondary_chart' => $attendanceTrend ?? [
                    'labels' => $dailyTaskCompletion['labels'],
                    'rates' => $dailyTaskCompletion['completion_rates'],
                ],
                'tertiary_chart' => $cashFlow ?? [
                    'labels' => $activeTaskRows->pluck('title')->take(5)->values(),
                    'values' => $activeTaskRows->pluck('progress')->take(5)->values(),
                    'current_value' => round((float) ((clone $tasks)->avg('progress') ?? 0), 1),
                ],
                'active_tasks' => $activeTaskRows,
            ],
        ]);
    }

    /** Retained for clients that still use the original chart endpoint. */
    public function chartData(Request $request, CompanyProfile $companyProfile): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->weeklyTaskActivity(
                $this->visibleTasks($user),
                now($companyProfile->timezone()),
                $user->hasPermission('view_all_tasks'),
            ),
        ]);
    }

    private function visibleTasks(User $user): Builder
    {
        $query = Task::query();

        if ($user->hasPermission('view_all_tasks')) {
            return $query;
        }

        if (! $user->hasPermission('view_assigned_tasks')) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereHas('users', fn (Builder $users) => $users->whereKey($user->id))
            ->whereHas('board.users', fn (Builder $users) => $users->whereKey($user->id));
    }

    private function weeklyTaskActivity(Builder $tasks, Carbon $now, bool $canUseCompanyAggregates): array
    {
        $labels = [];
        $created = [];
        $completed = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $start = $now->copy()->startOfWeek()->subWeeks($offset);
            $end = $start->copy()->endOfWeek();
            $labels[] = $start->toDateString();
            $snapshot = DailyAnalytics::query()->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            $lastDay = $end->isFuture() ? $now->copy()->endOfDay() : $end;
            $hasSnapshot = $canUseCompanyAggregates && (clone $snapshot)->count() === $this->daysInRange($start, $lastDay);
            $created[] = $hasSnapshot ? (int) (clone $snapshot)->sum('tasks_created') : (clone $tasks)->whereBetween('created_at', $this->utcRange($start, $end))->count();
            $completed[] = $hasSnapshot ? (int) (clone $snapshot)->sum('tasks_completed') : (clone $tasks)->whereBetween('completed_at', $this->utcRange($start, $end))->count();
        }

        return compact('labels', 'created', 'completed');
    }

    private function dailyTaskCompletion(Builder $tasks, Carbon $now, bool $canUseCompanyAggregates): array
    {
        $labels = [];
        $created = [];
        $completed = [];
        $completionRates = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $day = $now->copy()->subDays($offset);
            $snapshot = $canUseCompanyAggregates ? DailyAnalytics::query()->whereDate('date', $day->toDateString())->first() : null;
            $dayCreated = $snapshot ? (int) $snapshot->tasks_created : (clone $tasks)->whereBetween('created_at', $this->utcRange($day->copy()->startOfDay(), $day->copy()->endOfDay()))->count();
            $dayCompleted = $snapshot ? (int) $snapshot->tasks_completed : (clone $tasks)->whereBetween('completed_at', $this->utcRange($day->copy()->startOfDay(), $day->copy()->endOfDay()))->count();
            $labels[] = $day->toDateString();
            $created[] = $dayCreated;
            $completed[] = $dayCompleted;
            $completionRates[] = $this->percentage($dayCompleted, $dayCreated);
        }

        return [
            'labels' => $labels,
            'created' => $created,
            'completed' => $completed,
            'completion_rates' => $completionRates,
        ];
    }

    private function attendanceTrend(Carbon $now): array
    {
        $labels = [];
        $rates = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $day = $now->copy()->subDays($offset);
            $summary = $this->attendanceSummary($day, $day);
            $labels[] = $day->toDateString();
            $rates[] = $summary['rate'];
        }

        return compact('labels', 'rates');
    }

    private function attendanceSummary(Carbon $start, Carbon $end): array
    {
        $snapshot = DailyAnalytics::query()->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
        if ((clone $snapshot)->count() === $this->daysInRange($start, $end)) {
            $present = (int) (clone $snapshot)->sum('attendance_present');
            $absent = (int) (clone $snapshot)->sum('attendance_absent');
            $marked = $present + $absent;
            return ['present' => $present, 'marked' => $marked, 'rate' => $this->percentage($present, $marked)];
        }
        $query = Attendance::query()->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
        $present = (clone $query)->where('status', 'present')->count();
        $absent = (clone $query)->where('status', 'absent')->count();
        $marked = $present + $absent;

        return [
            'present' => $present,
            'marked' => $marked,
            'rate' => $this->percentage($present, $marked),
        ];
    }

    private function dailyIncome(Carbon $now): array
    {
        $values = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $date = $now->copy()->subDays($offset)->toDateString();
            $snapshot = DailyAnalytics::query()->whereDate('date', $date)->value('income_total');
            $values[] = round($snapshot !== null ? (float) $snapshot : (float) Income::query()->whereDate('date', $date)->sum('total_amount'), 2);
        }

        return $values;
    }

    private function cashFlow(Carbon $now): array
    {
        $labels = [];
        $values = [];

        for ($offset = 4; $offset >= 0; $offset--) {
            $month = $now->copy()->subMonthsNoOverflow($offset);
            $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
            $analytics = DailyAnalytics::query()->whereBetween('date', [$range[0]->toDateString(), $range[1]->toDateString()]);
            $hasCompleteSnapshot = (clone $analytics)->count() === $this->daysInRange($range[0], $range[1]);
            $income = $hasCompleteSnapshot ? (float) (clone $analytics)->sum('income_total') : (float) Income::query()->whereBetween('date', $range)->sum('total_amount');
            $expenses = $hasCompleteSnapshot ? (float) (clone $analytics)->sum('expense_total') : (float) Expense::query()->whereBetween('date', $range)->sum('total_amount');
            $labels[] = $month->copy()->startOfMonth()->toDateString();
            $values[] = round($income - $expenses, 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'current_value' => $values[array_key_last($values)] ?? 0,
        ];
    }

    private function taskRow(Task $task, Carbon $now): array
    {
        $isOverdue = $task->due_date && $task->due_date->isBefore($now->copy()->startOfDay());

        [$status, $color] = match (true) {
            $isOverdue => ['overdue', 'error'],
            $task->progress >= 75 => ['nearing_completion', 'success'],
            $task->progress > 0 => ['in_progress', 'primary'],
            default => ['not_started', 'secondary'],
        };

        return [
            'id' => (string) $task->id,
            'title' => $task->title,
            'board' => $task->board?->title ?? '',
            'progress' => (int) $task->progress,
            'status' => $status,
            'color' => $color,
        ];
    }

    private function daysInRange(Carbon $start, Carbon $end): int
    {
        return (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
    }

    private function percentage(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function utcRange(Carbon $start, Carbon $end): array
    {
        return [$start->copy()->utc(), $end->copy()->utc()];
    }
}
