<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PendingTransaction;
use App\Models\Salary;
use App\Services\ActiveContractService;
use App\Services\EducationalWorkflowNotificationService;
use App\Services\LoanReceiptService;
use App\Support\EditWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PendingTransactionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([
                PendingTransaction::SUBMITTED, PendingTransaction::UNDER_REVIEW,
                PendingTransaction::APPROVED, PendingTransaction::REJECTED,
                PendingTransaction::POSTED, PendingTransaction::DRAFT,
            ])],
            'transaction_type' => ['nullable', Rule::in(['income', 'expense', 'salary'])],
        ]);

        $query = PendingTransaction::with(['currency', 'requester', 'approver', 'applicant', 'employee']);
        if (! $request->user()->hasPermission('approve_pending_transactions') && ! $request->user()->hasPermission('post_pending_transactions')) {
            $query->where('requested_by', $request->user()->id);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        $query
            ->when($search !== '', fn ($builder) => $builder->where('reason', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('approval_status', $status))
            ->when($filters['transaction_type'] ?? null, fn ($builder, $type) => $builder->where('transaction_type', $type));

        return response()->json(['success' => true, 'data' => $query->latest()->paginate($request->integer('per_page', 25))]);
    }

    public function receipt(PendingTransaction $pendingTransaction, LoanReceiptService $receipts)
    {
        return response()->json(['success' => true, 'data' => $receipts->for($pendingTransaction)]);
    }

    public function options(Request $request)
    {
        $isManager = $this->isManager($request);
        $ownEmployeeId = $isManager ? null : Employee::query()->where('user_id', $request->user()->id)->value('id');

        return response()->json(['success' => true, 'data' => [
            // Every currency is offered; the base one stays first for the pickers.
            'currencies' => Currency::query()->orderByDesc('is_base')->orderBy('code')->get(['id', 'name', 'code', 'symbol', 'is_base']),
            'employees' => Employee::query()->active()->excludePrimaryRecord()->when(! $isManager, fn ($query) => $query->whereKey($ownEmployeeId))->orderBy('name')->get(['id', 'name']),
            'applicants' => Applicant::query()->when(! $isManager, fn ($query) => $query->where('responsible_employee_id', $ownEmployeeId))->latest()->get(['id', 'name']),
        ]]);
    }

    public function store(Request $request, EducationalWorkflowNotificationService $notifications)
    {
        $data = $this->validated($request);

        $pending = PendingTransaction::create([
            ...$data,
            'requested_by' => $request->user()->id,
            'approval_status' => PendingTransaction::SUBMITTED,
            'requested_at' => now(),
        ])->load('requester');
        $notifications->pendingSubmitted($pending);

        return response()->json(['success' => true, 'data' => $pending], 201);
    }

    public function update(Request $request, PendingTransaction $pendingTransaction, EditWindow $editWindow)
    {
        abort_unless($pendingTransaction->requested_by === $request->user()->id || $request->user()->hasPermission('override_edit_window'), 403, 'auth.forbidden');
        abort_unless(in_array($pendingTransaction->approval_status, [PendingTransaction::DRAFT, PendingTransaction::SUBMITTED], true), 422, 'business.pending_transaction_locked');
        $editWindow->enforce($request->user(), $pendingTransaction);
        $pendingTransaction->update($this->validated($request, true));

        return response()->json(['success' => true, 'data' => $pendingTransaction->fresh()]);
    }

    public function review(Request $request, PendingTransaction $pendingTransaction, EducationalWorkflowNotificationService $notifications)
    {
        return $this->transition($request, $pendingTransaction, [PendingTransaction::SUBMITTED], PendingTransaction::UNDER_REVIEW, notifications: $notifications);
    }

    public function approve(Request $request, PendingTransaction $pendingTransaction, EducationalWorkflowNotificationService $notifications)
    {
        abort_if($pendingTransaction->requested_by === $request->user()->id, 422, 'business.pending_transaction_self_approval');

        return $this->transition($request, $pendingTransaction, [PendingTransaction::SUBMITTED, PendingTransaction::UNDER_REVIEW], PendingTransaction::APPROVED, [
            'approved_at' => now(),
        ], $notifications);
    }

    public function reject(Request $request, PendingTransaction $pendingTransaction, EducationalWorkflowNotificationService $notifications)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:5000']]);
        abort_if($pendingTransaction->requested_by === $request->user()->id, 422, 'business.pending_transaction_self_approval');

        return $this->transition($request, $pendingTransaction, [PendingTransaction::SUBMITTED, PendingTransaction::UNDER_REVIEW], PendingTransaction::REJECTED, [
            ...$data,
            'rejected_at' => now(),
        ], $notifications);
    }

    public function post(Request $request, PendingTransaction $pendingTransaction, EducationalWorkflowNotificationService $notifications)
    {
        return DB::transaction(function () use ($request, $pendingTransaction, $notifications) {
            $pending = PendingTransaction::query()->lockForUpdate()->findOrFail($pendingTransaction->id);
            if ($pending->posted_at) {
                return response()->json(['success' => true, 'data' => $pending]);
            }
            // Posting is the final authorization step, so the same rule that
            // blocks self-approval also blocks a requester posting their own
            // request. The UI hides the action; the API remains authoritative.
            abort_if($pending->requested_by === $request->user()->id, 422, 'business.pending_transaction_self_approval');
            abort_unless($pending->approval_status === PendingTransaction::APPROVED, 422, 'business.pending_transaction_not_approved');

            $currency = Currency::query()->findOrFail($pending->currency_id);
            $baseCurrency = Currency::query()->where('is_base', true)->where('is_active', true)->firstOrFail();
            $rate = (float) $currency->rate;
            abort_unless($rate > 0, 422, 'business.pending_transaction_currency_rate_invalid');
            $common = ['currency_id' => $currency->id, 'base_currency_id' => $baseCurrency->id, 'rate' => $rate, 'base_amount' => $pending->amount / $rate, 'recorded_by' => $request->user()->id];

            $record = match ($pending->transaction_type) {
                'income' => Income::create([...$common, 'pending_transaction_id' => $pending->id, 'applicant_id' => $pending->applicant_id, 'employee_id' => $pending->employee_id, 'type' => 'Other', 'description' => $pending->reason, 'amount' => $pending->amount, 'quantity' => 1, 'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $baseCurrency->code, 'date' => now()->toDateString()]),
                'expense' => Expense::create([...$common, 'pending_transaction_id' => $pending->id, 'applicant_id' => $pending->applicant_id, 'employee_id' => $pending->employee_id, 'currency' => $currency->code, 'type' => 'Pending transaction', 'description' => $pending->reason, 'amount' => $pending->amount, 'quantity' => 1, 'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $baseCurrency->code, 'date' => now()->toDateString()]),
                // Legacy only: salary is no longer offered on payment requests,
                // but rows created before that change must still post so their
                // history and payroll link stay intact.
                'salary' => Salary::create(['employee_id' => $pending->employee_id, 'pending_transaction_id' => $pending->id, ...$common, 'currency_code' => $currency->code, 'currency_name' => $currency->name, 'currency_symbol' => $currency->symbol, 'currency_decimal_places' => $currency->decimal_places, 'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $baseCurrency->code, 'salary_month' => now()->startOfMonth()->toDateString(), 'amount_paid' => $pending->amount, 'total_amount' => $pending->amount, 'total_days' => 0, 'remarks' => $pending->reason, 'payment_date' => now()->toDateString()]),
            };

            $pending->update(['approval_status' => PendingTransaction::POSTED, 'posted_at' => now(), 'posted_record_type' => $record::class, 'posted_record_id' => $record->id]);
            $notifications->pendingStatusChanged($pending->fresh(), PendingTransaction::POSTED, $request->user()->id);

            return response()->json(['success' => true, 'data' => $pending->fresh()]);
        });
    }

    private function transition(Request $request, PendingTransaction $pendingTransaction, array $from, string $to, array $attributes = [], ?EducationalWorkflowNotificationService $notifications = null)
    {
        return DB::transaction(function () use ($request, $pendingTransaction, $from, $to, $attributes, $notifications) {
            $pending = PendingTransaction::query()->lockForUpdate()->findOrFail($pendingTransaction->id);
            abort_unless(in_array($pending->approval_status, $from, true), 422, 'business.pending_transaction_invalid_transition');
            $pending->update([...$attributes, 'approval_status' => $to, 'approver_id' => $request->user()->id]);
            $notifications?->pendingStatusChanged($pending->fresh(), $to, $request->user()->id);

            return response()->json(['success' => true, 'data' => $pending->fresh()]);
        });
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'transaction_type' => [$presence, 'in:income,expense'], 'reason' => [$presence, 'string', 'max:5000'], 'amount' => [$presence, 'numeric', 'gt:0'],
            'currency_id' => [$presence, 'exists:currencies,id'], 'applicant_id' => ['nullable', 'exists:applicants,id'], 'employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        if (! $this->isManager($request)) {
            $ownEmployeeId = Employee::query()->where('user_id', $request->user()->id)->value('id');
            abort_unless($ownEmployeeId, 422, 'business.employee_profile_missing');
            if (isset($data['employee_id'])) {
                abort_unless((int) $data['employee_id'] === (int) $ownEmployeeId, 403, 'auth.forbidden');
            }
            if (isset($data['applicant_id'])) {
                abort_unless(Applicant::query()->whereKey($data['applicant_id'])->where('responsible_employee_id', $ownEmployeeId)->exists(), 403, 'auth.forbidden');
            }
        }
        $type = $data['transaction_type'] ?? $request->route('pendingTransaction')?->transaction_type;
        // Legacy only: the salary branch is unreachable for new or edited input,
        // but an old salary row still needs an employee to post.
        if ($type === 'salary' && ! ($data['employee_id'] ?? $request->route('pendingTransaction')?->employee_id)) {
            throw ValidationException::withMessages(['employee_id' => ['Salary requires an employee.']]);
        }
        // Plan §21: applicant-specific operational expenses require an active
        // contract. Income (payments in) is exempt — money in must flow
        // regardless of contract state.
        $applicantId = $data['applicant_id'] ?? ($partial ? $request->route('pendingTransaction')?->applicant_id : null);
        if ($type === 'expense') {
            app(ActiveContractService::class)->requireActiveContractForExpense($applicantId);
        }

        return $data;
    }

    private function isManager(Request $request): bool
    {
        $user = $request->user();

        return $user->isSuperAdmin() || array_intersect($user->roleNames(), ['admin', 'manager']) !== [];
    }
}
