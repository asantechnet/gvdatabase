<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\Asset;
use App\Models\AssetAllocation;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\DailyAnalytics;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\LeaveRequest;
use App\Models\Salary;
use App\Models\Task;
use App\Models\User;
use App\Models\Visitor;
use App\Services\ActiveContractService;
use App\Support\BuiltInService;
use App\Support\CompanyProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request, CompanyProfile $companyProfile): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:1m,3m,1y,2y,custom',
            'start_date' => 'nullable|required_if:period,custom|date',
            'end_date' => 'nullable|required_if:period,custom|date|after_or_equal:start_date',
        ]);
        /** @var User $user */
        $user = $request->user();
        $now = now($companyProfile->timezone());
        [$periodStart, $periodEnd] = $this->dashboardPeriod($request, $now);
        $tasks = $this->visibleTasks($user);
        $canViewAllTasks = $user->hasPermission('view_all_tasks');
        $activeTasks = (clone $tasks)->where('progress', '<', 100);
        $canViewFinance = $user->hasPermission('view_financial_reports')
            || $user->hasPermission('view_incomes');
        $canViewAttendance = $user->hasPermission('view_attendance');
        $canViewApplicants = $user->hasPermission('view_applicants') || $canViewFinance;
        $canViewAsset = $user->hasPermission('view_assets');
        $canViewEmployees = $user->hasPermission('view_employees') || $canViewAttendance;
        $canViewContracts = $user->hasPermission('view_contracts');
        $canViewSalaries = $user->hasPermission('view_salaries');
        $canViewPayroll = $canViewSalaries;
        $canViewVisitors = $user->hasPermission('view_visitors');
        $canViewServices = $user->hasPermission('view_contracts');

        $weeklyActivity = $this->weeklyTaskActivity($tasks, $now, $canViewAllTasks);
        $dailyTaskCompletion = $this->dailyTaskCompletion($tasks, $now, $canViewAllTasks);
        $attendanceTrend = $canViewAttendance ? $this->attendanceTrend($now) : null;
        $cashFlow = $canViewFinance ? $this->cashFlow($now) : null;
        $baseCurrency = Currency::query()->where('is_base', true)->first()
            ?? Currency::query()->orderBy('id')->first();

        $currentMonthIncomeByBase = $canViewFinance
            ? $this->incomeByBase($periodStart, $periodEnd, $baseCurrency)
            : [];
        $previousMonthIncomeByBase = $canViewFinance
            ? $this->incomeByBase(
                $periodStart->copy()->subDays((int) $periodStart->diffInDays($periodEnd) + 1),
                $periodStart->copy()->subDay(),
                $baseCurrency,
            )
            : [];
        $displayBase = $baseCurrency?->code ?? (array_key_first($currentMonthIncomeByBase) ?: 'USD');
        $currentMonthIncome = $canViewFinance ? ($currentMonthIncomeByBase[$displayBase] ?? 0.0) : null;
        $previousMonthIncome = $canViewFinance ? ($previousMonthIncomeByBase[$displayBase] ?? 0.0) : null;

        $currentMonthAttendance = $canViewAttendance
            ? $this->attendanceSummary($periodStart, $periodEnd)
            : null;
        $financialOverview = $canViewFinance ? $this->financialOverview($periodStart, $periodEnd) : null;
        $commercialOverview = $this->commercialOverview($now, $canViewApplicants, $canViewServices, $canViewContracts, $canViewVisitors);
        $peopleOverview = $this->peopleOverview($now, $canViewEmployees, $canViewAttendance, $canViewSalaries);

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
                    'applicants' => $canViewApplicants,
                    'asset' => $canViewAsset,
                    'employees' => $canViewEmployees,
                    'contracts' => $canViewContracts,
                    'payroll' => $canViewPayroll,
                    'visitors' => $canViewVisitors,
                    'services' => $canViewServices,
                ],
                'currency' => [
                    'code' => $baseCurrency?->code ?? 'USD',
                    'symbol' => $baseCurrency?->symbol ?? '$',
                    'decimal_places' => (int) ($baseCurrency?->decimal_places ?? 2),
                ],
                'summary' => [
                    'monthly_income' => $currentMonthIncome,
                    'previous_month_income' => $previousMonthIncome,
                    'finance_bases' => [
                        'display_base' => $displayBase,
                        'monthly_income' => $currentMonthIncomeByBase,
                        'previous_month_income' => $previousMonthIncomeByBase,
                    ],
                    'active_tasks' => (clone $activeTasks)->count(),
                    'nearing_completion_tasks' => (clone $activeTasks)->where('progress', '>=', 75)->count(),
                    'completed_tasks_this_month' => (clone $tasks)
                        ->where('progress', 100)
                        ->whereBetween('completed_at', $this->utcRange($periodStart, $periodEnd))
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
                    'monthly_expenses' => $financialOverview['expenses'] ?? null,
                    'monthly_net_cash' => $financialOverview['net_cash'] ?? null,
                    'applicant_count' => $canViewApplicants ? Applicant::query()->count() : null,
                    'new_applicants_this_month' => $canViewApplicants
                        ? Applicant::query()->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->count()
                        : null,
                    'employee_count' => $canViewEmployees ? Employee::query()->count() : null,
                    'asset_asset_value' => $canViewAsset ? round((float) Asset::query()->selectRaw('COALESCE(SUM(quantity * price), 0) as total')->value('total'), 2) : null,
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
                'financial_chart' => $canViewFinance ? $this->monthlyFinancials($now) : null,
                'expense_breakdown' => $canViewFinance ? $this->expenseBreakdown($now) : [],
                'top_applicants' => $canViewApplicants && $canViewFinance ? $this->topApplicants($now) : [],
                'asset_health' => $canViewAsset ? $this->assetHealth() : null,
                'team_health' => $canViewEmployees ? $this->teamHealth($now, $currentMonthAttendance) : null,
                'attention_items' => $this->attentionItems($activeTasks, $now, $canViewAsset),
                'commercial_overview' => $commercialOverview,
                'people_overview' => $peopleOverview,
                'kpi_trends' => $this->kpiTrends($tasks, $now, $canViewFinance, $canViewApplicants, $canViewSalaries, $canViewVisitors),
                'business_trend' => $canViewFinance ? $this->businessTrend($now) : null,
                'service_portfolio' => $canViewServices ? $this->servicePortfolio() : [],
                'task_mix' => $this->taskMix($activeTasks),
                'executive_actions' => $this->executiveActions(
                    $activeTasks,
                    $now,
                    $canViewContracts,
                    $canViewServices,
                    $canViewAttendance,
                    $canViewAsset,
                ),
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
            $values[] = round($this->incomeSum(Income::query()->whereDate('date', $date)), 2);
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
            $incomeQuery = Income::query()->whereBetween('date', $range);
            $expenseQuery = Expense::query()->whereBetween('date', $range);
            $income = $this->incomeSum($incomeQuery);
            $expenses = $this->expenseSum($expenseQuery);
            $labels[] = $month->copy()->startOfMonth()->toDateString();
            $values[] = round($income - $expenses, 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'current_value' => $values[array_key_last($values)] ?? 0,
        ];
    }

    /** Revenue and spending over the last six calendar months, in the base currency. */
    private function monthlyFinancials(Carbon $now): array
    {
        $labels = [];
        $income = [];
        $expenses = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $now->copy()->subMonthsNoOverflow($offset);
            $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
            $incomeQuery = Income::query()->whereBetween('date', $range);
            $expenseQuery = Expense::query()->whereBetween('date', $range);
            $labels[] = $month->copy()->startOfMonth()->toDateString();
            $income[] = round($this->incomeSum($incomeQuery), 2);
            $expenses[] = round($this->expenseSum($expenseQuery), 2);
        }

        return compact('labels', 'income', 'expenses');
    }

    private function financialOverview(Carbon $start, Carbon $end): array
    {
        $income = Income::query()->whereBetween('date', [$start, $end]);
        $expenses = Expense::query()->whereBetween('date', [$start, $end]);
        $incomeTotal = round($this->incomeSum($income), 2);
        $expenseTotal = round($this->expenseSum($expenses), 2);

        return ['income' => $incomeTotal, 'expenses' => $expenseTotal, 'net_cash' => round($incomeTotal - $expenseTotal, 2)];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dashboardPeriod(Request $request, Carbon $now): array
    {
        if ($request->input('period') === 'custom') {
            return [
                Carbon::parse($request->input('start_date'), $now->timezone)->startOfDay(),
                Carbon::parse($request->input('end_date'), $now->timezone)->endOfDay(),
            ];
        }

        $start = match ($request->input('period', '1m')) {
            '3m' => $now->copy()->subMonthsNoOverflow(3),
            '1y' => $now->copy()->subYear(),
            '2y' => $now->copy()->subYears(2),
            default => $now->copy()->subMonthNoOverflow(),
        };

        return [$start->startOfDay(), $now->copy()->endOfDay()];
    }

    private function expenseBreakdown(Carbon $now): array
    {
        return Expense::query()
            ->with('expenseCurrency:id,rate')
            ->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->get()
            ->groupBy(fn (Expense $expense) => $expense->type ?: 'Other')
            ->map(fn ($rows, string $type) => ['label' => $type, 'value' => round($rows->sum(fn (Expense $expense) => $this->currentBaseAmount((float) $expense->amount * $expense->quantity, $expense->expenseCurrency)), 2)])
            ->sortByDesc('value')->take(5)->values()->all();
    }

    private function topApplicants(Carbon $now): array
    {
        return Income::query()
            ->with(['applicant:id,name', 'incomeCurrency:id,rate'])
            ->whereNotNull('applicant_id')
            ->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->get()
            ->groupBy('applicant_id')
            ->map(function ($rows) {
                $income = $rows->first();

                return [
                    'id' => (string) $income->applicant_id,
                    'name' => $income->applicant?->name ?? 'Unknown applicant',
                    'value' => round($rows->sum(fn (Income $row) => $this->currentBaseAmount((float) $row->amount * $row->quantity, $row->incomeCurrency)), 2),
                ];
            })->sortByDesc('value')->take(5)->values()->all();
    }

    private function assetHealth(): array
    {
        $all = Asset::query();
        $total = (clone $all)->sum('quantity');
        $assigned = AssetAllocation::query()->sum('quantity');
        $damaged = (clone $all)->whereIn('condition', [Asset::CONDITION_DAMAGED, Asset::CONDITION_POOR])->sum('quantity');
        $byCondition = (clone $all)->select('condition')->selectRaw('SUM(quantity) as total')->groupBy('condition')->get()
            ->map(fn (Asset $asset) => ['label' => ucfirst($asset->condition ?: 'unknown'), 'value' => (int) $asset->total])->values()->all();

        return [
            'total_items' => (int) $total,
            'assigned_items' => (int) $assigned,
            'available_items' => max(0, (int) $total - (int) $assigned),
            'attention_items' => (int) $damaged,
            'by_condition' => $byCondition,
        ];
    }

    private function teamHealth(Carbon $now, ?array $attendance): array
    {
        $joined = Employee::query()->whereBetween('join_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->count();

        return [
            'total_employees' => Employee::query()->count(),
            'new_this_month' => $joined,
            'attendance_rate' => $attendance['rate'] ?? null,
            'present' => $attendance['present'] ?? null,
            'marked' => $attendance['marked'] ?? null,
        ];
    }

    private function commercialOverview(
        Carbon $now,
        bool $canViewApplicants,
        bool $canViewServices,
        bool $canViewContracts,
        bool $canViewVisitors,
    ): array {
        $activeServices = $canViewServices ? ApplicantContract::query()
            ->whereHas('contract', fn ($contracts) => $contracts->where('status', 'active')->where('is_cancelled', false))
            ->count() : null;
        $activeContracts = $canViewContracts ? app(ActiveContractService::class)->activeContractCount() : null;

        return [
            'applicants' => $canViewApplicants ? Applicant::query()->count() : null,
            'new_applicants_this_month' => $canViewApplicants ? Applicant::query()
                ->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->count() : null,
            'active_services' => $activeServices,
            'active_contracts' => $activeContracts,
            'draft_contracts' => $canViewContracts ? Contract::query()->where('status', 'draft')->count() : null,
            'visitors_this_month' => $canViewVisitors ? Visitor::query()
                ->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->count() : null,
        ];
    }

    private function peopleOverview(Carbon $now, bool $canViewEmployees, bool $canViewAttendance, bool $canViewSalaries): array
    {
        $employeeCount = $canViewEmployees ? Employee::query()->count() : null;
        $todayAttendance = $canViewAttendance ? Attendance::query()->whereDate('date', $now->toDateString()) : null;
        $latestSalaryPeriod = $canViewSalaries ? Salary::query()->max('salary_month') : null;
        $latestPayrollPeriod = $latestSalaryPeriod ?: null;
        $latestPayrollTotal = 0.0;
        if ($latestPayrollPeriod && $canViewSalaries) {
            $latestPayrollTotal += $this->salarySum(Salary::query()->where('salary_month', $latestPayrollPeriod));
        }

        return [
            'employees' => $employeeCount,
            'present_today' => $todayAttendance ? (clone $todayAttendance)->where('status', 'present')->count() : null,
            'attendance_marked_today' => $todayAttendance ? (clone $todayAttendance)->count() : null,
            'unmarked_today' => $todayAttendance && $employeeCount !== null
                ? max(0, $employeeCount - (clone $todayAttendance)->distinct('employee_id')->count('employee_id'))
                : null,
            'late_minutes_this_month' => $canViewAttendance ? (int) Attendance::query()
                ->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
                ->sum('late_arrival_minutes') : null,
            'overtime_minutes_this_month' => $canViewAttendance ? (int) Attendance::query()
                ->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
                ->sum('overtime_minutes') : null,
            'pending_leave_requests' => $canViewEmployees ? LeaveRequest::query()->where('is_granted', false)->whereDate('date', '>=', $now->toDateString())->count() : null,
            'latest_payroll_period' => $latestPayrollPeriod,
            'latest_payroll_total' => $latestPayrollPeriod ? round($latestPayrollTotal, 2) : null,
        ];
    }

    private function kpiTrends(
        Builder $tasks,
        Carbon $now,
        bool $canViewFinance,
        bool $canViewApplicants,
        bool $canViewSalaries,
        bool $canViewVisitors,
    ): array {
        $labels = [];
        $applicants = [];
        $payroll = [];
        $visitors = [];
        $completedTasks = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $now->copy()->subMonthsNoOverflow($offset);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $labels[] = $month->copy()->startOfMonth()->toDateString();
            $applicants[] = $canViewApplicants ? Applicant::query()->whereBetween('created_at', [$start, $end])->count() : 0;
            $salaryAmount = $canViewSalaries
                ? $this->salarySum(Salary::query()->where('salary_month', $month->format('Y-m')))
                : 0;
            $payroll[] = round($salaryAmount, 2);
            $visitors[] = $canViewVisitors ? Visitor::query()->whereBetween('date', [$start, $end])->count() : 0;
            $completedTasks[] = (clone $tasks)->where('progress', 100)->whereBetween('completed_at', $this->utcRange($start, $end))->count();
        }

        return compact('labels', 'applicants', 'payroll', 'visitors', 'completedTasks');
    }

    private function businessTrend(Carbon $now): array
    {
        $labels = [];
        $collected = [];
        $spent = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $now->copy()->subMonthsNoOverflow($offset);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $income = Income::query()->whereBetween('date', [$start, $end]);
            $expense = Expense::query()->whereBetween('date', [$start, $end]);
            $labels[] = $month->copy()->startOfMonth()->toDateString();
            $collected[] = round($this->incomeSum($income), 2);
            $spent[] = round($this->expenseSum($expense), 2);
        }

        return compact('labels', 'collected', 'spent');
    }

    private function servicePortfolio(): array
    {
        return ApplicantContract::query()
            ->whereIn('applicant_contract.service', BuiltInService::ALL)
            ->join('contracts', 'contracts.id', '=', 'applicant_contract.contract_id')
            ->where('contracts.status', 'active')
            ->where('contracts.is_cancelled', false)
            ->selectRaw('applicant_contract.service as label, COUNT(*) as total')
            ->groupBy('applicant_contract.service')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['label' => BuiltInService::label($row->label), 'value' => (int) $row->total])
            ->values()->all();
    }

    private function taskMix(Builder $activeTasks): array
    {
        return (clone $activeTasks)->selectRaw('priority, COUNT(*) as total')->groupBy('priority')->get()
            ->map(fn (Task $task) => ['label' => ucfirst($task->priority), 'value' => (int) $task->total])
            ->values()->all();
    }

    private function executiveActions(
        Builder $activeTasks,
        Carbon $now,
        bool $canViewContracts,
        bool $canViewServices,
        bool $canViewAttendance,
        bool $canViewAsset,
    ): array {
        $actions = [];
        $add = function (bool $condition, string $areaKey, string $titleKey, string $detailKey, array $params, string $severity, string $icon, string $route) use (&$actions) {
            if ($condition) {
                $actions[] = compact('areaKey', 'titleKey', 'detailKey', 'params', 'severity', 'icon', 'route');
            }
        };

        $overdueTasks = (clone $activeTasks)->whereDate('due_date', '<', $now->toDateString())->count();
        $urgentTasks = (clone $activeTasks)->where('priority', 'urgent')->count();
        $add($overdueTasks > 0, 'delivery', 'overdueTasks', 'overdueTasksDetail', ['count' => $overdueTasks], 'high', 'tabler-calendar-exclamation', '/tasks');
        $add($urgentTasks > 0, 'delivery', 'urgentTasks', 'urgentTasksDetail', ['count' => $urgentTasks], 'medium', 'tabler-flame', '/tasks');

        if ($canViewContracts) {
            $contractCount = Contract::query()->count();
            $add($contractCount === 0, 'contracts', 'noContracts', 'noContractsDetail', [], 'medium', 'tabler-file-text', '/contracts');
        }
        if ($canViewAttendance) {
            // Mirrors the active-only `unmarked-staff` list this action links to.
            $employees = Employee::query()->active()->count();
            $marked = Attendance::query()->whereDate('date', $now->toDateString())->distinct('employee_id')->count('employee_id');
            $unmarked = max(0, $employees - $marked);
            $add($unmarked > 0, 'people', 'unmarkedEmployees', 'unmarkedEmployeesDetail', ['count' => $unmarked], 'medium', 'tabler-user-question', '/timesheet');
        }
        if ($canViewAsset) {
            $damaged = Asset::query()->whereIn('condition', [Asset::CONDITION_DAMAGED, Asset::CONDITION_POOR])->sum('quantity');
            $add($damaged > 0, 'assets', 'assetInspection', 'assetInspectionDetail', ['count' => (int) $damaged], 'low', 'tabler-package-off', '/asset');
        }

        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($actions, fn (array $a, array $b) => ($rank[$a['severity']] ?? 3) <=> ($rank[$b['severity']] ?? 3));

        return array_slice($actions, 0, 8);
    }

    private function attentionItems(Builder $activeTasks, Carbon $now, bool $canViewAsset): array
    {
        $items = [];
        $overdueTasks = (clone $activeTasks)->whereDate('due_date', '<', $now->toDateString())->count();
        if ($overdueTasks > 0) {
            $items[] = ['type' => 'tasks', 'color' => 'error', 'icon' => 'tabler-alert-circle', 'count' => $overdueTasks, 'title' => 'Overdue tasks need an owner'];
        }
        if ($canViewAsset) {
            $attentionAsset = Asset::query()->whereIn('condition', [Asset::CONDITION_DAMAGED, Asset::CONDITION_POOR])->sum('quantity');
            if ($attentionAsset > 0) {
                $items[] = ['type' => 'asset', 'color' => 'warning', 'icon' => 'tabler-package-off', 'count' => (int) $attentionAsset, 'title' => 'Asset items need inspection'];
            }
        }

        return $items;
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

    private function incomeByBase(Carbon $start, Carbon $end, ?Currency $base): array
    {
        $code = $base?->code ?? 'BASE';

        return [$code => round($this->incomeSum(Income::query()->whereBetween('date', [$start, $end])), 4)];
    }

    private function incomeSum(Builder $query): float
    {
        return (float) $query->with('incomeCurrency:id,rate')->get()->sum(
            fn (Income $income) => $this->currentBaseAmount((float) $income->amount * $income->quantity, $income->incomeCurrency),
        );
    }

    private function expenseSum(Builder $query): float
    {
        return (float) $query->with('expenseCurrency:id,rate')->get()->sum(
            fn (Expense $expense) => $this->currentBaseAmount((float) $expense->amount * $expense->quantity, $expense->expenseCurrency),
        );
    }

    private function salarySum(Builder $query): float
    {
        return (float) $query->with('currency:id,rate')->get()->sum(
            fn (Salary $salary) => $this->currentBaseAmount((float) $salary->total_amount, $salary->currency),
        );
    }

    private function currentBaseAmount(float $amount, ?Currency $currency): float
    {
        $rate = (float) ($currency?->rate ?? 0);

        return $rate > 0 ? $amount / $rate : 0.0;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function utcRange(Carbon $start, Carbon $end): array
    {
        return [$start->copy()->utc(), $end->copy()->utc()];
    }
}
