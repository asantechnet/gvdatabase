<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Income;
use App\Models\Invoice;
use App\Services\CurrencyConversionService;
use App\Http\Resources\IncomeResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class IncomeController extends Controller
{
    public function __construct(private readonly CurrencyConversionService $currencies)
    {
    }

    public function index(Request $request)
    {
        $query = Income::with(['invoice', 'incomeCurrency', 'recorder']);

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->integer('currency_id'));
        }

        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('description', 'like', '%'.$request->search.'%')
                  ->orWhere('type', 'like', '%'.$request->search.'%');
            });
        }

        $incomes = $query->latest()->get();

        return IncomeResource::collection($incomes);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'type' => ['required', Rule::in(['Service', 'Product', 'Investment', 'Other'])],
            'description' => 'nullable|string|max:65535',
            'amount' => 'required|numeric|gt:0',
            'quantity' => 'required|integer|min:1',
            'currency_id' => 'required|exists:currencies,id',
            'rate' => 'nullable|numeric|gt:0',
            'date' => 'required|date',
        ]);

        $validator->validate();

        $data = $validator->validated();
        $data['recorded_by'] = $request->user()->id;
        $data['description'] = $data['description'] ?? '';
        $this->applyCurrencySnapshot($data);

        $income = DB::transaction(function () use ($data) {
            if (empty($data['invoice_id'])) {
                return Income::create($data);
            }

            $invoice = Invoice::whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail();
            $data['customer_id'] = $invoice->customer_id;
            $data['currency_id'] = $invoice->currency_id;
            $this->applyCurrencySnapshot($data);
            $data['invoice_amount'] = $this->paymentAmountInInvoiceCurrency($data, $invoice);
            $this->ensureInvoiceCanAcceptPayment($invoice, $data);

            $income = Income::create($data);
            $this->refreshInvoicePaymentStatus($invoice);

            return $income;
        });

        return new IncomeResource($income->load(['invoice', 'incomeCurrency', 'recorder']));
    }

    public function show(Income $income)
    {
        return new IncomeResource($income->load(['invoice', 'incomeCurrency', 'recorder']));
    }

    public function update(Request $request, Income $income)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'type' => ['sometimes', Rule::in(['Service', 'Product', 'Investment', 'Other'])],
            'description' => 'sometimes|nullable|string|max:65535',
            'amount' => 'sometimes|numeric|gt:0',
            'quantity' => 'sometimes|integer|min:1',
            'currency_id' => 'sometimes|exists:currencies,id',
            'rate' => 'nullable|numeric|gt:0',
            'date' => 'sometimes|date',
        ]);

        $validator->validate();

        $data = $validator->validated();
        $income = DB::transaction(function () use ($data, $income) {
            $invoiceId = array_key_exists('invoice_id', $data) ? $data['invoice_id'] : $income->invoice_id;
            $affectedInvoiceIds = array_filter([$income->invoice_id, $invoiceId]);
            $invoices = Invoice::whereIn('id', $affectedInvoiceIds)->lockForUpdate()->get()->keyBy('id');

            if ($invoiceId) {
                $invoice = $invoices->get($invoiceId);
                if (!$invoice) {
                    throw ValidationException::withMessages(['invoice_id' => 'business.invoice_not_found']);
                }

                $data['customer_id'] = $invoice->customer_id;
                $data['currency_id'] = $invoice->currency_id;
                if ((int) $invoiceId !== (int) $income->invoice_id || array_key_exists('rate', $data)) {
                    $this->applyCurrencySnapshot($data);
                }
                $data['invoice_amount'] = $this->paymentAmountInInvoiceCurrency(array_merge($income->toArray(), $data), $invoice);
                $this->ensureInvoiceCanAcceptPayment($invoice, array_merge($income->toArray(), $data), $income->id);
            } else {
                if (array_key_exists('currency_id', $data)) {
                    $this->applyCurrencySnapshot($data);
                }
                if (array_key_exists('invoice_id', $data)) {
                    $data['invoice_amount'] = null;
                    $data['customer_id'] = $data['customer_id'] ?? $income->customer_id;
                }
            }

            $income->update($data);

            foreach ($invoices as $invoice) {
                $this->refreshInvoicePaymentStatus($invoice);
            }

            return $income;
        });

        return new IncomeResource($income->load(['invoice', 'incomeCurrency', 'recorder']));
    }

    public function destroy(Income $income)
    {
        DB::transaction(function () use ($income) {
            $invoice = $income->invoice_id
                ? Invoice::whereKey($income->invoice_id)->lockForUpdate()->first()
                : null;

            $income->delete();

            if ($invoice) {
                $this->refreshInvoicePaymentStatus($invoice);
            }
        });

        return response()->noContent();
    }

    /** @param array<string, mixed> $payment */
    private function ensureInvoiceCanAcceptPayment(Invoice $invoice, array $payment, ?int $excludingIncomeId = null): void
    {
        if ($invoice->status === 'Void' || ($invoice->status === 'Paid' && !$excludingIncomeId)) {
            throw ValidationException::withMessages([
                'invoice_id' => 'business.invoice_not_payable',
            ]);
        }

        $paidAmount = $this->invoicePaidAmount($invoice, $excludingIncomeId);
        $paymentAmount = $this->paymentAmountInInvoiceCurrency($payment, $invoice);
        $remainingAmount = round(max(0, (float) $invoice->amount - $paidAmount), $this->currencyPrecision($invoice));

        if ($paymentAmount > $remainingAmount + 0.00001) {
            throw ValidationException::withMessages([
                'amount' => 'business.invoice_payment_exceeds_balance',
            ]);
        }
    }

    private function refreshInvoicePaymentStatus(Invoice $invoice): void
    {
        if ($invoice->status === 'Void') {
            return;
        }

        $paidAmount = $this->invoicePaidAmount($invoice);
        $precision = $this->currencyPrecision($invoice);
        $status = $paidAmount >= (float) $invoice->amount - (10 ** -$precision) / 2
            ? 'Paid'
            : ($paidAmount > 0 ? 'Partially Paid' : 'Issued');

        $invoice->update(['status' => $status]);
    }

    private function invoicePaidAmount(Invoice $invoice, ?int $excludingIncomeId = null): float
    {
        $query = $invoice->incomes();

        if ($excludingIncomeId) {
            $query->whereKeyNot($excludingIncomeId);
        }

        return round($query->get()->sum(fn (Income $income) =>
            (float) ($income->invoice_amount ?? ((float) $income->amount * (int) $income->quantity))
        ), $this->currencyPrecision($invoice));
    }

    /** @param array<string, mixed> $payment */
    private function paymentAmountInInvoiceCurrency(array $payment, Invoice $invoice): float
    {
        // Linked payments are deliberately recorded in the invoice currency.
        // This makes the paid balance independent of later base-currency changes.
        return round((float) ($payment['amount'] ?? 0) * (int) ($payment['quantity'] ?? 1), $this->currencyPrecision($invoice));
    }

    /** @param array<string, mixed> $data */
    private function currencyPrecision(Invoice $invoice): int
    {
        return max(0, min(8, (int) ($invoice->currency?->decimal_places ?? 2)));
    }

    private function applyCurrencySnapshot(array &$data): void
    {
        $receiptRate = $data['rate'] ?? null;
        $currency = Currency::query()->where('is_active', true)->findOrFail($data['currency_id']);
        $data = [...$data, ...$this->currencies->snapshot($currency)];
        if (array_key_exists('amount', $data)) {
            $data['amount'] = round((float) $data['amount'], $currency->decimal_places);
        }

        // Preserve the exchange rate agreed for this particular receipt.
        if ($receiptRate !== null) {
            $data['rate'] = (float) $receiptRate;
        }
    }
}
