<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExpenseResource;
use App\Models\CommissionSettlement;
use App\Models\OutsourcingSettlement;
use App\Models\Currency;
use App\Models\Expense;
use App\Services\CurrencyConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function __construct(private readonly CurrencyConversionService $currencies) {}

    public function index(Request $request)
    {
        $query = Expense::with(['expenseCurrency', 'company', 'project', 'recorder']);

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->integer('currency_id'));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', '%'.$request->search.'%')
                    ->orWhere('type', 'like', '%'.$request->search.'%');
            });
        }

        // Ensure we're getting all records with proper ordering
        $expenses = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();

        return ExpenseResource::collection($expenses);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'company_id' => 'nullable|exists:companies,id',
            'project_id' => 'nullable|exists:projects,id',
            'type' => 'required|string|max:255',
            'description' => 'required|string',
            'amount' => 'required|numeric|gt:0',
            'quantity' => 'required|integer|min:1',
            'currency_id' => 'required|exists:currencies,id',
            'rate' => 'nullable|numeric|gt:0',
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $validator->validate();

        $data = $validator->validated();
        $data['recorded_by'] = $request->user()->id;
        $this->applyCurrencySnapshot($data);

        $expense = Expense::create($data);

        // Return the created expense with 201 status code
        return (new ExpenseResource($expense->load(['expenseCurrency', 'company', 'project', 'recorder'])))->response()->setStatusCode(201);
    }

    public function show(Expense $expense)
    {
        return new ExpenseResource($expense->load(['expenseCurrency', 'company', 'project', 'recorder']));
    }

    public function update(Request $request, Expense $expense)
    {
        if ($expense->salary_id || CommissionSettlement::where('expense_id', $expense->id)->exists() || OutsourcingSettlement::where('expense_id', $expense->id)->exists()) {
            throw ValidationException::withMessages(['expense' => ['business.salary_expense_managed_by_payroll']]);
        }
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'company_id' => 'nullable|exists:companies,id',
            'project_id' => 'nullable|exists:projects,id',
            'type' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'amount' => 'sometimes|numeric|gt:0',
            'quantity' => 'sometimes|integer|min:1',
            'currency_id' => 'sometimes|exists:currencies,id',
            'rate' => 'nullable|numeric|gt:0',
            'date' => ['sometimes', 'date', 'before_or_equal:today'],
        ]);

        $validator->validate();

        $data = $validator->validated();

        if (array_key_exists('currency_id', $data)) {
            $this->applyCurrencySnapshot($data);
        }

        $expense->update($data);

        return new ExpenseResource($expense->load(['expenseCurrency', 'company', 'project', 'recorder']));
    }

    public function destroy(Expense $expense)
    {
        if ($expense->salary_id || CommissionSettlement::where('expense_id', $expense->id)->exists() || OutsourcingSettlement::where('expense_id', $expense->id)->exists()) {
            throw ValidationException::withMessages(['expense' => ['business.salary_expense_managed_by_payroll']]);
        }
        if ($expense->document_path) Storage::disk('local')->delete($expense->document_path);
        $expense->delete();

        return response()->noContent();
    }

    public function uploadDocument(Request $request, Expense $expense)
    {
        $request->validate(['document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:5120']]);
        $file = $request->file('document');
        $path = $file->storeAs("expense-documents/{$expense->id}", Str::uuid().'.'.$file->extension(), 'local');
        if ($expense->document_path) Storage::disk('local')->delete($expense->document_path);
        $expense->update(['document_path' => $path, 'document_name' => $file->getClientOriginalName(), 'document_mime' => $file->getMimeType(), 'document_size' => $file->getSize()]);
        return new ExpenseResource($expense->fresh(['expenseCurrency', 'project', 'recorder']));
    }

    public function downloadDocument(Expense $expense)
    {
        abort_unless($expense->document_path && Storage::disk('local')->exists($expense->document_path), 404);
        return Storage::disk('local')->download($expense->document_path, $expense->document_name ?: 'expense-document');
    }

    public function removeDocument(Expense $expense)
    {
        if ($expense->document_path) Storage::disk('local')->delete($expense->document_path);
        $expense->update(['document_path' => null, 'document_name' => null, 'document_mime' => null, 'document_size' => null]);
        return new ExpenseResource($expense->fresh(['expenseCurrency', 'project', 'recorder']));
    }

    /** @param array<string, mixed> $data */
    private function applyCurrencySnapshot(array &$data): void
    {
        $expenseRate = $data['rate'] ?? null;
        $currency = Currency::query()->where('is_active', true)->findOrFail($data['currency_id']);
        $data = [...$data, ...$this->currencies->snapshot($currency)];

        // The currency supplies a useful default, but each expense may record
        // the exchange rate that actually applied when it was paid.
        if ($expenseRate !== null) {
            $data['rate'] = (float) $expenseRate;
        }
    }
}
