<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantUniversity;
use App\Models\Contract;
use App\Models\Country;
use App\Models\CustomerService;
use App\Models\EducationalBonusSource;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PendingTransaction;
use App\Models\Salary;
use App\Models\Task;
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
            'date_to' => ['nullable', 'date'],
            'stale_days' => ['sometimes', 'integer', 'min:1', 'max:120'],
        ]);

        $user = $request->user();
        if (! $user->isSuperAdmin() && array_intersect($user->roleNames(), ['admin', 'manager']) === []) {
            // Staff dashboards are an own-work view. Never trust a staff-supplied
            // employee filter to widen case, finance, salary, or performance data.
            $data['responsible_employee_id'] = (int) (Employee::query()->where('user_id', $user->id)->value('id') ?? 0);
            $data['_actor_user_id'] = $user->id;
        }

        $staleDays = (int) ($data['stale_days'] ?? 14);

        return response()->json(['success' => true, 'data' => [
            'pipeline' => $this->pipeline($data),
            'financial' => $this->financial($data),
            'sales' => $this->sales($data),
            'staff' => $this->staff($data),
            'attention' => $this->attention($data, $staleDays),
            'countries' => $this->countries($data),
            'universities' => $this->universities($data),
        ]]);
    }

    private function applicantScope(array $filters)
    {
        $query = Applicant::query();
        foreach (['responsible_employee_id', 'country_id', 'university_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('application_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('application_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    private function pipeline(array $filters): array
    {
        $stages = ApplicantStage::query()->where('is_active', true)->orderBy('sort_order')->get();
        $columns = $stages->map(function (ApplicantStage $stage) use ($filters) {
            $count = $this->applicantScope($filters)->where('current_stage_id', $stage->id)->count();

            return ['key' => $stage->key, 'name' => $stage->name, 'is_terminal' => (bool) $stage->is_terminal, 'count' => $count];
        });

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
        if (! empty($filters['responsible_employee_id'])) {
            $contractQuery->where('employee_id', $filters['responsible_employee_id']);
        }
        if (! empty($filters['date_from'])) {
            $contractQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $contractQuery->whereDate('created_at', '<=', $filters['date_to']);
        }
        $out['contracts']['sold'] = $contractQuery->count();
        foreach (['scholarships' => 'scholarship', 'test_offers' => 'test_offer'] as $key => $category) {
            $query = CustomerService::query()->whereHas('service', fn ($service) => $service->where('category', $category));
            if (! empty($filters['responsible_employee_id'])) {
                $query->where('sold_by_employee_id', $filters['responsible_employee_id']);
            }
            if (! empty($filters['country_id'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('country_id', $filters['country_id'])
                        ->orWhereHas('applicant', fn ($aq) => $aq->where('country_id', $filters['country_id']));
                });
            }
            if (! empty($filters['university_id'])) {
                $query->whereHas('applicant', fn ($aq) => $aq->where('university_id', $filters['university_id']));
            }
            if (! empty($filters['date_from'])) {
                $query->whereDate('sold_at', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $query->whereDate('sold_at', '<=', $filters['date_to']);
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
                if (! empty($filters['responsible_employee_id'])) {
                    $query->where('employee_id', $filters['responsible_employee_id']);
                }
                if (! empty($filters['country_id']) || ! empty($filters['university_id'])) {
                    $query->whereHas('applicant', function ($q) use ($filters) {
                        $q->when(! empty($filters['country_id']), fn ($aq) => $aq->where('country_id', $filters['country_id']))
                            ->when(! empty($filters['university_id']), fn ($aq) => $aq->where('university_id', $filters['university_id']));
                    });
                }
            } elseif ($model === Salary::class && ! empty($filters['responsible_employee_id'])) {
                $query->where('employee_id', $filters['responsible_employee_id']);
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

        $pending = PendingTransaction::query()
            ->when(! empty($filters['_actor_user_id']), fn ($q) => $q->where('requested_by', $filters['_actor_user_id']))
            ->when(empty($filters['_actor_user_id']) && ! empty($filters['responsible_employee_id']), fn ($q) => $q->where('employee_id', $filters['responsible_employee_id']))
            ->when(! empty($filters['country_id']) || ! empty($filters['university_id']), fn ($q) => $q->whereHas('applicant', function ($aq) use ($filters) {
                $aq->when(! empty($filters['country_id']), fn ($q) => $q->where('country_id', $filters['country_id']))
                    ->when(! empty($filters['university_id']), fn ($q) => $q->where('university_id', $filters['university_id']));
            }))
            ->selectRaw('approval_status, COUNT(*) as count')->groupBy('approval_status')->pluck('count', 'approval_status');

        return [
            'income' => $scope(Income::class, 'amount'),
            'expenses' => $scope(Expense::class, 'amount'),
            'salaries' => $scope(Salary::class, 'total_amount', 'payment_date'),
            'pending_by_status' => $pending->map(fn ($count) => (int) $count)->all(),
        ];
    }

    private function staff(array $filters): array
    {
        $employees = Employee::query()
            ->when(! empty($filters['responsible_employee_id']), fn ($q) => $q->whereKey($filters['responsible_employee_id']))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name']);

        return $employees->map(function (Employee $employee) use ($filters) {
            $cases = $this->applicantScope($filters)->where('responsible_employee_id', $employee->id);
            $sales = EducationalBonusSource::query()->where('employee_id', $employee->id);
            if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
                $sales->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
                    ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']));
            }

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'active_applicants' => (clone $cases)->where('status', 'active')->count(),
                'sales_recorded' => (clone $sales)->count(),
                'sales_source_total' => (float) (clone $sales)->sum('source_amount'),
                'contracts_sold' => Contract::query()->where('employee_id', $employee->id)->whereNotIn('status', ['cancelled'])->count(),
                'bonuses_generated' => (float) (clone $sales)->whereNotIn('status', ['void'])->sum('bonus_amount'),
            ];
        })->all();
    }

    private function attention(array $filters, int $staleDays): array
    {
        $cutoff = now()->subDays($staleDays)->toDateTimeString();

        $stuck = $this->applicantScope($filters)
            ->with(['customer:id,name', 'currentStage:id,name,key'])
            ->where('status', 'active')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('applicant_stage_histories')->whereColumn('applicant_stage_histories.applicant_id', 'applicants.id')->where('applicant_stage_histories.changed_at', '>=', $cutoff))
            ->latest('applicants.updated_at')
            ->limit(10)
            ->get()
            ->map(fn (Applicant $a) => ['id' => $a->id, 'title' => $a->customer?->name ?? "Applicant #{$a->id}", 'subtitle' => ($a->currentStage?->name ?? 'No stage')." · idle {$staleDays}+ days", 'route' => ['name' => 'applicant-case', 'params' => ['id' => $a->id]]])
            ->all();

        $overdue = Task::query()
            ->with(['applicant:id,customer_id', 'applicant.customer:id,name'])
            ->whereNotNull('applicant_id')
            ->where('progress', '<', 100)
            ->whereDate('due_date', '<', now()->toDateString())
            ->when(! empty($filters['responsible_employee_id']), fn ($q) => $q->whereHas('applicant', fn ($applicant) => $applicant->where('responsible_employee_id', $filters['responsible_employee_id'])))
            ->latest('due_date')
            ->limit(10)
            ->get()
            ->map(fn (Task $t) => ['id' => $t->id, 'title' => $t->title, 'subtitle' => ($t->applicant?->customer?->name ?? "Applicant #{$t->applicant_id}")." · due {$t->due_date}", 'route' => ['name' => 'applicant-case', 'params' => ['id' => $t->applicant_id]]])
            ->all();

        $pendingApprovals = PendingTransaction::query()
            ->whereIn('approval_status', [PendingTransaction::SUBMITTED, PendingTransaction::UNDER_REVIEW])
            ->when(! empty($filters['_actor_user_id']), fn ($q) => $q->where('requested_by', $filters['_actor_user_id']))
            ->when(empty($filters['_actor_user_id']) && ! empty($filters['responsible_employee_id']), fn ($q) => $q->where('employee_id', $filters['responsible_employee_id']))
            ->count();
        $missingDocuments = $this->applicantScope($filters)
            ->with('customer:id,name')
            ->where('status', 'active')
            ->whereHas('currentStage', fn ($q) => $q->where('key', 'documents_required'))
            ->whereDoesntHave('tasks.attachments')
            ->limit(10)->get()
            ->map(fn (Applicant $a) => ['id' => $a->id, 'title' => $a->customer?->name ?? "Applicant #{$a->id}", 'subtitle' => 'Documents have not been attached to a case task yet.', 'route' => ['name' => 'applicant-case', 'params' => ['id' => $a->id]]])->all();
        $contractsRequiringAttention = Contract::query()->with(['customer:id,name', 'applicant:id,customer_id'])
            ->where(function ($q) {
                $q->where('status', 'draft')->orWhere('status', 'expired')->orWhere(function ($aq) {
                    $aq->where('status', 'active')->whereDate('end_date', '<=', now()->addDays(14)->toDateString());
                });
            })
            ->when(! empty($filters['responsible_employee_id']), fn ($q) => $q->where('employee_id', $filters['responsible_employee_id']))
            ->limit(10)->get()
            ->map(fn (Contract $c) => ['id' => $c->id, 'title' => $c->contract_number ?? "Contract #{$c->id}", 'subtitle' => ($c->customer?->name ?? 'No customer').' · '.$c->status, 'route' => $c->applicant_id ? ['name' => 'applicant-case', 'params' => ['id' => $c->applicant_id]] : ['name' => 'contracts']])->all();

        return ['stuck_applicants' => $stuck, 'overdue_tasks' => $overdue, 'pending_approvals' => $pendingApprovals, 'missing_documents' => $missingDocuments, 'contracts_requiring_attention' => $contractsRequiringAttention];
    }

    private function countries(array $filters): array
    {
        $rows = $this->applicantScope($filters)
            ->selectRaw('country_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active', ['active'])
            ->whereNotNull('country_id')
            ->groupBy('country_id')
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
            ->when(! empty($filters['country_id']), fn ($q) => $q->whereHas('applicant', fn ($aq) => $aq->where('country_id', $filters['country_id'])))
            ->when(! empty($filters['responsible_employee_id']), fn ($q) => $q->whereHas('applicant', fn ($aq) => $aq->where('responsible_employee_id', $filters['responsible_employee_id'])))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->selectRaw("university_id, COUNT(*) as applications, SUM(CASE WHEN status IN ('pending', 'applied') THEN 1 ELSE 0 END) as in_progress, SUM(CASE WHEN status = 'admitted' THEN 1 ELSE 0 END) as admitted")
            ->groupBy('university_id')->orderByDesc('applications')->limit(10)->get();

        return $query->map(fn ($row) => ['id' => $row->university_id, 'name' => $row->university?->name ?? "University #{$row->university_id}", 'applications' => (int) $row->applications, 'in_progress' => (int) $row->in_progress, 'admitted' => (int) $row->admitted])->all();
    }
}
