<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\PaidTimeOff;
use App\Models\Salary;
use App\Models\Service;
use App\Models\Stock;
use App\Models\Task;
use App\Models\User;
use App\Models\Visitor;
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
        $canViewCustomers = $user->hasPermission('view_customers') || $canViewFinance;
        $canViewInvoices = $user->hasPermission('view_invoices') || $canViewFinance;
        $canViewStock = $user->hasPermission('view_stocks');
        $canViewEmployees = $user->hasPermission('view_employees') || $canViewAttendance;
        $canViewContracts = $user->hasPermission('view_contracts');
        $canViewPayroll = $user->hasPermission('view_salaries');
        $canViewVisitors = $user->hasPermission('view_visitors');
        $canViewServices = $user->hasPermission('view_services') || $user->hasPermission('manage_customer_services');

        $weeklyActivity = $this->weeklyTaskActivity($tasks, $now, $canViewAllTasks);
        $dailyTaskCompletion = $this->dailyTaskCompletion($tasks, $now, $canViewAllTasks);
        $attendanceTrend = $canViewAttendance ? $this->attendanceTrend($now) : null;
        $cashFlow = $canViewFinance ? $this->cashFlow($now) : null;
        $baseCurrency = Currency::query()->where('is_base', true)->first()
            ?? Currency::query()->orderBy('id')->first();

        $currentMonthIncomeByBase = $canViewFinance
            ? $this->incomeByBase($now->copy()->startOfMonth(), $now->copy()->endOfMonth())
            : [];
        $previousMonthIncomeByBase = $canViewFinance
            ? $this->incomeByBase(
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            )
            : [];
        $displayBase = $baseCurrency?->code ?? (array_key_first($currentMonthIncomeByBase) ?: 'USD');
        $currentMonthIncome = $canViewFinance ? ($currentMonthIncomeByBase[$displayBase] ?? 0.0) : null;
        $previousMonthIncome = $canViewFinance ? ($previousMonthIncomeByBase[$displayBase] ?? 0.0) : null;

        $currentMonthAttendance = $canViewAttendance
            ? $this->attendanceSummary($now->copy()->startOfMonth(), $now->copy()->endOfMonth())
            : null;
        $financialOverview = $canViewFinance ? $this->financialOverview($now) : null;
        $allReceivables = $canViewInvoices ? $this->receivables($now, false) : [];
        $invoiceOverview = $canViewInvoices
            ? ['count' => count($allReceivables), 'value' => round(array_sum(array_column($allReceivables, 'balance')), 2)]
            : null;
        $commercialOverview = $this->commercialOverview($now, $canViewCustomers, $canViewInvoices, $canViewServices, $canViewContracts, $canViewVisitors);
        $peopleOverview = $this->peopleOverview($now, $canViewEmployees, $canViewAttendance, $canViewPayroll);

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
                    'customers' => $canViewCustomers,
                    'invoices' => $canViewInvoices,
                    'stock' => $canViewStock,
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
                    'monthly_expenses' => $financialOverview['expenses'] ?? null,
                    'monthly_net_cash' => $financialOverview['net_cash'] ?? null,
                    'customer_count' => $canViewCustomers ? Customer::query()->count() : null,
                    'new_customers_this_month' => $canViewCustomers
                        ? Customer::query()->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->count()
                        : null,
                    'open_invoice_count' => $invoiceOverview['count'] ?? null,
                    'open_invoice_value' => $invoiceOverview['value'] ?? null,
                    'employee_count' => $canViewEmployees ? Employee::query()->count() : null,
                    'stock_asset_value' => $canViewStock ? round((float) Stock::query()->selectRaw('COALESCE(SUM(quantity * price), 0) as total')->value('total'), 2) : null,
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
                'top_customers' => $canViewCustomers && $canViewFinance ? $this->topCustomers($now) : [],
                'receivables' => array_slice($allReceivables, 0, 5),
                'stock_health' => $canViewStock ? $this->stockHealth() : null,
                'team_health' => $canViewEmployees ? $this->teamHealth($now, $currentMonthAttendance) : null,
                'attention_items' => $this->attentionItems($activeTasks, $now, $allReceivables, $canViewStock),
                'commercial_overview' => $commercialOverview,
                'people_overview' => $peopleOverview,
                'kpi_trends' => $this->kpiTrends($tasks, $now, $canViewFinance, $canViewCustomers, $canViewPayroll, $canViewVisitors),
                'business_trend' => $canViewFinance ? $this->businessTrend($now) : null,
                'invoice_aging' => $canViewInvoices ? $this->invoiceAging($allReceivables, $now) : null,
                'service_portfolio' => $canViewServices ? $this->servicePortfolio() : [],
                'task_mix' => $this->taskMix($activeTasks),
                'executive_actions' => $this->executiveActions(
                    $activeTasks,
                    $allReceivables,
                    $now,
                    $canViewInvoices,
                    $canViewContracts,
                    $canViewServices,
                    $canViewAttendance,
                    $canViewStock,
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
        $baseCode = Currency::query()->where('is_base', true)->value('code');

        for ($offset = 6; $offset >= 0; $offset--) {
            $date = $now->copy()->subDays($offset)->toDateString();
            $query = Income::query()->whereDate('date', $date);
            if ($baseCode) {
                $query->where('base_curr_shrt', $baseCode);
            }
            $values[] = round((float) $query->sum('base_amount'), 2);
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
            $baseCode = Currency::query()->where('is_base', true)->value('code');
            $incomeQuery = Income::query()->whereBetween('date', $range);
            $expenseQuery = Expense::query()->whereBetween('date', $range);
            if ($baseCode) {
                $incomeQuery->where('base_curr_shrt', $baseCode);
                $expenseQuery->where('base_curr_shrt', $baseCode);
            }
            $income = (float) $incomeQuery->sum('base_amount');
            $expenses = (float) $expenseQuery->sum('base_amount');
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
        $baseCode = Currency::query()->where('is_base', true)->value('code');

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $now->copy()->subMonthsNoOverflow($offset);
            $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
            $incomeQuery = Income::query()->whereBetween('date', $range);
            $expenseQuery = Expense::query()->whereBetween('date', $range);
            if ($baseCode) {
                $incomeQuery->where('base_curr_shrt', $baseCode);
                $expenseQuery->where('base_curr_shrt', $baseCode);
            }
            $labels[] = $month->copy()->startOfMonth()->toDateString();
            $income[] = round((float) $incomeQuery->sum('base_amount'), 2);
            $expenses[] = round((float) $expenseQuery->sum('base_amount'), 2);
        }

        return compact('labels', 'income', 'expenses');
    }

    private function financialOverview(Carbon $now): array
    {
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();
        $baseCode = Currency::query()->where('is_base', true)->value('code');
        $income = Income::query()->whereBetween('date', [$start, $end]);
        $expenses = Expense::query()->whereBetween('date', [$start, $end]);
        if ($baseCode) {
            $income->where('base_curr_shrt', $baseCode);
            $expenses->where('base_curr_shrt', $baseCode);
        }
        $incomeTotal = round((float) $income->sum('base_amount'), 2);
        $expenseTotal = round((float) $expenses->sum('base_amount'), 2);

        return ['income' => $incomeTotal, 'expenses' => $expenseTotal, 'net_cash' => round($incomeTotal - $expenseTotal, 2)];
    }

    private function expenseBreakdown(Carbon $now): array
    {
        $baseCode = Currency::query()->where('is_base', true)->value('code');
        $query = Expense::query()
            ->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->selectRaw('type, SUM(base_amount) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->limit(5);
        if ($baseCode) {
            $query->where('base_curr_shrt', $baseCode);
        }

        return $query->get()->map(fn (Expense $expense) => [
            'label' => $expense->type ?: 'Other',
            'value' => round((float) $expense->total, 2),
        ])->values()->all();
    }

    private function topCustomers(Carbon $now): array
    {
        $baseCode = Currency::query()->where('is_base', true)->value('code');
        $query = Income::query()
            ->with('customer:id,name,company')
            ->whereNotNull('customer_id')
            ->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->selectRaw('customer_id, SUM(base_amount) as total')
            ->groupBy('customer_id')
            ->orderByDesc('total')
            ->limit(5);
        if ($baseCode) {
            $query->where('base_curr_shrt', $baseCode);
        }

        return $query->get()->map(fn (Income $income) => [
            'id' => (string) $income->customer_id,
            'name' => $income->customer?->company ?: ($income->customer?->name ?? 'Unknown customer'),
            'value' => round((float) $income->total, 2),
        ])->values()->all();
    }

    private function receivables(Carbon $now, bool $limited = true): array
    {
        $invoices = Invoice::query()
            ->with('customer:id,name,company')
            ->withSum('incomes', 'base_amount')
            ->whereNull('voided_at')
            ->orderBy('due_date')
            ->get();

        $rows = $invoices->map(function (Invoice $invoice) use ($now) {
            $paid = (float) $invoice->incomes_sum_base_amount;
            $balance = max(0, (float) $invoice->base_amount - $paid);
            return [
                'id' => (string) $invoice->id,
                'number' => $invoice->invoice_number ?: 'Invoice #'.$invoice->id,
                'customer' => $invoice->customer?->company ?: ($invoice->customer?->name ?? $invoice->deptor),
                'balance' => round($balance, 2),
                'due_date' => $invoice->due_date?->toDateString(),
                'is_overdue' => $balance > 0 && $invoice->due_date?->isBefore($now->copy()->startOfDay()),
            ];
        })->filter(fn (array $invoice) => $invoice['balance'] > 0)
            ->sortByDesc('balance')
            ->values();

        return ($limited ? $rows->take(5) : $rows)->values()->all();
    }

    private function stockHealth(): array
    {
        $all = Stock::query();
        $total = (clone $all)->sum('quantity');
        $assigned = (clone $all)->whereNotNull('staff_id')->sum('quantity');
        $damaged = (clone $all)->whereIn('condition', [Stock::CONDITION_DAMAGED, Stock::CONDITION_POOR])->sum('quantity');
        $byCondition = (clone $all)->select('condition')->selectRaw('SUM(quantity) as total')->groupBy('condition')->get()
            ->map(fn (Stock $stock) => ['label' => ucfirst($stock->condition ?: 'unknown'), 'value' => (int) $stock->total])->values()->all();

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
        bool $canViewCustomers,
        bool $canViewInvoices,
        bool $canViewServices,
        bool $canViewContracts,
        bool $canViewVisitors,
    ): array {
        $invoiceTotal = $canViewInvoices
            ? round((float) Invoice::query()->whereNull('voided_at')->sum('base_amount'), 2)
            : null;
        $collectedTotal = $canViewInvoices
            ? round((float) Income::query()->whereNotNull('invoice_id')->sum('base_amount'), 2)
            : null;
        $activeServices = $canViewServices ? CustomerService::query()->where('status', 'active')->count() : null;
        $serviceDeadlines = $canViewServices ? CustomerService::query()
            ->where('status', 'active')
            ->whereBetween('deadline', [$now->toDateString(), $now->copy()->addDays(30)->toDateString()])
            ->count() : null;
        $activeContracts = $canViewContracts ? Contract::query()->where('status', 'active')->where('is_cancelled', false)->count() : null;
        $expiringContracts = $canViewContracts ? Contract::query()
            ->where('status', 'active')
            ->where('is_cancelled', false)
            ->whereBetween('end_date', [$now->toDateString(), $now->copy()->addDays(30)->toDateString()])
            ->count() : null;

        return [
            'customers' => $canViewCustomers ? Customer::query()->count() : null,
            'new_customers_this_month' => $canViewCustomers ? Customer::query()
                ->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->count() : null,
            'invoice_total' => $invoiceTotal,
            'collected_total' => $collectedTotal,
            'collection_rate' => $invoiceTotal && $invoiceTotal > 0
                ? round(min(100, (($collectedTotal ?? 0) / $invoiceTotal) * 100), 1)
                : 0.0,
            'active_services' => $activeServices,
            'service_deadlines_30_days' => $serviceDeadlines,
            'active_contracts' => $activeContracts,
            'expiring_contracts_30_days' => $expiringContracts,
            'draft_contracts' => $canViewContracts ? Contract::query()->where('status', 'draft')->count() : null,
            'visitors_this_month' => $canViewVisitors ? Visitor::query()
                ->whereBetween('date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->count() : null,
        ];
    }

    private function peopleOverview(Carbon $now, bool $canViewEmployees, bool $canViewAttendance, bool $canViewPayroll): array
    {
        $employeeCount = $canViewEmployees ? Employee::query()->count() : null;
        $todayAttendance = $canViewAttendance ? Attendance::query()->whereDate('date', $now->toDateString()) : null;
        $latestPayrollPeriod = $canViewPayroll ? Salary::query()->max('salary_month') : null;

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
            'pending_leave_requests' => $canViewEmployees ? PaidTimeOff::query()->where('is_granted', false)->whereDate('date', '>=', $now->toDateString())->count() : null,
            'latest_payroll_period' => $latestPayrollPeriod,
            'latest_payroll_total' => $latestPayrollPeriod ? round((float) Salary::query()
                ->where('salary_month', $latestPayrollPeriod)->sum('base_amount'), 2) : null,
        ];
    }

    private function kpiTrends(
        Builder $tasks,
        Carbon $now,
        bool $canViewFinance,
        bool $canViewCustomers,
        bool $canViewPayroll,
        bool $canViewVisitors,
    ): array {
        $labels = [];
        $invoiceValue = [];
        $customers = [];
        $payroll = [];
        $visitors = [];
        $completedTasks = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $now->copy()->subMonthsNoOverflow($offset);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $labels[] = $month->copy()->startOfMonth()->toDateString();
            $invoiceValue[] = $canViewFinance ? round((float) $this->invoicesForPeriod($start, $end)->sum('base_amount'), 2) : 0;
            $customers[] = $canViewCustomers ? Customer::query()->whereBetween('date', [$start, $end])->count() : 0;
            $payroll[] = $canViewPayroll ? round((float) Salary::query()->where('salary_month', $month->format('Y-m'))->sum('base_amount'), 2) : 0;
            $visitors[] = $canViewVisitors ? Visitor::query()->whereBetween('date', [$start, $end])->count() : 0;
            $completedTasks[] = (clone $tasks)->where('progress', 100)->whereBetween('completed_at', $this->utcRange($start, $end))->count();
        }

        return compact('labels', 'invoiceValue', 'customers', 'payroll', 'visitors', 'completedTasks');
    }

    private function businessTrend(Carbon $now): array
    {
        $labels = [];
        $billed = [];
        $collected = [];
        $spent = [];
        $baseCode = Currency::query()->where('is_base', true)->value('code');

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $now->copy()->subMonthsNoOverflow($offset);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $income = Income::query()->whereBetween('date', [$start, $end]);
            $expense = Expense::query()->whereBetween('date', [$start, $end]);
            if ($baseCode) {
                $income->where('base_curr_shrt', $baseCode);
                $expense->where('base_curr_shrt', $baseCode);
            }
            $labels[] = $month->copy()->startOfMonth()->toDateString();
            $billed[] = round((float) $this->invoicesForPeriod($start, $end)->sum('base_amount'), 2);
            $collected[] = round((float) $income->sum('base_amount'), 2);
            $spent[] = round((float) $expense->sum('base_amount'), 2);
        }

        return compact('labels', 'billed', 'collected', 'spent');
    }

    private function invoicesForPeriod(Carbon $start, Carbon $end): Builder
    {
        return Invoice::query()->whereNull('voided_at')->where(function (Builder $query) use ($start, $end) {
            $query->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
                ->orWhere(function (Builder $fallback) use ($start, $end) {
                    $fallback->whereNull('issue_date')->whereBetween('created_at', $this->utcRange($start, $end));
                });
        });
    }

    private function invoiceAging(array $receivables, Carbon $now): array
    {
        $buckets = ['Not due' => 0.0, '1–30 days' => 0.0, '31–60 days' => 0.0, '61+ days' => 0.0, 'No due date' => 0.0];
        foreach ($receivables as $invoice) {
            if (! $invoice['due_date']) {
                $buckets['No due date'] += $invoice['balance'];
                continue;
            }
            $dueDate = Carbon::parse($invoice['due_date'])->startOfDay();
            if ($dueDate->greaterThanOrEqualTo($now->copy()->startOfDay())) {
                $buckets['Not due'] += $invoice['balance'];
                continue;
            }
            $days = (int) $dueDate->diffInDays($now->copy()->startOfDay());
            $bucket = $days <= 30 ? '1–30 days' : ($days <= 60 ? '31–60 days' : '61+ days');
            $buckets[$bucket] += $invoice['balance'];
        }

        return [
            'labels' => array_keys($buckets),
            'values' => array_map(fn (float $value) => round($value, 2), array_values($buckets)),
        ];
    }

    private function servicePortfolio(): array
    {
        return CustomerService::query()
            ->join('services', 'services.id', '=', 'customer_service.service_id')
            ->where('customer_service.status', 'active')
            ->selectRaw('services.type as label, COUNT(*) as total')
            ->groupBy('services.type')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->total])
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
        array $receivables,
        Carbon $now,
        bool $canViewInvoices,
        bool $canViewContracts,
        bool $canViewServices,
        bool $canViewAttendance,
        bool $canViewStock,
    ): array {
        $actions = [];
        $add = function (bool $condition, string $areaKey, string $titleKey, string $detailKey, array $params, string $severity, string $icon, string $route) use (&$actions) {
            if ($condition) $actions[] = compact('areaKey', 'titleKey', 'detailKey', 'params', 'severity', 'icon', 'route');
        };

        $overdueTasks = (clone $activeTasks)->whereDate('due_date', '<', $now->toDateString())->count();
        $urgentTasks = (clone $activeTasks)->where('priority', 'urgent')->count();
        $add($overdueTasks > 0, 'delivery', 'overdueTasks', 'overdueTasksDetail', ['count' => $overdueTasks], 'high', 'tabler-calendar-exclamation', '/tasks');
        $add($urgentTasks > 0, 'delivery', 'urgentTasks', 'urgentTasksDetail', ['count' => $urgentTasks], 'medium', 'tabler-flame', '/tasks');

        if ($canViewInvoices) {
            $missingDueDates = collect($receivables)->whereNull('due_date')->count();
            $overdueInvoices = collect($receivables)->where('is_overdue', true)->count();
            $add($missingDueDates > 0, 'finance', 'missingInvoiceDueDates', 'missingInvoiceDueDatesDetail', ['count' => $missingDueDates], 'high', 'tabler-file-alert', '/innvoice');
            $add($overdueInvoices > 0, 'finance', 'overdueInvoices', 'overdueInvoicesDetail', [
                'count' => $overdueInvoices,
                'amount' => round((float) collect($receivables)->where('is_overdue', true)->sum('balance'), 2),
            ], 'high', 'tabler-cash-off', '/innvoice');
        }
        if ($canViewContracts) {
            $contractCount = Contract::query()->count();
            $expiring = Contract::query()->where('status', 'active')->whereBetween('end_date', [$now->toDateString(), $now->copy()->addDays(30)->toDateString()])->count();
            $add($contractCount === 0, 'contracts', 'noContracts', 'noContractsDetail', [], 'medium', 'tabler-file-text', '/contracts');
            $add($expiring > 0, 'contracts', 'expiringContracts', 'expiringContractsDetail', ['count' => $expiring], 'medium', 'tabler-file-time', '/contracts');
        }
        if ($canViewServices) {
            $deadlines = CustomerService::query()->where('status', 'active')->whereBetween('deadline', [$now->toDateString(), $now->copy()->addDays(30)->toDateString()])->count();
            $add($deadlines > 0, 'customers', 'serviceDeadlines', 'serviceDeadlinesDetail', ['count' => $deadlines], 'medium', 'tabler-progress-check', '/all-customers');
        }
        if ($canViewAttendance) {
            $employees = Employee::query()->count();
            $marked = Attendance::query()->whereDate('date', $now->toDateString())->distinct('employee_id')->count('employee_id');
            $unmarked = max(0, $employees - $marked);
            $add($unmarked > 0, 'people', 'unmarkedEmployees', 'unmarkedEmployeesDetail', ['count' => $unmarked], 'medium', 'tabler-user-question', '/timesheet');
        }
        if ($canViewStock) {
            $damaged = Stock::query()->whereIn('condition', [Stock::CONDITION_DAMAGED, Stock::CONDITION_POOR])->sum('quantity');
            $add($damaged > 0, 'assets', 'stockInspection', 'stockInspectionDetail', ['count' => (int) $damaged], 'low', 'tabler-package-off', '/stock');
        }

        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($actions, fn (array $a, array $b) => ($rank[$a['severity']] ?? 3) <=> ($rank[$b['severity']] ?? 3));
        return array_slice($actions, 0, 8);
    }

    private function attentionItems(Builder $activeTasks, Carbon $now, array $receivables, bool $canViewStock): array
    {
        $items = [];
        $overdueTasks = (clone $activeTasks)->whereDate('due_date', '<', $now->toDateString())->count();
        if ($overdueTasks > 0) {
            $items[] = ['type' => 'tasks', 'color' => 'error', 'icon' => 'tabler-alert-circle', 'count' => $overdueTasks, 'title' => 'Overdue tasks need an owner'];
        }
        if ($receivables !== []) {
            $overdueInvoices = collect($receivables)->where('is_overdue', true)->count();
            if ($overdueInvoices > 0) {
                $items[] = ['type' => 'invoices', 'color' => 'warning', 'icon' => 'tabler-file-invoice', 'count' => $overdueInvoices, 'title' => 'Invoices are past their due date'];
            }
        }
        if ($canViewStock) {
            $attentionStock = Stock::query()->whereIn('condition', [Stock::CONDITION_DAMAGED, Stock::CONDITION_POOR])->sum('quantity');
            if ($attentionStock > 0) {
                $items[] = ['type' => 'stock', 'color' => 'warning', 'icon' => 'tabler-package-off', 'count' => (int) $attentionStock, 'title' => 'Stock items need inspection'];
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

    private function incomeByBase(Carbon $start, Carbon $end): array
    {
        return Income::query()
            ->whereBetween('date', [$start, $end])
            ->selectRaw('base_curr_shrt, SUM(base_amount) as total')
            ->groupBy('base_curr_shrt')
            ->pluck('total', 'base_curr_shrt')
            ->map(fn ($value) => round((float) $value, 4))
            ->all();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function utcRange(Carbon $start, Carbon $end): array
    {
        return [$start->copy()->utc(), $end->copy()->utc()];
    }
}
