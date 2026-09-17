<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Applicant;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PendingTransaction;
use App\Models\Salary;
use App\Support\EditWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PendingTransactionController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => PendingTransaction::with(['currency', 'requester', 'approver', 'customer', 'applicant', 'employee'])->latest()->get()]);
    }

    public function options()
    {
        return response()->json(['success' => true, 'data' => [
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'name', 'code', 'symbol', 'is_base']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->orderBy('name')->get(['id', 'name']),
            'applicants' => Applicant::query()->with('customer:id,name')->latest()->get(['id', 'customer_id']),
        ]]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return response()->json(['success' => true, 'data' => PendingTransaction::create([
            ...$data,
            'requested_by' => $request->user()->id,
            'approval_status' => PendingTransaction::SUBMITTED,
            'requested_at' => now(),
        ])], 201);
    }

    public function update(Request $request, PendingTransaction $pendingTransaction, EditWindow $editWindow)
    {
        abort_unless($pendingTransaction->requested_by === $request->user()->id || $request->user()->hasPermission('override_edit_window'), 403, 'auth.forbidden');
        abort_unless(in_array($pendingTransaction->approval_status, [PendingTransaction::DRAFT, PendingTransaction::SUBMITTED], true), 422, 'business.pending_transaction_locked');
        $editWindow->enforce($request->user(), $pendingTransaction);
        $pendingTransaction->update($this->validated($request, true));

        return response()->json(['success' => true, 'data' => $pendingTransaction->fresh()]);
    }

    public function review(Request $request, PendingTransaction $pendingTransaction)
    {
        return $this->transition($request, $pendingTransaction, [PendingTransaction::SUBMITTED], PendingTransaction::UNDER_REVIEW);
    }

    public function approve(Request $request, PendingTransaction $pendingTransaction)
    {
        abort_if($pendingTransaction->requested_by === $request->user()->id, 422, 'business.pending_transaction_self_approval');

        return $this->transition($request, $pendingTransaction, [PendingTransaction::SUBMITTED, PendingTransaction::UNDER_REVIEW], PendingTransaction::APPROVED, [
            'approved_at' => now(),
        ]);
    }

    public function reject(Request $request, PendingTransaction $pendingTransaction)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:5000']]);
        abort_if($pendingTransaction->requested_by === $request->user()->id, 422, 'business.pending_transaction_self_approval');

        return $this->transition($request, $pendingTransaction, [PendingTransaction::SUBMITTED, PendingTransaction::UNDER_REVIEW], PendingTransaction::REJECTED, [
            ...$data,
            'rejected_at' => now(),
        ]);
    }

    public function post(Request $request, PendingTransaction $pendingTransaction)
    {
        return DB::transaction(function () use ($request, $pendingTransaction) {
            $pending = PendingTransaction::query()->lockForUpdate()->findOrFail($pendingTransaction->id);
            if ($pending->posted_at) return response()->json(['success' => true, 'data' => $pending]);
            abort_unless($pending->approval_status === PendingTransaction::APPROVED, 422, 'business.pending_transaction_not_approved');

            $currency = Currency::query()->where('is_active', true)->findOrFail($pending->currency_id);
            $baseCurrency = Currency::query()->where('is_base', true)->where('is_active', true)->firstOrFail();
            $rate = (float) $currency->rate;
            abort_unless($rate > 0, 422, 'business.pending_transaction_currency_rate_invalid');
            $common = ['currency_id' => $currency->id, 'base_currency_id' => $baseCurrency->id, 'rate' => $rate, 'base_amount' => $pending->amount / $rate, 'recorded_by' => $request->user()->id];

            $record = match ($pending->transaction_type) {
                'income' => Income::create([...$common, 'pending_transaction_id' => $pending->id, 'customer_id' => $pending->customer_id, 'type' => 'Other', 'description' => $pending->reason, 'amount' => $pending->amount, 'quantity' => 1, 'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $baseCurrency->code, 'date' => now()->toDateString()]),
                'expense' => Expense::create([...$common, 'pending_transaction_id' => $pending->id, 'customer_id' => $pending->customer_id, 'currency' => $currency->code, 'type' => 'Pending transaction', 'description' => $pending->reason, 'amount' => $pending->amount, 'quantity' => 1, 'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $baseCurrency->code, 'date' => now()->toDateString()]),
                'salary' => Salary::create(['employee_id' => $pending->employee_id, 'pending_transaction_id' => $pending->id, ...$common, 'currency_code' => $currency->code, 'currency_name' => $currency->name, 'currency_symbol' => $currency->symbol, 'currency_decimal_places' => $currency->decimal_places, 'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $baseCurrency->code, 'salary_month' => now()->startOfMonth()->toDateString(), 'amount_paid' => $pending->amount, 'total_amount' => $pending->amount, 'total_days' => 0, 'remarks' => $pending->reason, 'payment_date' => now()->toDateString()]),
            };

            $pending->update(['approval_status' => PendingTransaction::POSTED, 'posted_at' => now(), 'posted_record_type' => $record::class, 'posted_record_id' => $record->id]);

            return response()->json(['success' => true, 'data' => $pending->fresh()]);
        });
    }

    private function transition(Request $request, PendingTransaction $pendingTransaction, array $from, string $to, array $attributes = [])
    {
        return DB::transaction(function () use ($request, $pendingTransaction, $from, $to, $attributes) {
            $pending = PendingTransaction::query()->lockForUpdate()->findOrFail($pendingTransaction->id);
            abort_unless(in_array($pending->approval_status, $from, true), 422, 'business.pending_transaction_invalid_transition');
            $pending->update([...$attributes, 'approval_status' => $to, 'approver_id' => $request->user()->id]);

            return response()->json(['success' => true, 'data' => $pending->fresh()]);
        });
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'transaction_type' => [$presence, 'in:income,expense,salary'], 'reason' => [$presence, 'string', 'max:5000'], 'amount' => [$presence, 'numeric', 'gt:0'],
            'currency_id' => [$presence, 'exists:currencies,id'], 'customer_id' => ['nullable', 'exists:customers,id'], 'applicant_id' => ['nullable', 'exists:applicants,id'], 'employee_id' => ['nullable', 'exists:employees,id'],
        ]);
        $type = $data['transaction_type'] ?? $request->route('pendingTransaction')?->transaction_type;
        if ($type === 'salary' && ! ($data['employee_id'] ?? $request->route('pendingTransaction')?->employee_id)) throw ValidationException::withMessages(['employee_id' => ['Salary requires an employee.']]);
        if (isset($data['applicant_id'], $data['customer_id']) && ! DB::table('applicants')->whereKey($data['applicant_id'])->where('customer_id', $data['customer_id'])->exists()) throw ValidationException::withMessages(['applicant_id' => ['The applicant must belong to the selected customer.']]);

        return $data;
    }
}
