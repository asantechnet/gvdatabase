<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Expence;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PDF;

class ReportController extends Controller
{
    public function generateInvoiceReport(Request $request)
    {
        $validator = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $invoices = Invoice::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'Void')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalAmount = $invoices->sum(fn ($invoice) =>
            (float) $invoice->currency_rate > 0
                ? (float) $invoice->amount / (float) $invoice->currency_rate
                : 0
        );
        $totalInvoices = $invoices->count();

        return response()->json([
            'success' => true,
            'data' => [
                'invoices' => $invoices,
                'total_amount' => $totalAmount,
                'total_invoices' => $totalInvoices,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]
        ]);
    }

    public function generateExpenceReport(Request $request)
    {
        $validator = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $expences = Expence::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalAmount = $expences->sum('amount');
        $totalExpences = $expences->count();

        $groupedByCategory = $expences->groupBy('category')->map(function ($items) {
            return [
                'total' => $items->sum('amount'),
                'count' => $items->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'expences' => $expences,
                'total_amount' => $totalAmount,
                'total_expences' => $totalExpences,
                'by_category' => $groupedByCategory,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]
        ]);
    }

    public function generateSalaryReport(Request $request)
    {
        $validator = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $salaries = EmployeeSalary::whereBetween('salary_month', [$startDate, $endDate])
            ->with('employee')
            ->orderBy('salary_month', 'desc')
            ->get();

        $totalSalary = $salaries->sum(function ($salary) {
            // Calculate actual salary based on fraction and bonus
            $employee = $salary->employee;
            $baseSalary = $employee ? $employee->position->salary : 0;
            $actualSalary = ($baseSalary * $salary->salary_fraction / 100) + $salary->bonus;
            return $actualSalary;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'salaries' => $salaries,
                'total_salary' => $totalSalary,
                'total_records' => $salaries->count(),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]
        ]);
    }

    public function exportInvoiceReport(Request $request)
    {
        $validator = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $invoices = Invoice::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'Void')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalAmount = $invoices->sum(fn ($invoice) =>
            (float) $invoice->currency_rate > 0
                ? (float) $invoice->amount / (float) $invoice->currency_rate
                : 0
        );

        $pdf = PDF::loadView('reports.invoices', [
            'invoices' => $invoices,
            'totalAmount' => $totalAmount,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $fileName = 'invoice_report_' . time() . '.pdf';
        $filePath = 'reports/' . $fileName;
        Storage::put('public/' . $filePath, $pdf->output());

        return response()->json([
            'success' => true,
            'file_path' => $filePath,
            'file_name' => $fileName,
        ]);
    }
}
