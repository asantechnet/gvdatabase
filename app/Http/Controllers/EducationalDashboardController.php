<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantUniversity;
use App\Models\Contract;
use App\Models\Country;
use App\Models\EducationalBonusSource;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Salary;
use App\Models\Task;
use App\Support\BuiltInService;
use App\Support\EmployeeScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Agency operations overview for the educational-visa workflow
 * (Implementation Plan §29 — Dashboard Redesign).
 *
 * Every metric is derived from authoritative operational rows at request
 * time — no stored counters — and every figure links back to the list
 * it was counted from. Read-only: this controller never writes.
 */
class EducationalDashboardController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', $request->filled('date_from') ? 'after_or_equal:date_from' : 'nullable'],
            'stale_days' => ['sometimes', 'integer', 'min:1', 'max:120'],
        ]);

        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasPermission('view_all_applicants')) {
            $visibleIds = app(EmployeeScope::class)->visibleEmployeeIds($user);
            if (! empty($data['responsible_employee_id'])) {
                abort_unless(in_array((int) $data['responsible_employee_id'], $visibleIds, true), 403, 'auth.forbidden');
                $visibleIds = [(int) $data['responsible_employee_id']];
            }
            // Scope every metric, rather than only case totals. A senior
            // counselor gets the team's read-only picture, never an arbitrary
            // employee chosen through the query string.
            $data['responsible_employee_ids'] = $visibleIds ?: [0];
        }

        $staleDays = (int) ($data['stale_days'] ?? 14);

        return response()->json(['success' => true, 'data' => [
            'pipeline' => $this->pipeline($data),
            'financial' => $this->financial($data),
            'sales' => $this->sales($data),
            'staff' => $this->staff($data),
            'attention' => $this->attention($data, $staleDays),
            // Task and contract workload: the operational half of the agency
            // picture that the financial/pipeline sections do not cover.
            'work' => $this->work($data),
            'countries' => $this->countries($data),
            'universities' => $this->universities($data),
            'operations' => $this->operations($data),
        ]]);
    }

    private function applicantScope(array $filters)
    {
        $query = Applicant::query();
        if (($employeeIds = $this->employeeIds($filters)) !== []) {
            $query->whereIn('responsible_employee_id', $employeeIds);
        }
        foreach (['country_id', 'university_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->whereScholarshipField($field, $filters[$field]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('applicants.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('applicants.created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /** @return array<int, int> */
    private function employeeIds(array $filters): array
    {
        if (! empty($filters['responsible_employee_ids'])) {
            return array_map('intval', $filters['responsible_employee_ids']);
        }

        return ! empty($filters['responsible_employee_id']) ? [(int) $filters['responsible_employee_id']] : [];
    }

    /**
     * The cross-service overview of the per-service workflows.
     *
     * Each column belongs to one service, and it counts that service's contract
     * lines (plus cases that have not bought the service yet), never the shared
     * applicant-level mirror. This keeps a case that buys two services visible
     * in both of their pipelines instead of only in the last one it touched.
     */
    private function pipeline(array $filters): array
    {
        $applicantIds = $this->applicantScope($filters)->select('applicants.id');
        $counts = [];

        $lines = ApplicantContract::query()
            ->whereIn('applicant_id', $applicantIds)
            ->whereNotNull('current_stage_id')
            ->whereHas('contract', fn ($q) => $q->whereNotIn('status', ['cancelled', 'terminated'])->where('is_cancelled', false))
            ->selectRaw('service, current_stage_id, COUNT(*) as total')
            ->groupBy('service', 'current_stage_id')->get();

        // Cases without a line for their own service are still work in progress.
        $unlined = $this->applicantScope($filters)
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('applicant_contract')
                    ->whereColumn('applicant_contract.applicant_id', 'applicants.id')
                    ->whereColumn('applicant_contract.service', 'applicants.service')
                    ->whereNull('applicant_contract.deleted_at');
            })
            ->selectRaw('service, current_stage_id, COUNT(*) as total')
            ->groupBy('service', 'current_stage_id')->get();

        foreach ($lines->concat($unlined) as $row) {
            $key = ($row->service ?? BuiltInService::SCHOLARSHIP).'|'.$row->current_stage_id;
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->total;
        }

        $order = array_flip(BuiltInService::ALL);
        $stages = ApplicantStage::query()->where('is_active', true)->orderBy('sort_order')->get()
            ->sortBy(fn (ApplicantStage $stage) => [$order[$stage->service] ?? 99, $stage->sort_order])
            ->values();

        $columns = $stages->map(fn (ApplicantStage $stage) => [
            'id' => $stage->id,
            'service' => $stage->service,
            'key' => $stage->key,
            'name' => $stage->name,
            'is_terminal' => (bool) $stage->is_terminal,
            'count' => (int) ($counts[($stage->service ?? BuiltInService::SCHOLARSHIP).'|'.$stage->id] ?? 0),
        ]);

        $base = $this->applicantScope($filters);

        return [
            'stages' => $columns,
            'totals' => [
                'active' => (clone $base)->where('status', 'active')->count(),
                'closed' => (clone $base)->where('status', 'closed')->count(),
                'all' => (clone $base)->count(),
            ],
        ];
    }

    private function sales(array $filters): array
    {
        $out = ['contracts' => ['sold' => 0, 'by_currency' => []]];
        $contractQuery = Contract::query()->whereNotIn('status', ['cancelled']);
        if (($employeeIds = $this->employeeIds($filters)) !== []) {
            $contractQuery->where(fn ($query) => $query->whereIn('employee_id', $employeeIds)->orWhereIn('sold_by_employee_id', $employeeIds));
        }
        if (! empty($filters['date_from'])) {
            $contractQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $contractQuery->whereDate('created_at', '<=', $filters['date_to']);
        }
        $out['contracts']['sold'] = $contractQuery->count();
        foreach (['scholarships' => 'scholarship', 'test_offers' => 'test_offer', 'academy' => 'academy', 'private' => 'private'] as $key => $category) {
            $query = ApplicantContract::query()->where('service', $category)
                ->whereHas('contract', fn ($q) => $q->whereNotIn('status', ['cancelled', 'terminated'])->where('is_cancelled', false));
            if (($employeeIds = $this->employeeIds($filters)) !== []) {
                $query->whereIn('sold_by_employee_id', $employeeIds);
            }
            if (! empty($filters['country_id'])) {
                $query->where(function ($q) use ($filters) {
                    $q->whereHas('applicant', fn ($aq) => $aq->whereScholarshipField('country_id', $filters['country_id']));
                });
            }
            if (! empty($filters['university_id'])) {
                $query->whereHas('applicant', fn ($aq) => $aq->whereScholarshipField('university_id', $filters['university_id']));
            }
            if (! empty($filters['date_from'])) {
                $query->whereHas('contract', fn ($contracts) => $contracts->whereDate('start_date', '>=', $filters['date_from']));
            }
            if (! empty($filters['date_to'])) {
                $query->whereHas('contract', fn ($contracts) => $contracts->whereDate('start_date', '<=', $filters['date_to']));
            }
            $rows = $query->selectRaw('currency_id, COUNT(*) as sold, COALESCE(SUM(fee), 0) as revenue')->groupBy('currency_id')->get();
            $byCurrency = $rows->map(fn ($row) => [
                'currency' => $row->currency_id ? DB::table('currencies')->where('id', $row->currency_id)->value('code') : null,
                'sold' => (int) $row->sold,
                'revenue' => (float) $row->revenue,
            ]);

            $out[$key] = ['sold' => $byCurrency->sum('sold'), 'by_currency' => $byCurrency->values()->all()];
        }

        return $out;
    }

    /**
     * Financial values intentionally remain grouped by currency: adding USD,
     * AFN and EUR without a rate snapshot would be misleading.  Amounts come
     * only from posted official rows; approval workflow counts are separate.
     */
    private function financial(array $filters): array
    {
        $scope = function (string $model, string $amount, ?string $dateColumn = 'date') use ($filters): array {
            $query = $model::query();
            if (in_array($model, [Income::class, Expense::class], true)) {
                if (($employeeIds = $this->employeeIds($filters)) !== []) {
                    $query->whereIn('employee_id', $employeeIds);
                }
                if (! empty($filters['country_id']) || ! empty($filters['university_id'])) {
                    $query->whereHas('applicant', function ($q) use ($filters) {
                        $q->when(! empty($filters['country_id']), fn ($aq) => $aq->whereScholarshipField('country_id', $filters['country_id']))
                            ->when(! empty($filters['university_id']), fn ($aq) => $aq->whereScholarshipField('university_id', $filters['university_id']));
                    });
                }
            } elseif ($model === Salary::class && ($employeeIds = $this->employeeIds($filters)) !== []) {
                $query->whereIn('employee_id', $employeeIds);
            }
            if ($dateColumn && ! empty($filters['date_from'])) {
                $query->whereDate($dateColumn, '>=', $filters['date_from']);
            }
            if ($dateColumn && ! empty($filters['date_to'])) {
                $query->whereDate($dateColumn, '<=', $filters['date_to']);
            }

            return $query->selectRaw("currency_id, COUNT(*) as records, COALESCE(SUM({$amount}), 0) as amount")
                ->groupBy('currency_id')->get()->map(fn ($row) => [
                    'currency' => $row->currency_id ? DB::table('currencies')->where('id', $row->currency_id)->value('code') : null,
                    'records' => (int) $row->records,
                    'amount' => (float) $row->amount,
                ])->all();
        };

        // Draft finance records replace the retired payment-request pipeline;
        // only drafts wait for a finance decision.
        $draftScope = fn ($q) => $q->when(
            ! empty($filters['country_id']) || ! empty($filters['university_id']),
            fn ($query) => $query->whereIn('applicant_id', $this->applicantScope($filters)->select('applicants.id')),
        );
        $pending = collect([
            'draft' => Income::query()->where('status', Income::DRAFT)->where($draftScope)->count()
                + Expense::query()->where('status', Expense::DRAFT)->where($draftScope)->count(),
        ]);

        // Payroll is split by its persisted payment state so an open salary is
        // never presented as posted. `salaries` stays the total for existing
        // consumers; the new keys say what is still payable and what is paid.
        $salaryBuckets = Salary::query()
            ->when(($employeeIds = $this->employeeIds($filters)) !== [], fn ($q) => $q->whereIn('employee_id', $employeeIds))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('payment_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('payment_date', '<=', $filters['date_to']))
            ->selectRaw("COALESCE(payment_status, 'open') as status, currency_id, COUNT(*) as records, COALESCE(SUM(total_amount), 0) as amount")
            ->groupBy('status', 'currency_id')->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'currency' => $row->currency_id ? DB::table('currencies')->where('id', $row->currency_id)->value('code') : null,
                'records' => (int) $row->records,
                'amount' => (float) $row->amount,
            ])->values();
        $salaryFor = fn (array $states) => $salaryBuckets->whereIn('status', $states)
            ->groupBy(fn ($row) => $row['currency'] ?? '')
            ->map(fn ($rows) => [
                'currency' => $rows->first()['currency'] ?? null,
                'records' => (int) $rows->sum('records'),
                'amount' => (float) $rows->sum('amount'),
            ])->values()->all();

        return [
            'income' => $scope(Income::class, 'amount'),
            'expenses' => $scope(Expense::class, 'amount'),
            'salaries' => $scope(Salary::class, 'total_amount', 'payment_date'),
            'salaries_by_status' => $salaryBuckets->all(),
            'salaries_payable' => $salaryFor(['open', 'partially_paid']),
            'salaries_paid' => $salaryFor(['paid']),
            'pending_by_status' => $pending->map(fn ($count) => (int) $count)->all(),
        ];
    }

    private function staff(array $filters): array
    {
        $employees = Employee::query()
            ->active()
            ->excludePrimaryRecord()
            ->when(($employeeIds = $this->employeeIds($filters)) !== [], fn ($q) => $q->whereIn('id', $employeeIds))
            ->select(['employees.id', 'employees.name'])
            ->selectSub($this->applicantScope($filters)->where('status', 'active')
                ->whereColumn('responsible_employee_id', 'employees.id')->selectRaw('COUNT(*)'), 'active_case_count')
            ->orderByDesc('active_case_count')
            ->orderBy('name')
            ->limit(50)
            ->get();

        return $employees->map(function (Employee $employee) use ($filters) {
            $sales = ApplicantContract::query()->whereIn('service', BuiltInService::ALL)->where('sold_by_employee_id', $employee->id);
            $bonuses = EducationalBonusSource::query()->where('employee_id', $employee->id);
            if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
                $sales->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
                    ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']));
                $bonuses->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
                    ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']));
            }

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'active_applicants' => (int) $employee->active_case_count,
                'sales_recorded' => (clone $sales)->count(),
                'contracts_sold' => Contract::query()->where(fn ($q) => $q->where('employee_id', $employee->id)->orWhere('sold_by_employee_id', $employee->id))->whereNotIn('status', ['cancelled'])->count(),
                'bonuses_generated' => (float) (clone $bonuses)->whereNotIn('status', ['void'])->sum('bonus_amount'),
            ];
        })->all();
    }

    private function attention(array $filters, int $staleDays): array
    {
        // Daily priorities must not disappear when a sales reporting period changes.
        unset($filters['date_from'], $filters['date_to']);
        $cutoff = now()->subDays($staleDays)->toDateTimeString();
        $counts = [];

        $stuck = $this->applicantScope($filters)
            ->with(['currentStage:id,name,key'])
            ->where('status', 'active')
            ->where('applicants.created_at', '<', $cutoff)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('applicant_stage_histories')->whereColumn('applicant_stage_histories.applicant_id', 'applicants.id')->where('applicant_stage_histories.changed_at', '>=', $cutoff))
            ->oldest('applicants.updated_at');
        $counts['stuck_applicants'] = (clone $stuck)->count();
        $stuck = $stuck
            ->limit(10)
            ->get()
            ->map(fn (Applicant $a) => ['id' => $a->id, 'title' => $a->name ?: "Applicant #{$a->id}", 'subtitle' => ($a->currentStage?->name ?? 'No stage')." · idle {$staleDays}+ days", 'route' => ['name' => 'applicant-case', 'params' => ['id' => $a->id]]])
            ->all();

        $overdue = Task::query()
            ->with(['applicant:id,name'])
            ->whereNotNull('applicant_id')
            ->where('progress', '<', 100)
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereHas('applicant', fn ($q) => $q->whereIn('id', $this->applicantScope($filters)->select('applicants.id')))
            ->oldest('due_date');
        $counts['overdue_tasks'] = (clone $overdue)->count();
        $overdue = $overdue
            ->limit(10)
            ->get()
            ->map(fn (Task $t) => ['id' => $t->id, 'title' => $t->title, 'subtitle' => ($t->applicant?->name ?: "Applicant #{$t->applicant_id}")." · due {$t->due_date}", 'route' => ['name' => 'applicant-case', 'params' => ['id' => $t->applicant_id]]])
            ->all();

        $pendingApprovals = Income::query()->where('status', Income::DRAFT)
            ->when(! empty($filters['country_id']) || ! empty($filters['university_id']), fn ($q) => $q->whereIn('applicant_id', $this->applicantScope($filters)->select('applicants.id')))
            ->count()
            + Expense::query()->where('status', Expense::DRAFT)
                ->when(! empty($filters['country_id']) || ! empty($filters['university_id']), fn ($q) => $q->whereIn('applicant_id', $this->applicantScope($filters)->select('applicants.id')))
                ->count();
        $missingDocuments = $this->applicantScope($filters)
            ->where('status', 'active')
            ->whereHas('currentStage', fn ($q) => $q->where('key', 'documents_required'))
            ->whereDoesntHave('attachments')
            ->whereDoesntHave('tasks.attachments')
            ->oldest('applicants.created_at');
        $counts['missing_documents'] = (clone $missingDocuments)->count();
        $missingDocuments = $missingDocuments->limit(10)->get()
            ->map(fn (Applicant $a) => ['id' => $a->id, 'title' => $a->name ?: "Applicant #{$a->id}", 'subtitle' => 'Documents have not been attached to a case task yet.', 'route' => ['name' => 'applicant-case', 'params' => ['id' => $a->id]]])->all();
        $contractsRequiringAttention = Contract::query()->with(['applicant:id,name', 'contractType'])
            ->where('is_cancelled', false)
            ->where(fn ($q) => $q->whereIn('status', ['draft', 'terminated'])
                ->orWhere(fn ($active) => $active->where('status', 'active')->whereDate('end_date', '<=', now()->addDays(30)->toDateString())))
            ->when(($employeeIds = $this->employeeIds($filters)) !== [], fn ($q) => $q->whereIn('employee_id', $employeeIds))
            ->when(! empty($filters['country_id']) || ! empty($filters['university_id']), fn ($q) => $q->whereIn('applicant_id', $this->applicantScope($filters)->select('applicants.id')))
            ->oldest('created_at');
        $counts['contracts_requiring_attention'] = (clone $contractsRequiringAttention)->count();
        $contractsRequiringAttention = $contractsRequiringAttention->limit(10)->get()
            ->map(function (Contract $c) {
                // An expiring active contract names its end date and opens the
                // register's expiring view, not a generic contract list.
                $expiring = $c->status === 'active' && $c->end_date;

                return [
                    'id' => $c->id,
                    'title' => $c->contract_number ?? "Contract #{$c->id}",
                    'subtitle' => ($c->applicant?->name ?? 'No applicant').' · '.($expiring ? 'ends '.$c->end_date->toDateString() : $c->status),
                    'route' => $expiring
                        ? ['name' => $c->type_slug === 'employment' ? 'employee-contracts' : 'employee-contracts', 'query' => ['expiring' => 1]]
                        : ($c->applicant_id ? ['name' => 'applicant-case', 'params' => ['id' => $c->applicant_id]] : ['name' => $c->type_slug === 'employment' ? 'employee-contracts' : 'other-contracts']),
                ];
            })->all();

        return ['counts' => $counts, 'stuck_applicants' => $stuck, 'overdue_tasks' => $overdue, 'pending_approvals' => $pendingApprovals, 'missing_documents' => $missingDocuments, 'contracts_requiring_attention' => $contractsRequiringAttention];
    }

    private function operations(array $filters): array
    {
        unset($filters['date_from'], $filters['date_to']);
        $cases = $this->applicantScope($filters);
        $lines = ApplicantContract::query()
            ->whereIn('applicant_id', (clone $cases)->select('applicants.id'))
            ->whereHas('contract', fn ($q) => $q->whereNotIn('status', ['cancelled', 'terminated'])->where('is_cancelled', false));
        // Use persisted payment state, including deliberate manager overrides.
        $balance = 'CASE WHEN fee - COALESCE(discount, 0) - COALESCE(amount_paid, 0) > 0 THEN fee - COALESCE(discount, 0) - COALESCE(amount_paid, 0) ELSE 0 END';
        // A future-dated extension is not collectible yet, so it is reported in
        // its own bucket instead of inflating the current receivable.
        $today = now()->toDateString();
        $outstandingFor = fn (bool $future) => (clone $lines)
            ->whereHas('contract', fn ($q) => $future
                ? $q->whereDate('start_date', '>', $today)
                : $q->where(fn ($inner) => $inner->whereNull('start_date')->orWhereDate('start_date', '<=', $today)))
            ->with('currency:id,code')
            ->selectRaw("currency_id, COUNT(*) as records, SUM({$balance}) as amount")
            ->whereRaw("({$balance}) > 0")->groupBy('currency_id')->get()
            ->map(fn ($row) => ['currency' => $row->currency?->code, 'records' => (int) $row->records, 'amount' => (float) $row->amount])->all();
        $outstanding = $outstandingFor(false);
        $outstandingFuture = $outstandingFor(true);
        $admissions = ApplicantUniversity::query()->whereIn('applicant_id', (clone $cases)->select('applicants.id'))
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        return [
            'active_cases' => (clone $cases)->where('status', 'active')->count(),
            'unassigned_cases' => (clone $cases)->where('status', 'active')->whereNull('responsible_employee_id')->count(),
            'outstanding' => $outstanding,
            'outstanding_future' => $outstandingFuture,
            'admissions' => collect(ApplicantUniversity::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($admissions[$status] ?? 0)])->all(),
            'service_stages' => (clone $lines)->with('currentStage:id,key,name,is_terminal')->selectRaw('service, current_stage_id, COUNT(*) as total')
                ->groupBy('service', 'current_stage_id')->get()->map(fn ($row) => [
                    'service' => $row->service, 'key' => $row->currentStage?->key,
                    'name' => $row->currentStage?->name, 'is_terminal' => (bool) $row->currentStage?->is_terminal, 'count' => (int) $row->total,
                ])->all(),
        ];
    }

    /**
     * Task and contract workload for the overview.
     *
     * Tasks are reached through their assignees so a scoped viewer only ever
     * counts work that belongs to the employees they may see.
     */
    private function work(array $filters): array
    {
        $employeeIds = $this->employeeIds($filters);
        $today = now()->toDateString();
        $period = function ($query, string $column) use ($filters) {
            if (! empty($filters['date_from'])) {
                $query->whereDate($column, '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $query->whereDate($column, '<=', $filters['date_to']);
            }

            return $query;
        };
        $taskScope = fn () => Task::query()->when($employeeIds !== [], fn ($query) => $query
            ->where(fn ($q) => $q->whereHas('users.employee', fn ($employee) => $employee->whereIn('id', $employeeIds))
                ->orWhereHas('applicant', fn ($applicant) => $applicant->whereIn('responsible_employee_id', $employeeIds))))
            ->when(! empty($filters['country_id']) || ! empty($filters['university_id']), function ($query) use ($filters) {
                $liveFilters = $filters;
                unset($liveFilters['date_from'], $liveFilters['date_to']);
                $query->whereIn('applicant_id', $this->applicantScope($liveFilters)->select('applicants.id'));
            });

        $open = (clone $taskScope())->where('progress', '<', 100);
        $tasks = [
            'open' => (clone $open)->count(),
            'due_today' => (clone $open)->whereDate('due_date', $today)->count(),
            'overdue' => (clone $open)->whereDate('due_date', '<', $today)->count(),
            'unassigned' => (clone $open)->whereDoesntHave('users')->count(),
            'completed' => $period($taskScope()->where('progress', '>=', 100), 'completed_at')->count(),
        ];

        $contractScope = fn () => Contract::query()->when($employeeIds !== [], fn ($query) => $query
            ->where(fn ($inner) => $inner->whereIn('employee_id', $employeeIds)->orWhereIn('sold_by_employee_id', $employeeIds)));
        $statusRows = (clone $contractScope())->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $contracts = [
            'total' => (int) $statusRows->sum(),
            'draft' => (int) ($statusRows['draft'] ?? 0),
            'active' => (int) ($statusRows['active'] ?? 0),
            'completed' => (int) ($statusRows['completed'] ?? 0),
            'terminated' => (int) ($statusRows['terminated'] ?? 0),
            'cancelled' => (int) ($statusRows['cancelled'] ?? 0),
            'new_in_period' => $period(clone $contractScope(), 'created_at')->count(),
            // Service contracts are the revenue line, so they are counted
            // separately from employment and other agreements.
            'service_lines' => ApplicantContract::query()
                ->when($employeeIds !== [], fn ($query) => $query->whereIn('sold_by_employee_id', $employeeIds))
                ->count(),
        ];

        return ['tasks' => $tasks, 'contracts' => $contracts];
    }

    private function countries(array $filters): array
    {
        $rows = $this->applicantScope($filters)
            ->joinAdmissionDetails()
            ->selectRaw('detail.country_id, COUNT(*) as total, SUM(CASE WHEN applicants.status = ? THEN 1 ELSE 0 END) as active', ['active'])
            ->whereNotNull('detail.country_id')
            ->groupBy('detail.country_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $names = Country::query()->whereIn('id', $rows->pluck('country_id'))->pluck('name', 'id');

        return $rows->map(fn ($row) => ['id' => $row->country_id, 'name' => $names[$row->country_id] ?? "Country #{$row->country_id}", 'active' => (int) $row->active, 'total' => (int) $row->total])->all();
    }

    private function universities(array $filters): array
    {
        $query = ApplicantUniversity::query()->with('university:id,name')
            ->when(! empty($filters['university_id']), fn ($q) => $q->where('university_id', $filters['university_id']))
            ->when(! empty($filters['country_id']), fn ($q) => $q->whereHas('applicant', fn ($aq) => $aq->whereScholarshipField('country_id', $filters['country_id'])))
            ->when(($employeeIds = $this->employeeIds($filters)) !== [], fn ($q) => $q->whereHas('applicant', fn ($aq) => $aq->whereIn('responsible_employee_id', $employeeIds)))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->selectRaw("university_id, COUNT(*) as applications, SUM(CASE WHEN status IN ('pending', 'applied') THEN 1 ELSE 0 END) as in_progress, SUM(CASE WHEN status = 'admitted' THEN 1 ELSE 0 END) as admitted")
            ->groupBy('university_id')->orderByDesc('applications')->limit(10)->get();

        return $query->map(fn ($row) => ['id' => $row->university_id, 'name' => $row->university?->name ?? "University #{$row->university_id}", 'applications' => (int) $row->applications, 'in_progress' => (int) $row->in_progress, 'admitted' => (int) $row->admitted])->all();
    }
}
