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
use App\Models\PendingTransaction;
use App\Models\Salary;
use App\Support\AcceptedLocale;
use App\Support\BuiltInService;
use App\Support\ReportDateFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        AcceptedLocale::fromRequest($request);
        $filters = $this->filters($request);
        $report = $this->rows($type, $filters, paginate: false, request: $request);
        $filename = sprintf('visa-%s-report-%s.csv', $type, now()->format('Y-m-d'));
        $generatedAt = now('UTC')->toISOString();

        return response()->streamDownload(function () use ($report, $generatedAt): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $report['columns']);
            foreach ($report['rows'] as $row) {
                fputcsv($stream, array_map(fn ($column) => $this->csvValue($row[$column] ?? null), $report['columns']));
            }
            fputcsv($stream, []);
            // Keep the machine value and pair it with a localized, readable one.
            fputcsv($stream, ['generated_at_utc', $generatedAt]);
            fputcsv($stream, ['generated_at', ReportDateFormatter::human($generatedAt)]);
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
            'package_id' => ['prohibited'],
            'package_type_id' => ['prohibited'],
            'service' => ['nullable', Rule::in(BuiltInService::ALL)],
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
        $query = Applicant::query()->with(['responsibleEmployee:id,name', 'privateDetail.country:id,name', 'privateDetail.university:id,name', 'privateDetail.educationLevel:id,name', 'scholarshipDetail.country:id,name', 'scholarshipDetail.university:id,name', 'scholarshipDetail.educationLevel:id,name', 'currentStage:id,name,key']);
        foreach (['country_id', 'university_id', 'education_level_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->whereScholarshipField($field, $filters[$field]);
            }
        }
        if (! empty($filters['employee_id'])) {
            $query->where('responsible_employee_id', $filters['employee_id']);
        }
        if (! empty($filters['service'])) {
            // A case belongs to a service when it is its own service or when
            // it bought that service as a contract line.
            $query->where(fn ($q) => $q->where('service', $filters['service'])
                ->orWhereHas('services', fn ($line) => $line->where('service', $filters['service'])));
            // The report shows the stage of the selected service's line.
            $query->with(['services' => fn ($line) => $line->where('service', $filters['service'])->with('currentStage:id,name,key')]);
        }
        if (! empty($filters['stage'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('currentStage', fn ($stage) => $stage->where('key', $filters['stage']))
                    ->orWhereHas('services', fn ($line) => $line
                        ->whereHas('currentStage', fn ($stage) => $stage->where('key', $filters['stage']))
                        ->when(! empty($filters['service']), fn ($scoped) => $scoped->where('service', $filters['service'])));
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('applicants.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('applicants.created_at', '<=', $filters['date_to']);
        }

        return $query->latest('applicants.created_at');
    }

    private function applicantReport(array $filters, bool $paginate, Request $request): array
    {
        $columns = ['id', 'applicant', 'employee', 'country', 'university', 'education_level', 'service', 'stage', 'status', 'created_at'];
        $map = fn (Applicant $a) => [
            'id' => $a->id,
            'applicant' => $a->name ?: "Applicant #{$a->id}",
            'employee' => $a->responsibleEmployee?->name ?? 'Unassigned',
            'country' => $a->detail()?->country?->name ?? '—',
            'university' => $a->detail()?->university?->name ?? '—',
            'education_level' => $a->detail()?->educationLevel?->name ?? '—',
            'service' => $a->service,
            // With a service filter the stage belongs to that service's line;
            // without one the case-level mirror is the only single answer.
            'stage' => ! empty($filters['service'])
                ? ($a->services->first()?->currentStage?->name ?? '—')
                : ($a->currentStage?->name ?? '—'),
            'status' => $a->status,
            'created_at' => $a->created_at?->format('Y-m-d') ?? '',
        ];

        if (! $paginate) {
            return ['columns' => $columns, 'rows' => $this->applicantScope($filters)->limit(5000)->get()->map($map)->all(), 'total' => $this->applicantScope($filters)->count()];
        }

        $page = $this->applicantScope($filters)->paginate($request->integer('per_page', 100));

        return ['columns' => $columns, 'rows' => collect($page->items())->map($map)->all(), 'total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()];
    }

    private function performanceReport(array $filters): array
    {
        $columns = ['employee', 'active_cases', 'closed_cases', 'services_sold', 'contracts_sold', 'bonus_amount'];
        $employees = Employee::query()
            ->excludePrimaryRecord()
            ->when(! empty($filters['employee_id']) || ! empty($filters['responsible_employee_id']), fn ($q) => $q->whereKey($filters['employee_id'] ?? $filters['responsible_employee_id']))
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        $rows = $employees->map(function (Employee $employee) {
            $cases = Applicant::query()->where('responsible_employee_id', $employee->id);
            $bonuses = EducationalBonusSource::query()->where('employee_id', $employee->id);
            $sales = ApplicantContract::query()->whereIn('service', BuiltInService::ALL)->where('sold_by_employee_id', $employee->id);
            $contracts = Contract::query()->where(fn ($q) => $q->where('employee_id', $employee->id)->orWhere('sold_by_employee_id', $employee->id))->whereNotIn('status', ['cancelled']);

            return [
                'employee' => $employee->name,
                'active_cases' => (clone $cases)->where('status', 'active')->count(),
                'closed_cases' => (clone $cases)->where('status', 'closed')->count(),
                'services_sold' => (clone $sales)->count(),
                'contracts_sold' => $contracts->count(),
                'bonus_amount' => (float) (clone $bonuses)->whereNotIn('status', ['void'])->sum('bonus_amount'),
            ];
        })->all();

        return ['columns' => $columns, 'rows' => $rows, 'total' => count($rows)];
    }

    private function serviceQuery(string $category, array $filters)
    {
        $query = ApplicantContract::query()->where('service', $category)
            ->with(['contract.applicant:id,name', 'soldByEmployee:id,name', 'applicant.privateDetail.country:id,name', 'applicant.privateDetail.educationLevel:id,name', 'applicant.scholarshipDetail.country:id,name', 'applicant.scholarshipDetail.educationLevel:id,name', 'contract:id,applicant_id,status,start_date']);
        $employee = $filters['employee_id'] ?? $filters['responsible_employee_id'] ?? null;
        if ($employee) {
            $query->where('sold_by_employee_id', $employee);
        }
        foreach (['country_id', 'education_level_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->whereHas('applicant', fn ($applicant) => $applicant->whereScholarshipField($field, $filters[$field]));
            }
        }
        if (! empty($filters['date_from'])) {
            $query->whereHas('contract', fn ($contracts) => $contracts->whereDate('start_date', '>=', $filters['date_from']));
        }
        if (! empty($filters['date_to'])) {
            $query->whereHas('contract', fn ($contracts) => $contracts->whereDate('start_date', '<=', $filters['date_to']));
        }

        return $query->latest('applicant_contract.id');
    }

    private function salesReport(array $filters, bool $paginate, Request $request): array
    {
        $columns = ['id', 'service', 'applicant', 'employee', 'country', 'education_level', 'fee', 'currency', 'date', 'status'];
        $codes = DB::table('currencies')->pluck('code', 'id');
        $collect = function ($items, string $service) use ($codes) {
            return $items->toBase()->map(fn ($s) => [
                'id' => $s->id,
                'service' => $service,
                'applicant' => $s->contract?->applicant?->name ?? $s->applicant?->name ?? "Applicant #{$s->applicant_id}",
                'country' => $s->applicant?->detail()?->country?->name ?? '—',
                'education_level' => $s->applicant?->detail()?->educationLevel?->name ?? '—',
                'employee' => $s->soldByEmployee?->name ?? '—',
                'fee' => (float) $s->fee,
                'currency' => $codes[$s->currency_id] ?? '',
                'date' => $s->contract?->start_date?->toDateString(),
                'status' => $s->contract?->status,
            ]);
        };

        if (! $paginate) {
            $rows = $collect($this->serviceQuery('scholarship', $filters)->limit(2500)->get(), 'scholarship')
                ->merge($collect($this->serviceQuery('test_offer', $filters)->limit(2500)->get(), 'test_offer'))
                ->merge($collect($this->serviceQuery('academy', $filters)->limit(2500)->get(), 'academy'))
                ->merge($collect($this->serviceQuery('private', $filters)->limit(2500)->get(), 'private'))
                ->sortByDesc('date')->values()->all();

            return ['columns' => $columns, 'rows' => $rows, 'total' => count($rows)];
        }

        $perPage = $request->integer('per_page', 100);
        $merged = $collect($this->serviceQuery('scholarship', $filters)->limit(2500)->get(), 'scholarship')
            ->merge($collect($this->serviceQuery('test_offer', $filters)->limit(2500)->get(), 'test_offer'))
            ->merge($collect($this->serviceQuery('academy', $filters)->limit(2500)->get(), 'academy'))
            ->merge($collect($this->serviceQuery('private', $filters)->limit(2500)->get(), 'private'))
            ->sortByDesc('date')->values();
        $page = max(1, $request->integer('page', 1));

        return ['columns' => $columns, 'rows' => $merged->forPage($page, $perPage)->values()->all(), 'total' => $merged->count(), 'current_page' => $page, 'last_page' => (int) ceil($merged->count() / $perPage)];
    }

    private function contractReport(array $filters, bool $paginate, Request $request): array
    {
        $columns = ['id', 'contract_number', 'contract_type', 'template', 'version', 'language', 'employee', 'applicant', 'status', 'start_date'];
        $query = Contract::query()->with(['contractType:id,name', 'template:id,name', 'templateVersion.language:id,name', 'employee:id,name', 'applicant:id,name'])
            ->when(! empty($filters['contract_type_id']), fn ($q) => $q->where('contract_type_id', $filters['contract_type_id']))
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('employee_id', $filters['employee_id']))
            ->when(! empty($filters['language_id']), fn ($q) => $q->whereHas('templateVersion', fn ($vq) => $vq->where('language_id', $filters['language_id'])))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->latest();
        $map = fn (Contract $c) => ['id' => $c->id, 'contract_number' => $c->contract_number ?? "Contract #{$c->id}", 'contract_type' => $c->contractType?->name ?? '—', 'template' => $c->template?->name ?? '—', 'version' => $c->templateVersion?->version_number ?? 'Legacy', 'language' => $c->templateVersion?->language?->name ?? '—', 'employee' => $c->employee?->name ?? '—', 'applicant' => $c->applicant?->name ?? '—', 'applicant_id' => $c->applicant_id ?? '', 'status' => $c->status, 'start_date' => $c->start_date?->format('Y-m-d') ?? ''];
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
            ->when(! empty($filters['country_id']), fn ($q) => $q->whereHas('applicant', fn ($aq) => $aq->whereScholarshipField('country_id', $filters['country_id'])))
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
        $columns = ['record_type', 'id', 'status', 'category', 'applicant', 'employee', 'amount', 'currency', 'date', 'reason'];
        $codes = DB::table('currencies')->pluck('code', 'id');
        $rows = collect();

        foreach ([[Income::class, 'income', 'amount', 'date', 'description'], [Expense::class, 'expense', 'amount', 'date', 'description'], [Salary::class, 'salary', 'total_amount', 'payment_date', 'remarks']] as [$model, $type, $amount, $date, $reason]) {
            $query = $model::query()->with(['employee:id,name'])
                ->when(! empty($filters['employee_id']), fn ($q) => $q->where('employee_id', $filters['employee_id']))
                ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate($date, '>=', $filters['date_from']))
                ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate($date, '<=', $filters['date_to']));
            if (in_array($model, [Income::class, Expense::class], true)) {
                $query->with('applicant:id,name');
                if (! empty($filters['country_id']) || ! empty($filters['university_id'])) {
                    $query->whereHas('applicant', function ($aq) use ($filters) {
                        if (! empty($filters['country_id'])) {
                            $aq->whereScholarshipField('country_id', $filters['country_id']);
                        }
                        if (! empty($filters['university_id'])) {
                            $aq->whereScholarshipField('university_id', $filters['university_id']);
                        }
                    });
                }
            }
            $rows = $rows->merge($query->limit(2500)->get()->map(fn ($r) => [
                // Payroll reports its persisted payment state; income and expense
                // rows have no posting marker, so they are labelled recorded.
                'record_type' => $type, 'id' => $r->id,
                'status' => $type === 'salary' ? ($r->payment_status ?? 'open') : 'recorded',
                'category' => $r->type ?? $type,
                'applicant' => $r->applicant?->name ?? '—',
                'employee' => $r->employee?->name ?? '—', 'amount' => (float) $r->{$amount},
                'currency' => $codes[$r->currency_id] ?? '', 'date' => $r->{$date}?->format('Y-m-d') ?? '',
                'reason' => $r->{$reason} ?? '',
            ]));
        }

        $pending = PendingTransaction::query()->with(['applicant:id,name', 'employee:id,name'])
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('employee_id', $filters['employee_id']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('requested_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('requested_at', '<=', $filters['date_to']))
            ->limit(2500)->get()->map(fn (PendingTransaction $r) => [
                'record_type' => 'pending_transaction', 'id' => $r->id, 'status' => $r->approval_status,
                'category' => $r->transaction_type, 'applicant' => $r->applicant?->name ?? '—',
                'employee' => $r->employee?->name ?? '—',
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
        $query = $this->applicantScope($filters)->reorder()->setEagerLoads([])->joinAdmissionDetails()->selectRaw('detail.country_id, applicants.current_stage_id, COUNT(*) as total')->groupBy('detail.country_id', 'applicants.current_stage_id')->whereNotNull('detail.country_id');
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
            $query->whereHas('applicant', fn ($aq) => $aq->whereScholarshipField('country_id', $filters['country_id']));
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
