<?php

namespace App\Http\Controllers;

use App\Http\Resources\SalaryResource;
use App\Models\Salary;
use App\Models\SalaryPayment;
use App\Services\SalaryExpenseSynchronizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryPaymentController extends Controller
{
    public function store(Request $request, Salary $salary, SalaryExpenseSynchronizer $expenses): SalaryResource
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'date' => ['required', 'date', 'before_or_equal:today'], 'remarks' => ['nullable', 'string', 'max:255']]);
        DB::transaction(function () use ($salary, $data, $request, $expenses): void {
            $salary = Salary::query()->with('deductions')->lockForUpdate()->findOrFail($salary->id);
            $expenses->sync($salary);
            $remaining = max(0, (float) $salary->amount_due - (float) $salary->amount_paid_total);
            if ((float) $data['amount'] > $remaining + 0.00000001) throw ValidationException::withMessages(['amount' => ['The payment cannot exceed the outstanding balance.']]);
            $expenses->createPayment($salary, (float) $data['amount'], $data['date'], $data['remarks'] ?? null, $request->user()->id);
        });
        return new SalaryResource($salary->fresh(['employee', 'currency', 'bonuses', 'deductions', 'payments', 'recorder']));
    }

    public function destroy(Salary $salary, SalaryPayment $payment, SalaryExpenseSynchronizer $expenses): SalaryResource
    {
        abort_unless((int) $payment->salary_id === (int) $salary->id, 404);
        DB::transaction(function () use ($salary, $payment, $expenses): void {
            $payment = SalaryPayment::query()->with('expense')->lockForUpdate()->findOrFail($payment->id);
            $payment->expense?->delete();
            $payment->delete();
            $expenses->refreshPaymentTotals(Salary::query()->with('deductions')->lockForUpdate()->findOrFail($salary->id));
        });
        return new SalaryResource($salary->fresh(['employee', 'currency', 'bonuses', 'deductions', 'payments', 'recorder']));
    }
}
