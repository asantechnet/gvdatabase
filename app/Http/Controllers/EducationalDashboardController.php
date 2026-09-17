<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Country;
use App\Models\EducationalBonusSource;
use App\Models\Employee;
use App\Models\PendingTransaction;
use App\Models\Scholarship;
use App\Models\Task;
use App\Models\TestOffer;
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

        $staleDays = (int) ($data['stale_days'] ?? 14);

        return response()->json(['success' => true, 'data' => [
            'pipeline' => $this->pipeline($data),
            'sales' => $this->sales($data),
            'staff' => $this->staff($data),
            'attention' => $this->attention($data, $staleDays),
            'countries' => $this->countries($data),
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
        $out = [];
        foreach (['scholarships' => Scholarship::class, 'test_offers' => TestOffer::class] as $key => $model) {
            $query = $model::query();
            if (! empty($filters['responsible_employee_id'])) {
                $query->where('employee_id', $filters['responsible_employee_id']);
            }
            if (! empty($filters['date_from'])) {
                $query->whereDate('date', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $query->whereDate('date', '<=', $filters['date_to']);
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
            ->when(! empty($filters['responsible_employee_id']), function ($q) use ($filters) {
                $userId = Employee::whereKey($filters['responsible_employee_id'])->value('user_id');
                if ($userId) {
                    $q->whereHas('users', fn ($uq) => $uq->whereKey($userId));
                }
            })
            ->latest('due_date')
            ->limit(10)
            ->get()
            ->map(fn (Task $t) => ['id' => $t->id, 'title' => $t->title, 'subtitle' => ($t->applicant?->customer?->name ?? "Applicant #{$t->applicant_id}")." · due {$t->due_date}", 'route' => ['name' => 'applicant-case', 'params' => ['id' => $t->applicant_id]]])
            ->all();

        $pendingApprovals = PendingTransaction::query()->whereIn('approval_status', [PendingTransaction::SUBMITTED, PendingTransaction::UNDER_REVIEW])->count();

        return ['stuck_applicants' => $stuck, 'overdue_tasks' => $overdue, 'pending_approvals' => $pendingApprovals];
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
}
