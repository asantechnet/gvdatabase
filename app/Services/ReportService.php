<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Board;
use App\Models\Company;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Contract;
use App\Models\Applicant;
use App\Models\Currency;
use App\Models\ContractService;
use App\Models\DailyReport;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\PaidTimeOff;
use App\Models\Salary;
use App\Models\Setting;
use App\Models\Asset;
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
            'applicants' => $user->hasPermission('view_applicants') || $user->hasPermission('view_financial_reports')
                ? Applicant::query()->orderBy('name')->get(['id', 'name'])->all() : [],
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
            'financial' => $this->financial($filters, $start, $end, $user),
            'payroll' => $this->payroll($filters, $start, $end, $user),
            'attendance' => $this->attendance($filters, $start, $end, $user),
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
            'payroll' => $user->hasPermission('view_salaries') || $user->hasPermission('view_bonuses') || $user->hasPermission('view_commissions'),
            'attendance' => $user->hasPermission('view_attendance'),
            'operations' => $user->hasPermission('view_contracts') || $user->hasPermission('view_applicants')
                || $user->hasPermission('view_visitors') || $user->hasPermission('view_all_tasks'),
            default => false,
        };
    }

    public function canExport(User $user, string $type): bool
    {
        if (! $user->hasPermission('export_reports')) return false;
        return $type === 'financial' ? $user->hasPermission('export_financial_reports') : $this->canView($user, $type);
    }

    private function financial(array $filters, Carbon $start, Carbon $end, User $user): array
    {
        $base = $this->currentBase();
        $invoiceQuery = Invoice::query()->with(['applicant', 'currency', 'incomes'])->whereBetween('created_at', [$start->copy()->utc(), $end->copy()->utc()])
            ->when($filters['applicant_id'] ?? null, fn (Builder $q, $id) => $q->where('applicant_id', $id))
            ->when($filters['currency_id'] ?? null, fn (Builder $q, $id) => $q->where('currency_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status));
        $invoices = $invoiceQuery->latest()->get()->map(function (Invoice $invoice) use ($base) {
            $amountBase = $this->currentBaseAmount((float) $invoice->amount, $invoice->currency);
            $paidInvoiceAmount = (float) $invoice->incomes->sum(fn (Income $income) => (float) ($income->invoice_amount ?? ((float) $income->amount * $income->quantity)));
            $paidBase = min($amountBase, $this->currentBaseAmount($paidInvoiceAmount, $invoice->currency));
            return [
                'number' => $invoice->invoice_number, 'date' => $invoice->created_at?->toDateString(),
                'applicant' => $invoice->applicant?->name ?? $invoice->deptor, 'status' => $invoice->status,
                'amount' => (float) $invoice->amount, 'currency' => $invoice->currency_shortname,
                'base_currency' => $base?->code ?? 'BASE', 'rate' => (float) ($invoice->currency?->rate ?? 0), 'base_amount' => round($amountBase, 4),
                'paid_base' => round($paidBase, 4), 'outstanding_base' => round(max(0, $amountBase - $paidBase), 4),
            ];
        });
        $income = $this->cashRows(Income::query(), $filters, $start, $end, 'incomeCurrency')->map(fn (Income $row) => $this->cashRow($row, $base));
        $expenses = $this->cashRows(Expense::query(), $filters, $start, $end, 'expenseCurrency')->map(fn (Expense $row) => $this->cashRow($row, $base));
        $invoiceBase = $this->byBaseCurrency($invoices, 'base_amount');
        $incomeBase = $this->byBaseCurrency($income, 'base_amount');
        $expenseBase = $this->byBaseCurrency($expenses, 'base_amount');
        $netBase = $this->netByBaseCurrency($incomeBase, $expenseBase);

        $sections = [
            $this->section('invoices', ['number', 'date', 'applicant', 'status', 'amount', 'currency', 'base_currency', 'rate', 'base_amount', 'paid_base', 'outstanding_base'], $invoices, [
                'count' => $invoices->count(), 'base_amount_by_base' => $invoiceBase,
                'outstanding_by_base' => $this->byBaseCurrency($invoices, 'outstanding_base'), 'by_currency' => $this->byCurrency($invoices),
            ]),
            $this->section('income', ['date', 'reference', 'applicant', 'type', 'description', 'amount', 'quantity', 'currency', 'base_currency', 'rate', 'base_amount'], $income, [
                'count' => $income->count(), 'base_amount_by_base' => $incomeBase, 'by_currency' => $this->byCurrency($income),
            ]),
            $this->section('expenses', ['date', 'applicant', 'type', 'description', 'amount', 'quantity', 'currency', 'base_currency', 'rate', 'base_amount'], $expenses, [
                'count' => $expenses->count(), 'base_amount_by_base' => $expenseBase, 'by_currency' => $this->byCurrency($expenses),
            ]),
            $this->section('net_cash_flow', ['income_base', 'expense_base', 'net_base'], collect([[
                'income_base' => $incomeBase, 'expense_base' => $expenseBase, 'net_base' => $netBase,
            ]]), ['invoiced_base' => $invoiceBase, 'net_base' => $netBase]),
        ];

        if ($user->hasPermission('view_assets')) {
            $asset = Asset::query()->with(['office:id,name', 'allocations.employee:id,name'])
                ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->where('office_id', $id))
                ->orderBy('item')->get()->map(fn (Asset $row) => [
                    'item' => $row->item, 'model' => $row->model, 'serial_number' => $row->serial_number,
                    'office' => $row->office?->name,
                    'assigned_to' => $row->allocations
                        ->pluck('employee.name')
                        ->filter()
                        ->unique()
                        ->implode(', '),
                    'condition' => $row->condition, 'quantity' => (int) $row->quantity,
                    'unit_price' => (float) $row->price, 'asset_value' => round((float) $row->price * (int) $row->quantity, 2),
                ]);
            $sections[] = $this->section('asset_assets', ['item', 'model', 'serial_number', 'office', 'assigned_to', 'condition', 'quantity', 'unit_price', 'asset_value'], $asset, [
                'records' => $asset->count(), 'units' => (int) $asset->sum('quantity'),
                'asset_value' => round((float) $asset->sum('asset_value'), 2),
                'needs_attention' => $asset->whereIn('condition', ['poor', 'damaged'])->sum('quantity'),
            ]);
        }

        return $sections;
    }

    private function payroll(array $filters, Carbon $start, Carbon $end, User $user): array
    {
        $sections = [];
        if ($user->hasPermission('view_salaries')) {
            $baseCurrency = $this->currentBase();
            $salaries = Salary::query()->with(['employee.office', 'deductions', 'currency'])
                ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->where('employee_id', $id))
                ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('office_id', $id)))
                ->when($filters['currency_id'] ?? null, fn (Builder $q, $id) => $q->where('currency_id', $id))->latest('payment_date')->get()
                ->map(function (Salary $salary) use ($baseCurrency) {
                    $rate = (float) ($salary->currency?->rate ?: 1);
                    $deductions = (float) $salary->deductions->sum('amount');
                    return [
                        'payslip' => $salary->payslip_number, 'payment_date' => $salary->payment_date?->format('Y-m-d'),
                        'salary_month' => $salary->salary_month, 'employee' => $salary->employee?->name,
                        'office' => $salary->employee?->office?->name, 'amount' => (float) $salary->amount_paid,
                        'bonus' => (float) $salary->bonus, 'deductions' => $deductions,
                        'net_amount' => (float) $salary->total_amount - $deductions, 'currency' => $salary->currency_code,
                        'base_currency' => $baseCurrency?->code ?? 'BASE', 'rate' => $rate, 'base_amount' => $this->currentBaseAmount((float) $salary->total_amount, $salary->currency),
                        'deductions_base' => $this->currentBaseAmount($deductions, $salary->currency),
                    ];
                });
            $sections[] = $this->section('salary_payments', ['payslip', 'payment_date', 'salary_month', 'employee', 'office', 'amount', 'bonus', 'deductions', 'net_amount', 'currency', 'base_currency', 'rate', 'base_amount', 'deductions_base'], $salaries, [
                'count' => $salaries->count(), 'base_amount_by_base' => $this->byBaseCurrency($salaries, 'base_amount'),
                'deductions_by_base' => $this->byBaseCurrency($salaries, 'deductions_base'), 'by_currency' => $this->byCurrency($salaries),
            ]);
        }
        if ($user->hasPermission('view_bonuses')) {
            $baseCurrency = Currency::query()->where('is_base', true)->first() ?? Currency::query()->orderBy('id')->first();
            $bonuses = WorthyEmployee::query()->with(['employee.office'])->whereBetween('bonus_month', [$start->toDateString(), $end->toDateString()])
                ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->where('employee_id', $id))
                ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('office_id', $id)))
                ->when(isset($filters['status']), fn (Builder $q) => $q->where('is_granted', $filters['status'] === 'granted'))
                ->latest('bonus_month')->get()->map(fn (WorthyEmployee $bonus) => [
                    'month' => $bonus->bonus_month?->format('Y-m-d'), 'employee' => $bonus->employee?->name,
                    'office' => $bonus->employee?->office?->name, 'reason' => $bonus->selected_type,
                    'performance_score' => (float) $bonus->performance_score, 'achievement_details' => $bonus->achievement_details,
                    'status' => $bonus->is_granted ? 'granted' : 'pending',
                    'amount' => (float) ($bonus->granted_amount ?? $bonus->selected_amount),
                    'currency' => $baseCurrency?->code ?? 'BASE',
                    'base_currency' => $baseCurrency?->code ?? 'BASE',
                    'rate' => 1.0,
                    'base_amount' => (float) ($bonus->granted_amount ?? $bonus->selected_amount),
                    'source' => $bonus->is_automatic ? 'automatic_task_nomination' : 'manual_nomination',
                ]);
            $sections[] = $this->section('bonuses', ['month', 'employee', 'office', 'reason', 'performance_score', 'achievement_details', 'status', 'amount', 'currency', 'base_currency', 'base_amount', 'source'], $bonuses, [
                'count' => $bonuses->count(), 'base_amount_by_base' => $this->byBaseCurrency($bonuses, 'base_amount'), 'by_currency' => $this->byCurrency($bonuses),
            ]);
        }

        if ($user->hasPermission('view_commissions')) {
            $settlements = CommissionSettlement::query()->with(['employee.office', 'baseCurrency'])
                ->whereBetween('period_end', [$start->toDateString(), $end->toDateString()])
                ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->where('employee_id', $id))
                ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('office_id', $id)))
                ->when($filters['currency_id'] ?? null, fn (Builder $q, $id) => $q->where('base_currency_id', $id))
                ->latest('period_end')->get()->map(fn (CommissionSettlement $row) => [
                    'settlement' => $row->settlement_number,
                    'period_end' => $row->period_end?->format('Y-m-d'),
                    'employee' => $row->employee?->name,
                    'office' => $row->employee?->office?->name,
                    'status' => $row->status,
                    'amount' => (float) $row->gross_base_amount,
                    'currency' => $row->baseCurrency?->code,
                    'base_currency' => $row->baseCurrency?->code,
                    'base_amount' => (float) $row->gross_base_amount,
                    'payment_date' => $row->payment_date?->format('Y-m-d'),
                ]);
            $sections[] = $this->section('commission_settlements', ['settlement', 'period_end', 'employee', 'office', 'status', 'amount', 'currency', 'base_currency', 'base_amount', 'payment_date'], $settlements, [
                'count' => $settlements->count(),
                'base_amount_by_base' => $this->byBaseCurrency($settlements, 'base_amount'),
                'by_currency' => $this->byCurrency($settlements),
            ]);

            $pending = CommissionAccrual::query()->with(['employee.office', 'baseCurrency', 'invoice', 'service'])
                ->whereBetween('earned_at', [$start->toDateString(), $end->toDateString()])
                ->whereIn('status', ['pending', 'approved'])
                ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->where('employee_id', $id))
                ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('office_id', $id)))
                ->when($filters['currency_id'] ?? null, fn (Builder $q, $id) => $q->where('base_currency_id', $id))
                ->latest('earned_at')->get()->map(fn (CommissionAccrual $row) => [
                    'earned_at' => $row->earned_at?->format('Y-m-d'), 'employee' => $row->employee?->name,
                    'office' => $row->employee?->office?->name, 'invoice' => $row->invoice?->invoice_number,
                    'service' => $row->service?->type, 'status' => $row->status,
                    'amount' => (float) $row->commission_base_amount, 'currency' => $row->baseCurrency?->code,
                    'base_currency' => $row->baseCurrency?->code, 'base_amount' => (float) $row->commission_base_amount,
                ]);
            $sections[] = $this->section('commission_liabilities', ['earned_at', 'employee', 'office', 'invoice', 'service', 'status', 'amount', 'currency', 'base_currency', 'base_amount'], $pending, [
                'count' => $pending->count(), 'base_amount_by_base' => $this->byBaseCurrency($pending, 'base_amount'), 'by_currency' => $this->byCurrency($pending),
            ]);
        }

        if ($user->hasPermission('view_employees')) {
            $employees = Employee::selectable()->with(['office:id,name', 'position:id,title'])
                ->whereBetween('join_date', [$start->toDateString(), $end->toDateString()])
                ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
                ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->where('office_id', $id))
                ->latest('join_date')->get()->map(fn (Employee $row) => [
                    'join_date' => $row->join_date?->format('Y-m-d'), 'employee' => $row->name,
                    'office' => $row->office?->name, 'position' => $row->position?->title,
                    'department' => $row->department, 'employment_type' => $row->employment_type,
                ]);
            $sections[] = $this->section('employee_roster', ['join_date', 'employee', 'office', 'position', 'department', 'employment_type'], $employees, [
                'joined_in_period' => $employees->count(),
                'company_headcount' => Employee::selectable()->count(),
            ]);
        }

        if ($user->hasPermission('view_paid_time_off')) {
            $leave = PaidTimeOff::query()->with(['employee.office:id,name'])
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->where('employee_id', $id))
                ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('office_id', $id)))
                ->latest('date')->get()->map(fn (PaidTimeOff $row) => [
                    'date' => $row->date?->format('Y-m-d'), 'employee' => $row->employee?->name,
                    'office' => $row->employee?->office?->name, 'reason' => $row->reason,
                    'status' => $row->is_granted ? 'granted' : 'pending',
                    'classification' => $row->is_paid === null ? 'unclassified' : ($row->is_paid ? 'paid' : 'unpaid'),
                ]);
            $sections[] = $this->section('paid_time_off', ['date', 'employee', 'office', 'reason', 'status', 'classification'], $leave, [
                'requests' => $leave->count(), 'granted' => $leave->where('status', 'granted')->count(),
                'pending' => $leave->where('status', 'pending')->count(), 'unpaid' => $leave->where('classification', 'unpaid')->count(),
            ]);
        }
        return $sections;
    }

    private function attendance(array $filters, Carbon $start, Carbon $end, User $user): array
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

        $sections = [$this->section('attendance', ['date', 'employee', 'office', 'status', 'check_in_utc', 'check_out_utc', 'gross_minutes', 'break_minutes', 'worked_minutes', 'required_minutes', 'late_minutes', 'early_minutes', 'overtime_minutes', 'punch_state', 'overnight'], $rows, [
            'records' => $rows->count(), 'present' => $rows->where('status', 'present')->count(),
            'absent' => $rows->where('status', 'absent')->count(), 'paid_leave' => $rows->where('status', 'paid-leave')->count(),
            'incomplete' => $rows->where('status', 'present')->where('punch_state', '!=', 'none')->count(),
            'worked_minutes' => (int) $rows->sum('worked_minutes'), 'overtime_minutes' => (int) $rows->sum('overtime_minutes'),
        ])];

        $dailyReports = DailyReport::query()->with(['user.employee.office:id,name'])
            ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
            ->when($filters['employee_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('user.employee', fn (Builder $e) => $e->whereKey($id)))
            ->when($filters['office_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('user.employee', fn (Builder $e) => $e->where('office_id', $id)))
            ->latest('report_date')->get()->map(fn (DailyReport $row) => [
                'date' => $row->report_date?->format('Y-m-d'),
                'employee' => $row->user?->employee?->name ?? $row->user?->name,
                'office' => $row->user?->employee?->office?->name,
                'summary' => $row->summary, 'achievements' => $row->achievements,
                'challenges' => $row->challenges, 'support_needed' => $row->support_needed,
            ]);
        $sections[] = $this->section('daily_reports', ['date', 'employee', 'office', 'summary', 'achievements', 'challenges', 'support_needed'], $dailyReports, [
            'submitted' => $dailyReports->count(),
            'support_requested' => $dailyReports->filter(fn (array $row) => filled($row['support_needed']))->count(),
        ]);

        return $sections;
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
        if ($user->hasPermission('view_applicants')) {
            $rows = Applicant::query()->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->when($filters['applicant_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))->latest('date')->get()
                ->map(fn (Applicant $row) => ['date' => $row->date?->format('Y-m-d'), 'name' => $row->name, 'contact' => $row->contact, 'marketer' => $row->marketer]);
            $sections[] = $this->section('applicants', ['date', 'name', 'contact', 'marketer'], $rows, ['count' => $rows->count()]);

            $services = ContractService::query()->with(['contract.applicant:id,name', 'service:id,type', 'currency:id,code'])
                ->whereBetween('contract_service.created_at', [$start->copy()->utc(), $end->copy()->utc()])
                ->when($filters['applicant_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('contract', fn ($contracts) => $contracts->where('applicant_id', $id)))
                ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->whereHas('contract', fn ($contracts) => $contracts->where('status', $status)))
                ->latest('contract_service.id')->get()->map(fn (ContractService $row) => [
                'applicant' => $row->contract?->applicant?->name,
                    'service' => $row->service?->type, 'status' => $row->contract?->status,
                    'fee' => $row->fee === null ? null : (float) $row->fee,
                    'currency' => $row->currency?->code,
                ]);
            $sections[] = $this->section('contract_services', ['applicant', 'service', 'status', 'fee', 'currency'], $services, [
                'commitments' => $services->count(), 'active' => $services->where('status', 'active')->count(),
            ]);
        }
        if ($user->hasPermission('view_companies')) {
            $companies = Company::query()->whereBetween('created_at', [$start->copy()->utc(), $end->copy()->utc()])
                ->latest()->get()->map(fn (Company $row) => [
                    'company' => $row->trade_name ?: $row->legal_name, 'industry' => $row->industry,
                    'city' => $row->city, 'contact' => $row->phone ?: $row->email,
                    'representative' => $row->representative_name, 'status' => $row->is_active ? 'active' : 'inactive',
                ]);
            $sections[] = $this->section('companies', ['company', 'industry', 'city', 'contact', 'representative', 'status'], $companies, [
                'count' => $companies->count(), 'active' => $companies->where('status', 'active')->count(),
            ]);
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
        return $query->with(['applicant', $currencyRelation])->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($filters['applicant_id'] ?? null, fn (Builder $q, $id) => $q->where('applicant_id', $id))
            ->when($filters['currency_id'] ?? null, fn (Builder $q, $id) => $q->where('currency_id', $id))
            ->latest('date')->get();
    }

    private function cashRow(Income|Expense $row, ?Currency $base): array
    {
        $currency = $row instanceof Income ? $row->incomeCurrency : $row->expenseCurrency;
        return [
            'date' => $row->date?->format('Y-m-d'), 'reference' => $row instanceof Income ? $row->receipt_number : null,
            'applicant' => $row->applicant?->name, 'type' => $row->type, 'description' => $row->description,
            'amount' => (float) $row->amount, 'quantity' => (int) $row->quantity,
            'currency' => $currency?->code ?? $row->cnv_curr_shrt ?? 'UNK',
            'base_currency' => $base?->code ?? 'BASE',
            'rate' => (float) ($currency?->rate ?? 0),
            'base_amount' => $this->currentBaseAmount((float) $row->amount * $row->quantity, $currency),
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

    private function byBaseCurrency(Collection $rows, string $amountKey): array
    {
        return $rows
            ->groupBy(fn (array $row) => $row['base_currency'] ?: 'UNK')
            ->map(fn (Collection $items) => round((float) $items->sum(fn (array $row) => (float) ($row[$amountKey] ?? 0)), 4))
            ->all();
    }

    private function netByBaseCurrency(array $income, array $expenses): array
    {
        return collect(array_unique([...array_keys($income), ...array_keys($expenses)]))
            ->mapWithKeys(fn (string $base) => [$base => round((float) ($income[$base] ?? 0) - (float) ($expenses[$base] ?? 0), 4)])
            ->all();
    }

    private function currentBase(): ?Currency
    {
        return Currency::query()->where('is_base', true)->first()
            ?? Currency::query()->orderBy('id')->first();
    }

    private function currentBaseAmount(float $amount, ?Currency $currency): float
    {
        $rate = (float) ($currency?->rate ?? 0);
        return $rate > 0 ? round($amount / $rate, 4) : 0.0;
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
        return $user->hasPermission('view_employees') || $user->hasPermission('view_attendance')
            || $user->hasPermission('view_salaries') || $user->hasPermission('view_commissions');
    }
}
