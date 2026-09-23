<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantUniversity;
use App\Models\Contract;
use App\Models\Country;
use App\Models\ContractService;
use App\Models\EducationalBonusSource;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PendingTransaction;
use App\Models\Salary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Visa-agency operational reports (Implementation Plan §16 — Reporting).
 *
 * Tabular detail behind the dashboard aggregates: applicant cases,
 * employee performance, service sales, and visa outcomes — each
 * filterable, each exportable to CSV. Read-only; figures are derived
 * live from operational rows. Export mirrors the house CSV style
 * (UTF-8 BOM, column headers, generated-at footer).
 */
class EducationalReportController extends Controller
{
    public const TYPES = ['applicants', 'employee-performance', 'contracts', 'services', 'sales', 'financial', 'universities', 'visa-outcomes'];

    public function show(Request $request, string $type)
    {
        $this->assertType($type);
        $filters = $this->filters($request);
        $rows = $this->rows($type, $filters, paginate: true, request: $request);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $this->assertType($type);
        abort_unless($request->user()->hasPermission('export_reports'), 403, 'auth.forbidden');
        $filters = $this->filters($request);
        $report = $this->rows($type, $filters, paginate: false, request: $request);
        $filename = sprintf('visa-%s-report-%s.csv', $type, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $report['columns']);
            foreach ($report['rows'] as $row) {
                fputcsv($stream, array_map(fn ($column) => $this->csvValue($row[$column] ?? null), $report['columns']));
            }
            fputcsv($stream, []);
            fputcsv($stream, ['generated_at_utc', now('UTC')->toISOString()]);
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'contract_type_id' => ['nullable', 'integer', 'exists:contract_types,id'],
            'package_type_id' => ['nullable', 'integer', 'exists:package_types,id'],
            'stage' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:active,closed'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);
    }

    private function rows(string $type, array $filters, bool $paginate, Request $request): array
    {
        return match ($type) {
            'applicants' => $this->applicantReport($filters, $paginate, $request),
            'employee-performance' => $this->performanceReport($filters),
            'services' => $this->salesReport($filters, $paginate, $request),
            'sales' => $this->salesReport($filters, $paginate, $request), // Legacy report URL.
            'contracts' => $this->contractReport($filters, $paginate, $request),
            'financial' => $this->financialReport($filters, $paginate, $request),
            'universities' => $this->universityReport($filters),
            'visa-outcomes' => $this->outcomesReport($filters),
        };
    }

    private function applicantScope(array $filters)
    {
        $query = Applicant::query()->with(['customer:id,name', 'responsibleEmployee:id,name', 'country:id,name', 'university:id,name', 'educationLevel:id,name', 'language:id,name', 'currentStage:id,name,key']);
        foreach (['responsible_employee_id', 'country_id', 'university_id', 'education_level_id', 'language_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['employee_id'])) {
            $query->where('responsible_employee_id', $filters['employee_id']);
        }
        if (! empty($filters['stage'])) {
            $query->whereHas('currentStage', fn ($q) => $q->where('key', $filters['stage']));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('application_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('application_date', '<=', $filters['date_to']);
        }

        return $query->latest('applicants.created_at');
    }

    private function applicantReport(array $filters, bool $paginate, Request $request): array
    {
        $columns = ['id', 'customer', 'employee', 'country', 'university', 'education_level', 'language', 'stage', 'status', 'application_date'];
        $map = fn (Applicant $a) => [
            'id' => $a->id,
            'customer' => $a->customer?->name ?? "Customer #{$a->customer_id}",
            'employee' => $a->responsibleEmployee?->name ?? 'Unassigned',
            'country' => $a->country?->name ?? '—',
            'university' => $a->university?->name ?? '—',
            'education_level' => $a->educationLevel?->name ?? '—',
            'language' => $a->language?->name ?? '—',
            'stage' => $a->currentStage?->name ?? '—',
            'status' => $a->status,
            'application_date' => $a->application_date?->format('Y-m-d') ?? '',
        ];

        if (! $paginate) {
            return ['columns' => $columns, 'rows' => $this->applicantScope($filters)->limit(5000)->get()->map($map)->all(), 'total' => $this->applicantScope($filters)->count()];
        }

        $page = $this->applicantScope($filters)->paginate($request->integer('per_page', 100));

        return ['columns' => $columns, 'rows' => collect($page->items())->map($map)->all(), 'total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()];
    }

    private function performanceReport(array $filters): array
    {
        $columns = ['employee', 'active_cases', 'closed_cases', 'services_sold', 'contracts_sold', 'revenue_attributed', 'bonus_amount'];
        $employees = Employee::query()
            ->when(! empty($filters['employee_id']) || ! empty($filters['responsible_employee_id']), fn ($q) => $q->whereKey($filters['employee_id'] ?? $filters['responsible_employee_id']))
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        $rows = $employees->map(function (Employee $employee) {
            $cases = Applicant::query()->where('responsible_employee_id', $employee->id);
            $sales = EducationalBonusSource::query()->where('employee_id', $employee->id);
            $contracts = Contract::query()->where('employee_id', $employee->id)->whereNotIn('status', ['cancelled']);

            return [
                'employee' => $employee->name,
                'active_cases' => (clone $cases)->where('status', 'active')->count(),
                'closed_cases' => (clone $cases)->where('status', 'closed')->count(),
                'services_sold' => (clone $sales)->where('source_type', ContractService::class)->count(),
                'contracts_sold' => $contracts->count(),
                'revenue_attributed' => (float) (clone $sales)->sum('source_amount'),
                'bonus_amount' => (float) (clone $sales)->whereNotIn('status', ['void'])->sum('bonus_amount'),
                // Stable legacy fields for existing API consumers.
                'sales_recorded' => (clone $sales)->count(),
                'sales_value' => (float) (clone $sales)->sum('source_amount'),
            ];
        })->all();

        return ['columns' => $columns, 'rows' => $rows, 'total' => count($rows)];
    }

    private function serviceQuery(string $category, array $filters)
    {
        $query = ContractService::query()->whereHas('service', fn ($service) => $service->where('category', $category))
            ->with(['service:id,type,category,fee,currency_id', 'contract.customer:id,name', 'soldByEmployee:id,name', 'country:id,name', 'educationLevel:id,name', 'customerPackage.package:id,name', 'applicant.country:id,name', 'applicant.educationLevel:id,name', 'contract:id,customer_id,status,start_date']);
        $employee = $filters['employee_id'] ?? $filters['responsible_employee_id'] ?? null;
        if ($employee) {
            $query->where('sold_by_employee_id', $employee);
        }
        foreach (['country_id', 'education_level_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where(function ($q) use ($field, $filters) {
                    $q->where($field, $filters[$field])
                        ->orWhereHas('applicant', fn ($aq) => $aq->where($field, $filters[$field]));
                });
            }
        }
        if (! empty($filters['package_type_id'])) {
            $query->whereHas('customerPackage', fn ($q) => $q->where('package_id', $filters['package_type_id']));
        }
        if (! empty($filters['date_from'])) {
            $query->whereHas('contract', fn ($contracts) => $contracts->whereDate('start_date', '>=', $filters['date_from']));
        }
        if (! empty($filters['date_to'])) {
            $query->whereHas('contract', fn ($contracts) => $contracts->whereDate('start_date', '<=', $filters['date_to']));
        }

        return $query->latest('contract_service.id');
    }

    private function salesReport(array $filters, bool $paginate, Request $request): array
    {
        $columns = ['id', 'service', 'customer', 'applicant_id', 'employee', 'country', 'education_level', 'package_type', 'fee', 'currency', 'date', 'status'];
        $codes = DB::table('currencies')->pluck('code', 'id');
        $collect = function ($items, string $service) use ($codes) {
            return $items->map(fn ($s) => [
                'id' => $s->id,
                'service' => $service,
                'customer' => $s->contract?->customer?->name ?? "Customer #{$s->contract?->customer_id}",
                'applicant_id' => $s->applicant_id ?? '',
                'country' => $s->country?->name ?? $s->applicant?->country?->name ?? '—',
                'education_level' => $s->educationLevel?->name ?? $s->applicant?->educationLevel?->name ?? '—',
                'package_type' => $s->customerPackage?->package?->name ?? '—',
                'employee' => $s->soldByEmployee?->name ?? '—',
                'fee' => (float) ($s->fee ?? $s->service->fee),
                'currency' => $codes[$s->currency_id ?? $s->service->currency_id] ?? '',
                'date' => $s->contract?->start_date?->toDateString(),
                'status' => $s->contract?->status,
            ]);
        };

        if (! $paginate) {
            $rows = $collect($this->serviceQuery('scholarship', $filters)->limit(2500)->get(), 'scholarship')
                ->merge($collect($this->serviceQuery('test_offer', $filters)->limit(2500)->get(), 'test offer'))
                ->sortByDesc('date')->values()->all();

            return ['columns' => $columns, 'rows' => $rows, 'total' => count($rows)];
        }

        $perPage = $request->integer('per_page', 100);
        $merged = $collect($this->serviceQuery('scholarship', $filters)->limit(2500)->get(), 'scholarship')
            ->merge($collect($this->serviceQuery('test_offer', $filters)->limit(2500)->get(), 'test offer'))
            ->sortByDesc('date')->values();
        $page = max(1, $request->integer('page', 1));

        return ['columns' => $columns, 'rows' => $merged->forPage($page, $perPage)->values()->all(), 'total' => $merged->count(), 'current_page' => $page, 'last_page' => (int) ceil($merged->count() / $perPage)];
    }

    private function contractReport(array $filters, bool $paginate, Request $request): array
    {
        $columns = ['id', 'contract_number', 'contract_type', 'template', 'version', 'language', 'employee', 'customer', 'applicant_id', 'status', 'start_date'];
        $query = Contract::query()->with(['contractType:id,name', 'template:id,name', 'templateVersion.language:id,name', 'employee:id,name', 'customer:id,name'])
            ->when(! empty($filters['contract_type_id']), fn ($q) => $q->where('contract_type_id', $filters['contract_type_id']))
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('employee_id', $filters['employee_id']))
            ->when(! empty($filters['language_id']), fn ($q) => $q->whereHas('templateVersion', fn ($vq) => $vq->where('language_id', $filters['language_id'])))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->latest();
        $map = fn (Contract $c) => ['id' => $c->id, 'contract_number' => $c->contract_number ?? "Contract #{$c->id}", 'contract_type' => $c->contractType?->name ?? '—', 'template' => $c->template?->name ?? '—', 'version' => $c->templateVersion?->version_number ?? 'Legacy', 'language' => $c->templateVersion?->language?->name ?? '—', 'employee' => $c->employee?->name ?? '—', 'customer' => $c->customer?->name ?? '—', 'applicant_id' => $c->applicant_id ?? '', 'status' => $c->status, 'start_date' => $c->start_date?->format('Y-m-d') ?? ''];
        if (! $paginate) {
            return ['columns' => $columns, 'rows' => $query->limit(5000)->get()->map($map)->all(), 'total' => $query->count()];
        }
        $page = $query->paginate($request->integer('per_page', 100));

        return ['columns' => $columns, 'rows' => collect($page->items())->map($map)->all(), 'total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()];
    }

    private function universityReport(array $filters): array
    {
        $columns = ['university', 'country', 'applications', 'in_progress', 'admitted', 'rejected', 'withdrawn', 'admission_rate_pct'];
        $query = ApplicantUniversity::query()->with(['university.country:id,name'])
            ->when(! empty($filters['university_id']), fn ($q) => $q->where('university_id', $filters['university_id']))
            ->when(! empty($filters['country_id']), fn ($q) => $q->whereHas('applicant', fn ($aq) => $aq->where('country_id', $filters['country_id'])))
            ->when(! empty($filters['employee_id']), fn ($q) => $q->whereHas('applicant', fn ($aq) => $aq->where('responsible_employee_id', $filters['employee_id'])))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->selectRaw("university_id, COUNT(*) applications, SUM(CASE WHEN status IN ('pending','applied') THEN 1 ELSE 0 END) in_progress, SUM(CASE WHEN status = 'admitted' THEN 1 ELSE 0 END) admitted, SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) rejected, SUM(CASE WHEN status = 'withdrawn' THEN 1 ELSE 0 END) withdrawn")
            ->groupBy('university_id')->orderByDesc('applications')->get();
        $rows = $query->map(function ($row) {
            $total = (int) $row->applications;
            $admitted = (int) $row->admitted;

            return ['university' => $row->university?->name ?? "University #{$row->university_id}", 'country' => $row->university?->country?->name ?? '—', 'applications' => $total, 'in_progress' => (int) $row->in_progress, 'admitted' => $admitted, 'rejected' => (int) $row->rejected, 'withdrawn' => (int) $row->withdrawn, 'admission_rate_pct' => $total ? round(($admitted / $total) * 100, 2) : 0.0];
        })->all();

        return ['columns' => $columns, 'rows' => $rows, 'total' => count($rows)];
    }

    private function financialReport(array $filters, bool $paginate, Request $request): array
    {
        $columns = ['record_type', 'id', 'status', 'category', 'customer', 'applicant_id', 'employee', 'amount', 'currency', 'date', 'reason'];
        $codes = DB::table('currencies')->pluck('code', 'id');
        $rows = collect();

        foreach ([[Income::class, 'income', 'amount', 'date', 'description'], [Expense::class, 'expense', 'amount', 'date', 'description'], [Salary::class, 'salary', 'total_amount', 'payment_date', 'remarks']] as [$model, $type, $amount, $date, $reason]) {
            $query = $model::query()->with(['employee:id,name'])
                ->when(! empty($filters['employee_id']), fn ($q) => $q->where('employee_id', $filters['employee_id']))
                ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate($date, '>=', $filters['date_from']))
                ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate($date, '<=', $filters['date_to']));
            if (in_array($model, [Income::class, Expense::class], true)) {
                $query->with('customer:id,name');
                if (! empty($filters['country_id']) || ! empty($filters['university_id'])) {
                    $query->whereHas('applicant', function ($aq) use ($filters) {
                        if (! empty($filters['country_id'])) {
                            $aq->where('country_id', $filters['country_id']);
                        }
                        if (! empty($filters['university_id'])) {
                            $aq->where('university_id', $filters['university_id']);
                        }
                    });
                }
            }
            $rows = $rows->merge($query->limit(2500)->get()->map(fn ($r) => [
                'record_type' => $type, 'id' => $r->id, 'status' => 'posted', 'category' => $r->type ?? $type,
                'customer' => $r->customer?->name ?? '—', 'applicant_id' => $r->applicant_id ?? '',
                'employee' => $r->employee?->name ?? '—', 'amount' => (float) $r->{$amount},
                'currency' => $codes[$r->currency_id] ?? '', 'date' => $r->{$date}?->format('Y-m-d') ?? '',
                'reason' => $r->{$reason} ?? '',
            ]));
        }

        $pending = PendingTransaction::query()->with(['customer:id,name', 'employee:id,name'])
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('employee_id', $filters['employee_id']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('requested_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('requested_at', '<=', $filters['date_to']))
            ->limit(2500)->get()->map(fn (PendingTransaction $r) => [
                'record_type' => 'pending_transaction', 'id' => $r->id, 'status' => $r->approval_status,
                'category' => $r->transaction_type, 'customer' => $r->customer?->name ?? '—',
                'applicant_id' => $r->applicant_id ?? '', 'employee' => $r->employee?->name ?? '—',
                'amount' => (float) $r->amount, 'currency' => $codes[$r->currency_id] ?? '',
                'date' => $r->requested_at?->format('Y-m-d') ?? '', 'reason' => $r->reason,
            ]);
        $rows = $rows->merge($pending)->sortByDesc('date')->values();
        if (! $paginate) {
            return ['columns' => $columns, 'rows' => $rows->all(), 'total' => $rows->count()];
        }
        $perPage = $request->integer('per_page', 100);
        $page = max(1, $request->integer('page', 1));

        return ['columns' => $columns, 'rows' => $rows->forPage($page, $perPage)->values()->all(), 'total' => $rows->count(), 'current_page' => $page, 'last_page' => (int) ceil($rows->count() / $perPage)];
    }

    private function outcomesReport(array $filters): array
    {
        $columns = ['country', 'in_progress', 'visa_approved', 'rejected', 'closed_other', 'completed', 'total', 'success_rate_pct'];
        $query = $this->applicantScope($filters)->selectRaw('country_id, current_stage_id, COUNT(*) as total')->groupBy('country_id', 'current_stage_id')->whereNotNull('country_id');
        $stageKeys = ApplicantStage::query()->pluck('key', 'id');
        $countryNames = Country::query()->pluck('name', 'id');
        $buckets = [];

        foreach ($query->get() as $row) {
            $key = $stageKeys[$row->current_stage_id] ?? 'unknown';
            // Completed is the terminal success; visa_approved counts with it.
            $bucket = match ($key) {
                'completed' => 'completed',
                'visa_approved' => 'visa_approved',
                'rejected' => 'rejected',
                'cancelled', 'withdrawn' => 'closed_other',
                default => 'in_progress',
            };
            $buckets[$row->country_id][$bucket] = ($buckets[$row->country_id][$bucket] ?? 0) + $row->total;
            $buckets[$row->country_id]['total'] = ($buckets[$row->country_id]['total'] ?? 0) + $row->total;
        }

        $rows = [];
        foreach ($buckets as $countryId => $b) {
            $success = ($b['visa_approved'] ?? 0) + ($b['completed'] ?? 0);
            $rows[] = [
                'country' => $countryNames[$countryId] ?? "Country #{$countryId}",
                'in_progress' => $b['in_progress'] ?? 0,
                'visa_approved' => $b['visa_approved'] ?? 0,
                'rejected' => $b['rejected'] ?? 0,
                'closed_other' => $b['closed_other'] ?? 0,
                'completed' => $b['completed'] ?? 0,
                'total' => $b['total'],
                'success_rate_pct' => $b['total'] > 0 ? round($success / $b['total'] * 100, 1) : 0,
            ];
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return ['columns' => $columns, 'rows' => $rows, 'total' => count($rows), 'by_university' => $this->universityOutcomes($filters)];
    }

    private function universityOutcomes(array $filters): array
    {
        $query = ApplicantUniversity::query()
            ->join('applicants', 'applicants.id', '=', 'applicant_universities.applicant_id')
            ->join('universities', 'universities.id', '=', 'applicant_universities.university_id')
            ->selectRaw('universities.name as university, applicant_universities.status as status, COUNT(*) as total')
            ->groupBy('universities.name', 'applicant_universities.status');
        if (! empty($filters['university_id'])) {
            $query->where('applicant_universities.university_id', $filters['university_id']);
        }
        if (! empty($filters['country_id'])) {
            $query->where('applicants.country_id', $filters['country_id']);
        }

        $buckets = [];
        foreach ($query->get() as $row) {
            $buckets[$row->university]['university'] = $row->university;
            $buckets[$row->university][$row->status] = ($buckets[$row->university][$row->status] ?? 0) + $row->total;
            $buckets[$row->university]['applications'] = ($buckets[$row->university]['applications'] ?? 0) + $row->total;
        }

        $rows = [];
        foreach ($buckets as $b) {
            $admitted = $b['admitted'] ?? 0;
            $rows[] = [
                'university' => $b['university'],
                'applications' => $b['applications'],
                'admitted' => $admitted,
                'admission_rate_pct' => $b['applications'] > 0 ? round($admitted / $b['applications'] * 100, 1) : 0,
            ];
        }
        usort($rows, fn ($a, $b) => $b['applications'] <=> $a['applications']);

        return ['columns' => ['university', 'applications', 'admitted', 'admission_rate_pct'], 'rows' => $rows, 'total' => count($rows)];
    }

    private function csvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
