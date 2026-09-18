<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\EducationalBonusSource;
use App\Models\Employee;
use App\Models\Scholarship;
use App\Models\TestOffer;
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
    public const TYPES = ['applicants', 'employee-performance', 'sales', 'visa-outcomes'];

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
            'sales' => $this->salesReport($filters, $paginate, $request),
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
        $columns = ['employee', 'active_cases', 'closed_cases', 'sales_recorded', 'sales_value'];
        $employees = Employee::query()
            ->when(! empty($filters['employee_id']) || ! empty($filters['responsible_employee_id']), fn ($q) => $q->whereKey($filters['employee_id'] ?? $filters['responsible_employee_id']))
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        $rows = $employees->map(function (Employee $employee) use ($filters) {
            $cases = Applicant::query()->where('responsible_employee_id', $employee->id);
            $sales = EducationalBonusSource::query()->where('employee_id', $employee->id);

            return [
                'employee' => $employee->name,
                'active_cases' => (clone $cases)->where('status', 'active')->count(),
                'closed_cases' => (clone $cases)->where('status', 'closed')->count(),
                'sales_recorded' => (clone $sales)->count(),
                'sales_value' => (float) (clone $sales)->sum('source_amount'),
            ];
        })->all();

        return ['columns' => $columns, 'rows' => $rows, 'total' => count($rows)];
    }

    private function serviceQuery(string $model, array $filters)
    {
        $query = $model::query()->with(['customer:id,name', 'employee:id,name']);
        $employee = $filters['employee_id'] ?? $filters['responsible_employee_id'] ?? null;
        if ($employee) {
            $query->where('employee_id', $employee);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return $query->latest('date');
    }

    private function salesReport(array $filters, bool $paginate, Request $request): array
    {
        $columns = ['id', 'service', 'customer', 'applicant_id', 'employee', 'fee', 'currency', 'date', 'status'];
        $codes = DB::table('currencies')->pluck('code', 'id');
        $collect = function ($items, string $service) use ($codes) {
            return $items->map(fn ($s) => [
                'id' => $s->id,
                'service' => $service,
                'customer' => $s->customer?->name ?? "Customer #{$s->customer_id}",
                'applicant_id' => $s->applicant_id ?? '',
                'employee' => $s->employee?->name ?? '—',
                'fee' => (float) $s->fee,
                'currency' => $codes[$s->currency_id] ?? '',
                'date' => $s->date,
                'status' => $s->status,
            ]);
        };

        if (! $paginate) {
            $rows = $collect($this->serviceQuery(Scholarship::class, $filters)->limit(2500)->get(), 'scholarship')
                ->merge($collect($this->serviceQuery(TestOffer::class, $filters)->limit(2500)->get(), 'test offer'))
                ->sortByDesc('date')->values()->all();

            return ['columns' => $columns, 'rows' => $rows, 'total' => count($rows)];
        }

        $perPage = $request->integer('per_page', 100);
        $merged = $collect($this->serviceQuery(Scholarship::class, $filters)->limit(2500)->get(), 'scholarship')
            ->merge($collect($this->serviceQuery(TestOffer::class, $filters)->limit(2500)->get(), 'test offer'))
            ->sortByDesc('date')->values();
        $page = max(1, $request->integer('page', 1));

        return ['columns' => $columns, 'rows' => $merged->forPage($page, $perPage)->values()->all(), 'total' => $merged->count(), 'current_page' => $page, 'last_page' => (int) ceil($merged->count() / $perPage)];
    }

    private function outcomesReport(array $filters): array
    {
        $columns = ['country', 'in_progress', 'visa_approved', 'rejected', 'closed_other', 'completed', 'total', 'success_rate_pct'];
        $query = $this->applicantScope($filters)->selectRaw('country_id, current_stage_id, COUNT(*) as total')->groupBy('country_id', 'current_stage_id')->whereNotNull('country_id');
        $stageKeys = \App\Models\ApplicantStage::query()->pluck('key', 'id');
        $countryNames = \App\Models\Country::query()->pluck('name', 'id');
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
        $query = \App\Models\ApplicantUniversity::query()
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
