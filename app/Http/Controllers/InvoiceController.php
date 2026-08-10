<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
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
            'class_type' => ['required', Rule::in(['Standard', 'Proforma', 'Credit Note', 'Debit Note'])],
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'services.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
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
                    'services' => 'Every invoiced service must be assigned to the selected customer.',
                ]);
            }

            if ((float) $invoiceCurrency->rate_against_base <= 0) {
                throw ValidationException::withMessages([
                    'currency_id' => 'The invoice currency must have a positive exchange rate.',
                ]);
            }

            $pivotRows = [];
            $subtotal = 0.0;

            foreach ($serviceRows as $row) {
                $service = $assignedServices->get($row['id'])->service;
                $sourceCurrency = $service->currency;

                if (!$sourceCurrency || (float) $sourceCurrency->rate_against_base <= 0) {
                    throw ValidationException::withMessages([
                        'services' => "Service {$service->type} has no valid currency rate.",
                    ]);
                }

                $quantity = $row['quantity'] ?? 1;
                $convertedFee = round(
                    ((float) $service->fee / (float) $sourceCurrency->rate_against_base)
                    * (float) $invoiceCurrency->rate_against_base,
                    $invoiceCurrency->decimal_places
                );
                $lineTotal = round($convertedFee * $quantity, 2);
                $subtotal += $lineTotal;

                $pivotRows[$service->id] = [
                    'snapshot_name' => $service->type,
                    'snapshot_service_fee' => $service->fee,
                    'snapshot_service_currency_code' => $sourceCurrency->code,
                    'snapshot_service_currency_symbol' => $sourceCurrency->symbol,
                    'snapshot_service_currency_rate' => $sourceCurrency->rate_against_base,
                    'snapshot_invoice_currency_rate' => $invoiceCurrency->rate_against_base,
                    'snapshot_converted_fee' => $convertedFee,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $taxAmount = round($subtotal * ((float) $validated['tax_percent'] / 100), 2);
            $discount = round((float) ($validated['discount'] ?? 0), 2);
            $amount = max(0, round($subtotal + $taxAmount - $discount, 2));

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'currency_id' => $invoiceCurrency->id,
                'class_type' => $validated['class_type'],
                'deptor' => $customer->name,
                'status' => 'Issued',
                'tax_percent' => $validated['tax_percent'],
                'currency_name' => $invoiceCurrency->name,
                'currency_rate' => $invoiceCurrency->rate_against_base,
                'currency_symbol' => $invoiceCurrency->symbol,
                'currency_shortname' => $invoiceCurrency->code,
                'subtotal' => $subtotal,
                'amount' => $amount,
                'discount' => $discount,
                'notes' => $validated['notes'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
                'payment_instructions' => $validated['payment_instructions'] ?? [],
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
                'invoice' => 'This invoice is already void.',
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
