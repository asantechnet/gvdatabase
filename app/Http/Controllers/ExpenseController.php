<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\ExpenseResource;
use App\Models\Currency;
use App\Models\Expense;
use App\Services\CurrencyConversionService;
use App\Services\CurrencyRateService;
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
    ) {}

    public function index(Request $request)
    {
        $query = Expense::with(['expenseCurrency', 'recorder', 'applicant']);

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
        $data['recorded_by'] = $request->user()->id;
        $expense = DB::transaction(function () use ($data) {
            $this->applyCurrencySnapshot($data);

            return Expense::create($data);
        });

        // Return the created expense with 201 status code
        return (new ExpenseResource($expense->load(['expenseCurrency', 'recorder', 'applicant'])))->response()->setStatusCode(201);
    }

    public function show(Expense $expense)
    {
        return new ExpenseResource($expense->load(['expenseCurrency', 'recorder', 'applicant']));
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

        return new ExpenseResource($expense->load(['expenseCurrency', 'recorder', 'applicant']));
    }

    public function destroy(Expense $expense)
    {
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

    public function downloadDocument(Expense $expense)
    {
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
