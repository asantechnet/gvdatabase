<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateReportRequest;
use App\Models\User;
use App\Services\ReportService;
use App\Support\AcceptedLocale;
use App\Support\ReportDateFormatter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function catalog(GenerateReportRequest $request, ReportService $reports): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        return response()->json(['success' => true, 'data' => $reports->catalog($user)]);
    }

    public function generate(GenerateReportRequest $request, string $type, ReportService $reports): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        return response()->json(['success' => true, 'data' => $reports->generate($type, $request->validated(), $user)]);
    }

    public function export(GenerateReportRequest $request, string $type, ReportService $reports): StreamedResponse|\Illuminate\Contracts\View\View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($reports->canExport($user, $type), 403, 'auth.forbidden');
        // The print and CSV documents are rendered server-side, so the SPA's
        // locale travels with the request instead of relying on the session.
        AcceptedLocale::fromRequest($request);
        $report = $reports->generate($type, $request->validated(), $user);

        if (($request->validated('format') ?? 'csv') === 'print') {
            return view('reports.print', [
                'report' => $report,
                'direction' => in_array(app()->getLocale(), ['ar', 'fa', 'ps'], true) ? 'rtl' : 'ltr',
            ]);
        }

        $filename = sprintf('%s-report-%s-%s.csv', $type, $report['filters']['start_date'], $report['filters']['end_date']);
        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            foreach ($report['sections'] as $index => $section) {
                if ($index > 0) fputcsv($stream, []);
                fputcsv($stream, [$section['key']]);
                fputcsv($stream, $section['columns']);
                foreach ($section['rows'] as $row) {
                    fputcsv($stream, array_map(fn (string $column) => $this->csvValue($row[$column] ?? null), $section['columns']));
                }
                fputcsv($stream, ['totals', json_encode($section['totals'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
            fputcsv($stream, []);
            // The UTC value stays machine-readable; the localized line makes the
            // export readable in the same way the printed report is.
            fputcsv($stream, ['generated_at_utc', $report['meta']['generated_at']]);
            fputcsv($stream, ['generated_at', ReportDateFormatter::human($report['meta']['generated_at'], $report['meta']['timezone'])]);
            fputcsv($stream, ['company_timezone', $report['meta']['timezone']]);
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function generateExpenseReport(GenerateReportRequest $request, ReportService $reports): JsonResponse
    {
        return $this->legacy($request, $reports, 'financial', 'expenses');
    }

    /** @deprecated Kept temporarily for clients using the misspelled endpoint. */
    public function generateExpenceReport(GenerateReportRequest $request, ReportService $reports): JsonResponse
    {
        return $this->generateExpenseReport($request, $reports);
    }

    public function generateSalaryReport(GenerateReportRequest $request, ReportService $reports): JsonResponse
    {
        return $this->legacy($request, $reports, 'payroll', 'salary_payments');
    }

    public function generateIncomeReport(GenerateReportRequest $request, ReportService $reports): JsonResponse
    {
        return $this->legacy($request, $reports, 'financial', 'income');
    }

    public function generateVisitorReport(GenerateReportRequest $request, ReportService $reports): JsonResponse
    {
        return $this->legacy($request, $reports, 'operations', 'visitors');
    }

    private function legacy(GenerateReportRequest $request, ReportService $reports, string $type, string $section): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $report = $reports->generate($type, $request->validated(), $user);
        $report['sections'] = array_values(array_filter($report['sections'], fn (array $item) => $item['key'] === $section));
        return response()->json(['success' => true, 'data' => $report]);
    }

    private function csvValue(mixed $value): string|int|float
    {
        if (is_bool($value)) return $value ? 'yes' : 'no';
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $value ?? '';
    }
}
