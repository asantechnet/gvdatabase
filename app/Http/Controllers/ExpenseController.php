<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\ExpenseResource;
use App\Models\Currency;
use App\Models\Expense;
use App\Services\ActiveContractService;
use App\Services\CurrencyConversionService;
use App\Services\CurrencyRateService;
use App\Services\EducationalWorkflowNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    use PaginatesLists;

    public function __construct(
        private readonly CurrencyConversionService $currencies,
        private readonly CurrencyRateService $rates,
        private readonly EducationalWorkflowNotificationService $notifications,
    ) {}

    public function index(Request $request)
    {
        $query = Expense::with(['expenseCurrency', 'recorder', 'applicant', 'approver']);
        $this->scopeToVisibleRecords($request, $query);

        if ($request->filled('status')) {
            $request->validate(['status' => ['required', Rule::in([Expense::DRAFT, Expense::APPROVED, Expense::REJECTED])]]);
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->integer('currency_id'));
        }

        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', '%'.$request->search.'%')
                    ->orWhere('type', 'like', '%'.$request->search.'%');
            });
        }

        $dates = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', $request->filled('date_from') ? 'after_or_equal:date_from' : 'nullable'],
        ]);

        if (! empty($dates['date_from'])) {
            $query->whereDate('date', '>=', $dates['date_from']);
        }

        if (! empty($dates['date_to'])) {
            $query->whereDate('date', '<=', $dates['date_to']);
        }

        $query->orderBy('date', 'desc')->orderBy('id', 'desc');

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true] + $this->paginatedPayload($request, $query, fn ($rows) => ExpenseResource::collection($rows)->resolve()));
        }

        return ExpenseResource::collection($query->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'applicant_id' => ['nullable', 'integer', Rule::exists('applicants', 'id')->whereNull('deleted_at')],
            'status' => ['sometimes', Rule::in([Expense::DRAFT, Expense::APPROVED])],
            'type' => ['required', 'string', 'max:255', Rule::exists('finance_types', 'value')->where('kind', 'expense')],
            'description' => 'required|string',
            'amount' => 'required|numeric|gt:0',
            'quantity' => 'required|integer|min:1',
            'currency_id' => 'required|exists:currencies,id',
            'rate' => 'nullable|numeric|min:0.00000001',
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $validator->validate();

        $data = $validator->validated();
        $data['status'] = $this->creationStatus($request, $data['status'] ?? null);
        if ($data['status'] === Expense::DRAFT) {
            // A case-level draft follows the same rule the retired payment
            // request enforced: an applicant expense needs an active contract.
            app(ActiveContractService::class)->requireActiveContractForExpense($data['applicant_id'] ?? null);
        }
        $data['recorded_by'] = $request->user()->id;
        $expense = DB::transaction(function () use ($data) {
            $this->applyCurrencySnapshot($data);

            return Expense::create($data);
        });

        if ($expense->status === Expense::DRAFT) {
            $this->notifications->financeDraftSubmitted($expense);
        }

        // Return the created expense with 201 status code
        return (new ExpenseResource($expense->load(['expenseCurrency', 'recorder', 'applicant', 'approver'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, Expense $expense)
    {
        $this->assertCanViewRecord($request, $expense);

        return new ExpenseResource($expense->load(['expenseCurrency', 'recorder', 'applicant', 'approver']));
    }

    public function update(Request $request, Expense $expense)
    {
        if ($expense->salary_id) {
            throw ValidationException::withMessages(['expense' => ['business.salary_expense_managed_by_payroll']]);
        }
        $validator = Validator::make($request->all(), [
            'applicant_id' => ['nullable', 'integer', Rule::exists('applicants', 'id')->whereNull('deleted_at')],
            'type' => ['sometimes', 'string', 'max:255', Rule::exists('finance_types', 'value')->where('kind', 'expense')],
            'description' => 'sometimes|string',
            'amount' => 'sometimes|numeric|gt:0',
            'quantity' => 'sometimes|integer|min:1',
            'currency_id' => 'sometimes|exists:currencies,id',
            'rate' => 'nullable|numeric|min:0.00000001',
            'date' => ['sometimes', 'date', 'before_or_equal:today'],
        ]);

        $validator->validate();
        $this->assertCanEditRecord($request, $expense);

        $data = $validator->validated();

        DB::transaction(function () use ($data, $expense) {
            if (array_key_exists('currency_id', $data)) {
                $this->applyCurrencySnapshot($data);
            } elseif (isset($data['rate'])) {
                $data['rate'] = $this->rates->remember($expense->currency_id, $data['rate']);
            } else {
                unset($data['rate']);
            }

            $expense->update($data);
        });

        return new ExpenseResource($expense->load(['expenseCurrency', 'recorder', 'applicant', 'approver']));
    }

    public function approve(Request $request, Expense $expense)
    {
        return DB::transaction(function () use ($request, $expense) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            abort_unless($expense->status === Expense::DRAFT, 422, 'business.finance_record_not_draft');

            $expense->update([
                'status' => Expense::APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
            $this->notifications->financeDraftStatusChanged($expense->fresh(), Expense::APPROVED, $request->user()->id);

            return new ExpenseResource($expense->fresh()->load(['expenseCurrency', 'recorder', 'applicant', 'approver']));
        });
    }

    public function reject(Request $request, Expense $expense)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:5000']]);

        return DB::transaction(function () use ($request, $expense, $data) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            abort_unless($expense->status === Expense::DRAFT, 422, 'business.finance_record_not_draft');

            $expense->update([
                'status' => Expense::REJECTED,
                'rejected_at' => now(),
                'rejection_reason' => $data['rejection_reason'],
            ]);
            $this->notifications->financeDraftStatusChanged($expense->fresh(), Expense::REJECTED, $request->user()->id);

            return new ExpenseResource($expense->fresh()->load(['expenseCurrency', 'recorder', 'applicant', 'approver']));
        });
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->assertCanDeleteRecord($request, $expense);

        if ($expense->salary_id) {
            throw ValidationException::withMessages(['expense' => ['business.salary_expense_managed_by_payroll']]);
        }
        if ($expense->document_path) {
            Storage::disk('local')->delete($expense->document_path);
        }
        $expense->delete();

        return response()->noContent();
    }

    public function uploadDocument(Request $request, Expense $expense)
    {
        $request->validate(['document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:5120']]);
        $file = $request->file('document');
        $path = $file->storeAs("expense-documents/{$expense->id}", Str::uuid().'.'.$file->extension(), 'local');
        if ($expense->document_path) {
            Storage::disk('local')->delete($expense->document_path);
        }
        $expense->update(['document_path' => $path, 'document_name' => $file->getClientOriginalName(), 'document_mime' => $file->getMimeType(), 'document_size' => $file->getSize()]);

        return new ExpenseResource($expense->fresh(['expenseCurrency', 'recorder']));
    }

    public function downloadDocument(Request $request, Expense $expense)
    {
        $this->assertCanViewRecord($request, $expense);
        abort_unless($expense->document_path && Storage::disk('local')->exists($expense->document_path), 404);

        return Storage::disk('local')->download($expense->document_path, $expense->document_name ?: 'expense-document');
    }

    public function removeDocument(Expense $expense)
    {
        if ($expense->document_path) {
            Storage::disk('local')->delete($expense->document_path);
        }
        $expense->update(['document_path' => null, 'document_name' => null, 'document_mime' => null, 'document_size' => null]);

        return new ExpenseResource($expense->fresh(['expenseCurrency', 'recorder']));
    }

    /**
     * A list is scoped by the explicit own/all grants. A legacy finance viewer
     * who holds no scope grant keeps the historical all-records behaviour.
     */
    private function scopeToVisibleRecords(Request $request, $query): void
    {
        $user = $request->user();
        $seesAll = $user->hasPermission('view_all_finance_records')
            || (! $user->hasPermission('view_own_finance_records') && $user->hasPermission('view_expenses'));

        if (! $seesAll) {
            $query->where('recorded_by', $user->id);
        }
    }

    private function assertCanViewRecord(Request $request, Expense $expense): void
    {
        $user = $request->user();
        $seesAll = $user->hasPermission('view_all_finance_records')
            || (! $user->hasPermission('view_own_finance_records') && $user->hasPermission('view_expenses'));

        abort_unless(
            $seesAll || ((int) $expense->recorded_by === (int) $user->id && $user->hasPermission('view_own_finance_records')),
            403,
            'auth.forbidden',
        );
    }

    private function creationStatus(Request $request, ?string $status): string
    {
        $status = $status ?: Expense::APPROVED;
        abort_unless(in_array($status, [Expense::DRAFT, Expense::APPROVED], true), 422, 'business.finance_record_status_invalid');

        if ($status === Expense::DRAFT) {
            abort_unless($request->user()->hasPermission('create_draft_finance_records'), 403, 'auth.forbidden');
        } else {
            abort_unless($request->user()->hasPermission('create_expenses'), 403, 'auth.forbidden');
        }

        return $status;
    }

    private function assertCanEditRecord(Request $request, Expense $expense): void
    {
        $user = $request->user();
        if ($expense->status === Expense::DRAFT) {
            abort_unless(
                $user->hasPermission('edit_expenses')
                || ((int) $expense->recorded_by === (int) $user->id && $user->hasPermission('create_draft_finance_records')),
                403,
                'auth.forbidden',
            );

            return;
        }

        abort_unless($user->hasPermission('edit_expenses'), 403, 'auth.forbidden');
    }

    private function assertCanDeleteRecord(Request $request, Expense $expense): void
    {
        $user = $request->user();
        if ($user->hasPermission('delete_expenses')) {
            return;
        }

        abort_unless(
            $expense->status === Expense::DRAFT
            && (int) $expense->recorded_by === (int) $user->id
            && $user->hasPermission('create_draft_finance_records'),
            403,
            'auth.forbidden',
        );
    }

    /** @param array<string, mixed> $data */
    private function applyCurrencySnapshot(array &$data): void
    {
        $currency = Currency::query()->findOrFail($data['currency_id']);
        if (isset($data['rate'])) {
            $this->rates->remember($currency->id, $data['rate']);
            $currency->refresh();
        }
        $data = [...$data, ...$this->currencies->snapshot($currency)];
    }
}
