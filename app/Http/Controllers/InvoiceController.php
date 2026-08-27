<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $this->repairMissingInvoiceNumbers();

        $query = Invoice::with(['customer', 'currency', 'services', 'incomes']);

        $query->when($request->filled('customer_id'), fn ($query) =>
            $query->where('customer_id', $request->integer('customer_id'))
        );

        $query->when($request->filled('status'), fn ($query) =>
            $query->where('status', $request->string('status'))
        );

        $query->when($request->boolean('unpaid'), fn ($query) =>
            $query->where('status', '!=', 'Paid')->where('status', '!=', 'Void')
        );

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($query) use ($search) {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('deptor', 'like', "%{$search}%");
            });
        }

        return InvoiceResource::collection($query->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('is_active', true),
            ],
            'rate' => ['required', 'numeric', 'gt:0'],
            'class_type' => ['required', Rule::in(['Standard', 'Proforma', 'Credit Note', 'Debit Note'])],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'services.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'payment_detail_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_details', 'id')->whereNull('deleted_at'),
            ],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'payment_instructions' => ['nullable', 'array'],
            'payment_instructions.*' => ['string'],
        ]);

        $invoice = DB::transaction(function () use ($validated) {
            $customer = Customer::with('customerServices.service.currency')
                ->findOrFail($validated['customer_id']);
            $invoiceCurrency = Currency::findOrFail($validated['currency_id']);
            $assignedServices = $customer->customerServices
                ->keyBy('service_id');
            $serviceRows = collect($validated['services']);
            $requestedIds = $serviceRows->pluck('id');

            if ($requestedIds->contains(fn ($id) => !$assignedServices->has($id))) {
                throw ValidationException::withMessages([
                    'services' => 'business.invoice_service_not_assigned',
                ]);
            }

            $baseCurrency = Currency::query()->where('is_base', true)->firstOrFail();

            $pivotRows = [];
            $subtotal = 0.0;

            foreach ($serviceRows as $row) {
                $service = $assignedServices->get($row['id'])->service;
                $sourceCurrency = $service->currency;

                if (!$sourceCurrency || (float) $sourceCurrency->rate <= 0) {
                    throw ValidationException::withMessages([
                        'services' => 'business.service_currency_rate_invalid',
                    ]);
                }

                $quantity = $row['quantity'] ?? 1;
                $convertedFee = round(
                    ((float) $service->fee / (float) $sourceCurrency->rate)
                    * (float) $validated['rate'],
                    $invoiceCurrency->decimal_places
                );
                $lineTotal = round($convertedFee * $quantity, 2);
                $subtotal += $lineTotal;

                $pivotRows[$service->id] = [
                    'snapshot_name' => $service->type,
                    'snapshot_service_fee' => $service->fee,
                    'snapshot_service_currency_code' => $sourceCurrency->code,
                    'snapshot_service_currency_symbol' => $sourceCurrency->symbol,
                    'snapshot_cnv_curr_shrt' => $invoiceCurrency->code,
                    'snapshot_base_curr_shrt' => $baseCurrency->code,
                    'snapshot_rate' => $validated['rate'],
                    'snapshot_converted_fee' => $convertedFee,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $taxAmount = round($subtotal * ((float) $validated['tax_percent'] / 100), 2);
            $discount = round((float) ($validated['discount'] ?? 0), 2);
            $amount = max(0, round($subtotal + $taxAmount - $discount, 2));
            $paymentDetail = isset($validated['payment_detail_id'])
                ? PaymentDetail::findOrFail($validated['payment_detail_id'])
                : null;

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'currency_id' => $invoiceCurrency->id,
                'class_type' => $validated['class_type'],
                'deptor' => $customer->name,
                'customer_company' => $customer->company,
                'customer_email' => $customer->email,
                'customer_contact' => $customer->contact,
                'customer_address' => $customer->address,
                'customer_tax_number' => $customer->tax_number,
                'status' => 'Issued',
                'issue_date' => $validated['issue_date'] ?? now()->toDateString(),
                'due_date' => $validated['due_date'] ?? now()->addDays(30)->toDateString(),
                'tax_percent' => $validated['tax_percent'],
                'currency_name' => $invoiceCurrency->name,
                'cnv_curr_shrt' => $invoiceCurrency->code,
                'base_curr_shrt' => $baseCurrency->code,
                'rate' => $validated['rate'],
                'base_amount' => round($amount * (float) $validated['rate'], 4),
                'currency_symbol' => $invoiceCurrency->symbol,
                'currency_shortname' => $invoiceCurrency->code,
                'subtotal' => $subtotal,
                'amount' => $amount,
                'discount' => $discount,
                'notes' => $validated['notes'] ?? null,
                'payment_detail_id' => $paymentDetail?->id,
                'payment_method' => $paymentDetail?->method ?? ($validated['payment_method'] ?? null),
                'payment_instructions' => $paymentDetail
                    ? preg_split('/\R/u', $paymentDetail->instruction, -1, PREG_SPLIT_NO_EMPTY)
                    : ($validated['payment_instructions'] ?? []),
            ]);

            $invoice->assignDocumentNumber();

            $invoice->services()->attach($pivotRows);

            return $invoice;
        });

        return (new InvoiceResource($invoice->load(['customer', 'currency', 'services', 'incomes'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice)
    {
        $invoice->assignDocumentNumber();

        return new InvoiceResource($invoice->load(['customer', 'currency', 'services', 'incomes']));
    }

    public function update()
    {
        return response()->json([
            'message' => 'Issued invoices cannot be edited by users.',
        ], 405);
    }

    public function void(Request $request, Invoice $invoice)
    {
        $invoice->assignDocumentNumber();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        if ($invoice->voided_at) {
            throw ValidationException::withMessages([
                'invoice' => 'business.invoice_already_void',
            ]);
        }

        $invoice->update([
            'status' => 'Void',
            'voided_at' => now(),
            'void_reason' => $validated['reason'],
        ]);

        return new InvoiceResource($invoice->load(['customer', 'currency', 'services', 'incomes']));
    }

    public function destroy()
    {
        return response()->json([
            'message' => 'Invoices are permanent records. Void the invoice instead.',
        ], 405);
    }

    private function repairMissingInvoiceNumbers(): void
    {
        Invoice::whereNull('invoice_number')
            ->orderBy('id')
            ->each(fn (Invoice $invoice) => $invoice->assignDocumentNumber());
    }
}
