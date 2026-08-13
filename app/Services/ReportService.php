<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Board;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\Salary;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Models\Visitor;
use App\Models\WorthyEmployee;
use App\Support\CompanyProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public const TYPES = ['financial', 'payroll', 'attendance', 'operations'];

    public function __construct(private readonly CompanyProfile $company) {}

    public function catalog(User $user): array
    {
        abort_unless($user->hasPermission('view_reports'), 403, 'You are not authorized to view reports.');
        $types = collect(self::TYPES)->filter(fn (string $type) => $this->canView($user, $type))->values();

        return [
            'types' => $types->map(fn (string $type) => ['value' => $type, 'category' => $type, 'title' => "reports.types.{$type}"])->all(),
            'employees' => $this->canSeeEmployees($user) ? Employee::selectable()->orderBy('name')->get(['id', 'name', 'office_id'])->all() : [],
            'offices' => $user->hasPermission('view_offices') || $user->hasPermission('view_attendance')
                ? Office::query()->orderBy('name')->get(['id', 'name', 'code'])->all() : [],
            'customers' => $user->hasPermission('view_customers') || $user->hasPermission('view_financial_reports')
                ? Customer::query()->orderBy('name')->get(['id', 'name'])->all() : [],
            'currencies' => $user->hasPermission('view_currencies') || $user->hasPermission('view_financial_reports')
                ? Currency::query()->orderByDesc('is_base')->orderBy('code')->get(['id', 'code', 'symbol', 'decimal_places', 'is_base'])->all() : [],
            'boards' => $user->hasPermission('view_all_tasks') ? Board::query()->orderBy('title')->get(['id', 'title'])->all() : [],
            'contract_types' => $user->hasPermission('view_contracts')
                ? \App\Models\ContractTemplate::query()->distinct()->orderBy('type')->pluck('type')->filter()->values()->all() : [],
            'timezone' => $this->company->timezone(),
        ];
    }

    public function generate(string $type, array $filters, User $user): array
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        abort_unless($this->canView($user, $type), 403, 'You are not authorized to view this report.');
        [$start, $end] = $this->range($filters);
        $sections = match ($type) {
            'financial' => $this->financial($filters, $start, $end),
            'payroll' => $this->payroll($filters, $start, $end, $user),
            'attendance' => $this->attendance($filters, $start, $end),
            'operations' => $this->operations($filters, $start, $end, $user),
        };
        $setting = Setting::query()->latest('id')->first();

        return [
            'type' => $type,
            'category' => $type,
            'title' => "reports.types.{$type}",
            'filters' => [...$filters, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
            'sections' => array_values($sections),
            'meta' => [
                'generated_at' => now('UTC')->toISOString(),
                'timezone' => $this->company->timezone(),
                'company' => [
                    'name' => $setting?->original_company_name ?: $setting?->website_title,
                    'short_name' => $setting?->short_company_name,
                    'logo' => $setting?->website_logo,
                    'address' => $setting?->physical_address,
                    'email' => $setting?->company_email,
                    'phone' => $setting?->phone,
                ],
                'currency_basis' => 'pages.reports.currencyBasis',
            ],
        ];
    }

    public function canView(User $user, string $type): bool
    {
        if (! $user->hasPermission('view_reports')) return false;

        return match ($type) {
            'financial' => $user->hasPermission('view_financial_reports'),
            'payroll' => $user->hasPermission('view_salaries') || $user->hasPermission('view_bonuses'),
            'attendance' => $user->hasPermission('view_attendance'),
            'operations' => $user->hasPermission('view_contracts') || $user->hasPermission('view_customers')
                || $user->hasPermission('view_visitors') || $user->hasPermission('view_all_tasks'),
            default => false,
        };
    }

    public function canExport(User $user, string $type): bool
    {
        if (! $user->hasPermission('export_reports')) return false;
        return $type === 'financial' ? $user->hasPermission('export_financial_reports') : $this->canView($user, $type);
    }

    private function financial(array $filters, Carbon $start, Carbon $end): array
    {
        $invoiceQuery = Invoice::query()->with('customer')->whereBetween('created_at', [$start->copy()->utc(), $end->copy()->utc()])
            ->when($filters['customer_id'] ?? null, fn (Builder $q, $id) => $q->where('customer_id', $id))
            ->when($filters['currency_id'] ?? null, fn (Builder $q, $id) => $q->where('currency_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status));
        $paid = Income::query()->whereNotNull('invoice_id')->selectRaw('invoice_id, SUM(total_amount) AS paid_base')->groupBy('invoice_id')->pluck('paid_base', 'invoice_id');
        $invoices = $invoiceQuery->latest()->get()->map(function (Invoice $invoice) use ($paid) {
            $base = round((float) $invoice->amount * (float) $invoice->currency_rate, 4);
            $paidBase = min($base, (float) ($paid[$invoice->id] ?? 0));
            return [
                'number' => $invoice->invoice_number, 'date' => $invoice->created_at?->toDateString(),
                'customer' => $invoice->customer?->name ?? $invoice->deptor, 'status' => $invoice->status,
                'amount' => (float) $invoice->amount, 'currency' => $invoice->currency_shortname,
                'rate_to_base' => (float) $invoice->currency_rate, 'base_amount' => $base,
                'paid_base' => round($paidBase, 4), 'outstanding_base' => round(max(0, $base - $paidBase), 4),
            ];
        });
        $income = $this->cashRows(Income::query(), $filters, $start, $end, 'incomeCurrency')->map(fn (Income $row) => $this->cashRow($row));
        $expenses = $this->cashRows(Expense::query(), $filters, $start, $end, 'expenseCurrency')->map(fn (Expense $row) => $this->cashRow($row));
        $invoiceBase = (float) $invoices->sum('base_amount');
        $incomeBase = (float) $income->sum('base_amount');
        $expenseBase = (float) $expenses->sum('base_amount');

        return [
            $this->section('invoices', ['number', 'date', 'customer', 'status', 'amount', 'currency', 'rate_to_base', 'base_amount', 'paid_base', 'outstanding_base'], $invoices, [
                'count' => $invoices->count(), 'base_amount' => round($invoiceBase, 4),
                'outstanding_base' => round((float) $invoices->sum('outstanding_base'), 4), 'by_currency' => $this->byCurrency($invoices),
            ]),
            $this->section('income', ['date', 'reference', 'customer', 'type', 'description', 'amount', 'quantity', 'currency', 'rate_to_base', 'base_amount'], $income, [
                'count' => $income->count(), 'base_amount' => round($incomeBase, 4), 'by_currency' => $this->byCurrency($income),
            ]),
            $this->section('expenses', ['date', 'customer', 'type', 'description', 'amount', 'quantity', 'currency', 'rate_to_base', 'base_amount'], $expenses, [
                'count' => $expenses->count(), 'base_amount' => round($expenseBase, 4), 'by_currency' => $this->byCurrency($expenses),
            ]),
            $this->section('net_cash_flow', ['income_base', 'expense_base', 'net_base'], collect([[
                'income_base' => round($incomeBase, 4), 'expense_base' => round($expenseBase, 4), 'net_base' => round($incomeBase - $expenseBase, 4),
            ]]), ['invoiced_base' => round($invoiceBase, 4), 'net_base' => round($incomeBase - $expenseBase, 4)]),
        ];
    }

    private function payroll(array $filters, Carbon $start, Carbon $end, User $user): array
    {
        $sections = [];
        if ($user->hasPermission('view_salaries')) {
            $salaries = Salary::query()->with(['employee.office', 'deductions'])
                ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->where('employee_id', $id))
                ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('office_id', $id)))
                ->when($filters['currency_id'] ?? null, fn (Builder $q, $id) => $q->where('currency_id', $id))->latest('payment_date')->get()
                ->map(function (Salary $salary) {
                    $rate = (float) ($salary->exchange_rate_to_base ?: 1);
                    $deductions = (float) $salary->deductions->sum('amount');
                    return [
                        'payslip' => $salary->payslip_number, 'payment_date' => $salary->payment_date?->format('Y-m-d'),
                        'salary_month' => $salary->salary_month, 'employee' => $salary->employee?->name,
                        'office' => $salary->employee?->office?->name, 'amount' => (float) $salary->amount_paid,
                        'bonus' => (float) $salary->bonus, 'deductions' => $deductions,
                        'net_amount' => (float) $salary->total_amount - $deductions, 'currency' => $salary->currency_code,
                        'rate_to_base' => $rate, 'base_amount' => (float) ($salary->total_amount_base ?? ((float) $salary->total_amount * $rate)),
                        'deductions_base' => round($deductions * $rate, 4),
                    ];
                });
            $sections[] = $this->section('salary_payments', ['payslip', 'payment_date', 'salary_month', 'employee', 'office', 'amount', 'bonus', 'deductions', 'net_amount', 'currency', 'rate_to_base', 'base_amount', 'deductions_base'], $salaries, [
                'count' => $salaries->count(), 'base_amount' => round((float) $salaries->sum('base_amount'), 4),
                'deductions_base' => round((float) $salaries->sum('deductions_base'), 4), 'by_currency' => $this->byCurrency($salaries),
            ]);
        }
        if ($user->hasPermission('view_bonuses')) {
            $bonuses = WorthyEmployee::query()->with(['employee.office'])->whereBetween('bonus_month', [$start->toDateString(), $end->toDateString()])
                ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->where('employee_id', $id))
                ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('office_id', $id)))
                ->when($filters['currency_id'] ?? null, fn (Builder $q, $id) => $q->where(function (Builder $currency) use ($id) {
                    $currency->where('granted_currency_id', $id)->orWhere('selected_currency_id', $id)->orWhere('currency_id', $id);
                }))
                ->when(isset($filters['status']), fn (Builder $q) => $q->where('is_granted', $filters['status'] === 'granted'))
                ->latest('bonus_month')->get()->map(fn (WorthyEmployee $bonus) => [
                    'month' => $bonus->bonus_month?->format('Y-m-d'), 'employee' => $bonus->employee?->name,
                    'office' => $bonus->employee?->office?->name, 'reason' => $bonus->eligibility_reason,
                    'performance_score' => (float) $bonus->performance_score, 'achievement_details' => $bonus->achievement_details,
                    'status' => $bonus->is_granted ? 'granted' : 'pending',
                    'amount' => (float) ($bonus->granted_amount ?? $bonus->selected_amount),
                    'currency' => $bonus->granted_currency_code ?? $bonus->selected_currency_code,
                    'rate_to_base' => (float) ($bonus->granted_rate_to_base ?? $bonus->selected_rate_to_base),
                    'base_amount' => (float) ($bonus->granted_base_amount ?? $bonus->selected_base_amount),
                    'source' => $bonus->is_automatic ? 'automatic_task_nomination' : 'manual_nomination',
                ]);
            $sections[] = $this->section('bonuses', ['month', 'employee', 'office', 'reason', 'performance_score', 'achievement_details', 'status', 'amount', 'currency', 'rate_to_base', 'base_amount', 'source'], $bonuses, [
                'count' => $bonuses->count(), 'base_amount' => round((float) $bonuses->sum('base_amount'), 4), 'by_currency' => $this->byCurrency($bonuses),
            ]);
        }
        return $sections;
    }

    private function attendance(array $filters, Carbon $start, Carbon $end): array
    {
        $rows = Attendance::query()->with(['staff.office', 'office'])->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->where('employee_id', $id))
            ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->where('office_id', $id))
            ->when($filters['attendance_state'] ?? null, function (Builder $q, string $state) {
                if ($state === 'incomplete') return $q->where('status', 'present')->where('missing_punch_state', '!=', 'none');
                if ($state === 'complete') return $q->where('status', 'present')->where('missing_punch_state', 'none');
                return $q->where('status', $state);
            })->latest('date')->get()->map(fn (Attendance $row) => [
                'date' => $row->date?->format('Y-m-d'), 'employee' => $row->staff?->name,
                'office' => $row->office?->name ?? $row->staff?->office?->name, 'status' => $row->status,
                'check_in_utc' => $row->checked_in_at?->utc()->toISOString(), 'check_out_utc' => $row->checked_out_at?->utc()->toISOString(),
                'gross_minutes' => $row->gross_minutes, 'break_minutes' => (int) ($row->break_minutes ?? 0),
                'worked_minutes' => $row->net_worked_minutes, 'required_minutes' => $row->required_minutes,
                'late_minutes' => (int) $row->late_arrival_minutes, 'early_minutes' => (int) $row->early_departure_minutes,
                'overtime_minutes' => (int) $row->overtime_minutes, 'punch_state' => $row->missing_punch_state,
                'overnight' => (bool) $row->is_overnight_shift,
            ]);

        return [$this->section('attendance', ['date', 'employee', 'office', 'status', 'check_in_utc', 'check_out_utc', 'gross_minutes', 'break_minutes', 'worked_minutes', 'required_minutes', 'late_minutes', 'early_minutes', 'overtime_minutes', 'punch_state', 'overnight'], $rows, [
            'records' => $rows->count(), 'present' => $rows->where('status', 'present')->count(),
            'absent' => $rows->where('status', 'absent')->count(), 'paid_leave' => $rows->where('status', 'paid-off-day')->count(),
            'incomplete' => $rows->where('status', 'present')->where('punch_state', '!=', 'none')->count(),
            'worked_minutes' => (int) $rows->sum('worked_minutes'), 'overtime_minutes' => (int) $rows->sum('overtime_minutes'),
        ])];
    }

    private function operations(array $filters, Carbon $start, Carbon $end, User $user): array
    {
        $sections = [];
        if ($user->hasPermission('view_contracts')) {
            $rows = Contract::query()->with('template')->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
                ->when($filters['contract_type'] ?? null, fn (Builder $q, $type) => $q->whereHas('template', fn (Builder $t) => $t->where('type', $type)))
                ->latest('start_date')->get()->map(fn (Contract $row) => [
                    'number' => $row->contract_number, 'date' => $row->start_date?->format('Y-m-d'),
                    'title' => $row->template?->name, 'type' => $row->template?->type, 'counterparty' => $row->counterparty,
                    'status' => $row->is_cancelled ? 'cancelled' : $row->status,
                ]);
            $sections[] = $this->section('contracts', ['number', 'date', 'title', 'type', 'counterparty', 'status'], $rows, ['count' => $rows->count()]);
        }
        if ($user->hasPermission('view_customers')) {
            $rows = Customer::query()->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->when($filters['customer_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))->latest('date')->get()
                ->map(fn (Customer $row) => ['date' => $row->date?->format('Y-m-d'), 'name' => $row->name, 'company' => $row->company, 'contact' => $row->contact, 'marketer' => $row->marketer]);
            $sections[] = $this->section('customers', ['date', 'name', 'company', 'contact', 'marketer'], $rows, ['count' => $rows->count()]);
        }
        if ($user->hasPermission('view_visitors')) {
            $rows = Visitor::query()->whereBetween('date', [$start->toDateString(), $end->toDateString()])->latest('date')->get()
                ->map(fn (Visitor $row) => ['date' => $row->date?->format('Y-m-d'), 'name' => $row->name, 'company' => $row->company, 'contact' => $row->contact, 'service' => $row->service, 'marketer' => $row->marketer]);
            $sections[] = $this->section('visitors', ['date', 'name', 'company', 'contact', 'service', 'marketer'], $rows, ['count' => $rows->count()]);
        }
        if ($user->hasPermission('view_all_tasks')) {
            $today = now($this->company->timezone())->startOfDay();
            $rows = Task::query()->with(['board', 'users.employee'])->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('due_date', [$start->toDateString(), $end->toDateString()]);
            })->when($filters['board_id'] ?? null, fn (Builder $q, $id) => $q->where('board_id', $id))
                ->latest('due_date')->get()->map(function (Task $row) use ($today) {
                    $status = $row->progress >= 100 ? 'completed' : ($row->due_date?->lt($today) ? 'overdue' : 'in_progress');
                    return [
                        'title' => $row->title, 'board' => $row->board?->title, 'start_date' => $row->start_date?->format('Y-m-d'),
                        'due_date' => $row->due_date?->format('Y-m-d'), 'priority' => $row->priority, 'progress' => (int) $row->progress,
                        'status' => $status, 'employees' => $row->users->map(fn (User $user) => $user->employee?->name ?? $user->name)->filter()->join(', '),
                    ];
                })->when($filters['status'] ?? null, fn (Collection $rows, $status) => $rows->where('status', $status)->values());
            $sections[] = $this->section('tasks', ['title', 'board', 'start_date', 'due_date', 'priority', 'progress', 'status', 'employees'], $rows, [
                'count' => $rows->count(), 'completed' => $rows->where('status', 'completed')->count(),
                'overdue' => $rows->where('status', 'overdue')->count(),
                'average_progress' => round((float) $rows->avg('progress'), 2),
            ]);
        }
        return $sections;
    }

    private function cashRows(Builder $query, array $filters, Carbon $start, Carbon $end, string $currencyRelation): Collection
    {
        return $query->with(['customer', $currencyRelation])->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($filters['customer_id'] ?? null, fn (Builder $q, $id) => $q->where('customer_id', $id))
            ->when($filters['currency_id'] ?? null, fn (Builder $q, $id) => $q->where('currency_id', $id))
            ->latest('date')->get();
    }

    private function cashRow(Income|Expense $row): array
    {
        $currency = $row instanceof Income ? $row->incomeCurrency : $row->expenseCurrency;
        return [
            'date' => $row->date?->format('Y-m-d'), 'reference' => $row instanceof Income ? $row->receipt_number : null,
            'customer' => $row->customer?->name, 'type' => $row->type, 'description' => $row->description,
            'amount' => (float) $row->amount, 'quantity' => (int) $row->quantity,
            'currency' => $currency?->code ?? 'UNK', 'rate_to_base' => (float) $row->rate_to_base,
            'base_amount' => (float) $row->total_amount,
        ];
    }

    private function section(string $key, array $columns, Collection $rows, array $totals): array
    {
        return ['key' => $key, 'title' => "reports.sections.{$key}", 'columns' => $columns, 'rows' => $rows->values()->all(), 'totals' => $totals];
    }

    private function byCurrency(Collection $rows): array
    {
        return $rows->groupBy(fn (array $row) => $row['currency'] ?: 'UNK')->map(fn (Collection $items) => round((float) $items->sum(fn (array $row) => ((float) ($row['amount'] ?? 0)) * ((int) ($row['quantity'] ?? 1))), 4))->all();
    }

    private function range(array $filters): array
    {
        $timezone = $this->company->timezone();
        $end = Carbon::parse($filters['end_date'] ?? now($timezone)->toDateString(), $timezone)->endOfDay();
        $start = Carbon::parse($filters['start_date'] ?? $end->copy()->startOfMonth()->toDateString(), $timezone)->startOfDay();
        return [$start, $end];
    }

    private function canSeeEmployees(User $user): bool
    {
        return $user->hasPermission('view_employees') || $user->hasPermission('view_attendance') || $user->hasPermission('view_salaries');
    }
}
