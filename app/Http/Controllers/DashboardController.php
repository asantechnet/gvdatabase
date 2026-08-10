<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmployeeResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ExpenceResource;
use App\Http\Resources\ContractResource;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Expence;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        $totalEmployees = Employee::count();
        $activeInvoices = Invoice::where('status', '!=', 'Void');
        $totalInvoices = (clone $activeInvoices)->count();
        $totalExpences = Expence::count();
        $totalContracts = Contract::count();

        $totalInvoiceAmount = (clone $activeInvoices)->get()->sum(fn ($invoice) =>
            (float) $invoice->currency_rate > 0
                ? (float) $invoice->amount / (float) $invoice->currency_rate
                : 0
        );
        $totalExpenceAmount = Expence::sum('amount');

        $recentEmployees = Employee::with(['position', 'user'])->orderBy('created_at', 'desc')->take(5)->get();
        $recentInvoices = Invoice::with(['customer', 'currency', 'services', 'incomes'])
            ->orderBy('created_at', 'desc')->take(5)->get();

        $monthlyInvoices = Invoice::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', '!=', 'Void')
            ->count();

        $monthlyExpences = Expence::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_employees' => $totalEmployees,
                'total_invoices' => $totalInvoices,
                'total_expences' => $totalExpences,
                'total_contracts' => $totalContracts,
                'total_invoice_amount' => $totalInvoiceAmount,
                'total_expence_amount' => $totalExpenceAmount,
                'recent_employees' => EmployeeResource::collection($recentEmployees),
                'recent_invoices' => InvoiceResource::collection($recentInvoices),
                'monthly_invoices' => $monthlyInvoices,
                'monthly_expences' => $monthlyExpences,
            ]
        ]);
    }

    public function chartData(Request $request)
    {
        $year = $request->input('year', date('Y'));

        $monthlyInvoices = [];
        $monthlyExpences = [];

        for ($month = 1; $month <= 12; $month++) {
            $invoiceCount = Invoice::whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->where('status', '!=', 'Void')
                ->count();

            $expenceAmount = Expence::whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->sum('amount');

            $monthlyInvoices[] = $invoiceCount;
            $monthlyExpences[] = $expenceAmount;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                'invoices' => $monthlyInvoices,
                'expences' => $monthlyExpences,
            ]
        ]);
    }
}
