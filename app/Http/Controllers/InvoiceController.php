<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\ContractService;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\PaymentDetail;
use App\Services\CurrencyConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(private readonly CurrencyConversionService $currencies) {}

    public function index(Request $request)
    {
        $query = Invoice::with(['customer', 'currency', 'baseCurrency', 'services', 'incomes', 'recorder']);

        $query->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id'))
        );

        $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status'))
        );

        $query->when($request->boolean('unpaid'), fn ($query) => $query->where('status', '!=', 'Paid')->where('status', '!=', 'Void')
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

    public function commissionCandidates()
    {
        // Candidates are derived from contracted-service delivery assignments
        // on active contracts plus the employee's catalogue capability.
        $assignments = ContractService::query()
            ->whereHas('contract', fn ($contracts) => $contracts->where('status', 'active')->where('is_cancelled', false))
            ->whereHas('deliveryEmployees')
            ->with(['deliveryEmployees.commissionServices', 'contract:id,customer_id'])
            ->get()
            ->map(function (ContractService $line): ?array {
                return $line->deliveryEmployees->map(function ($employee) use ($line): ?array {
                    $capability = $employee->commissionServices->firstWhere('id', $line->service_id);

                    return $employee->employment_type === 'commission' && $capability ? [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'contract_service_id' => $line->id,
                        'percentage' => (float) $capability->pivot->percentage,
                    ] : null;
                });
            })
            ->flatten(1)
            ->filter()
            ->groupBy('employee_id')
            ->map(fn ($rows) => [
                'id' => $rows->first()['employee_id'],
                'name' => $rows->first()['employee_name'],
                'contract_services' => $rows->map(fn (array $row) => [
                    'contract_service_id' => $row['contract_service_id'],
                    'percentage' => $row['percentage'],
                ])->values(),
            ])
            ->values();

        return response()->json(['data' => $assignments]);
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
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.contract_service_id' => ['required', 'integer', 'distinct', 'exists:contract_service,id'],
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

        unset($validated['rate']);
        $validated['issue_date'] ??= today()->toDateString();
        $validated['due_date'] ??= today()->addDays(30)->toDateString();
        if ($validated['due_date'] < $validated['issue_date']) {
            throw ValidationException::withMessages([
                'due_date' => ['business.invoice_due_before_issue'],
            ]);
        }

        $recordedBy = $request->user()->id;
        $invoice = DB::transaction(function () use ($validated, $recordedBy) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($validated['customer_id']);
            $invoiceCurrency = Currency::findOrFail($validated['currency_id']);
            $serviceRows = collect($validated['services']);
            $requestedIds = $serviceRows->pluck('contract_service_id');
            $assignedServices = ContractService::query()
                ->whereIn('id', $requestedIds)
                ->whereHas('contract', fn ($contracts) => $contracts
                    ->where('customer_id', $customer->id)
                    ->where('status', 'active')
                    ->where('is_cancelled', false))
                ->with(['service.currency', 'currency', 'deliveryEmployees.commissionServices', 'marketer.commissionServices', 'outsourcingCompany'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($requestedIds->contains(fn ($id) => ! $assignedServices->has($id))) {
                throw ValidationException::withMessages([
                    'services' => 'business.invoice_service_not_assigned',
                ]);
            }

            $baseCurrency = $this->currencies->currentBase();
            $invoiceRate = $this->currencies->rate($invoiceCurrency);

            $pivotRows = [];
            $deliveryRecipientRows = [];
            $subtotal = 0.0;

            foreach ($serviceRows as $row) {
                $contractService = $assignedServices->get($row['contract_service_id']);
                $service = $contractService->service;
                $sourceCurrency = $contractService->currency ?? $service->currency;
                $contractedFee = (float) ($contractService->fee ?? $service->fee);
                $commissionableInvoice = true;
                $deliveryRecipients = [];
                $marketingEmployee = null;
                $marketingPercentage = null;
                $outsourcingCompanyId = null;
                $outsourcingPercentage = null;

                // A contracted service can credit several delivery employees.
                // Their catalogue capability supplies the rate; each resolved
                // rate is snapshotted independently on the invoice line.
                $deliveryEmployees = $contractService->deliveryEmployees;

                foreach ($deliveryEmployees as $deliveryEmployee) {
                    $capability = $deliveryEmployee->commissionServices
                        ->firstWhere('id', $service->id);

                    if ($deliveryEmployee->employment_type !== 'commission' || ! $capability) {
                        throw ValidationException::withMessages([
                            'services' => 'business.customer_service_delivery_employee_ineligible',
                        ]);
                    }

                    $deliveryRecipients[$deliveryEmployee->id] = (float) $capability->pivot->percentage;
                }

                // Acquisition credit is independent from delivery. A marketer
                // earns only when they have explicitly been made a
                // commission-based employee and have a rate for this service.
                // This prevents mere CRM attribution from becoming payroll.
                if ($commissionableInvoice && $contractService->marketer) {
                    $marketer = $contractService->marketer;
                    $capability = $marketer->commissionServices->firstWhere('id', $service->id);

                    if ($marketer->employment_type === 'commission' && $capability) {
                        $marketingEmployee = $marketer;
                        $marketingPercentage = (float) $capability->pivot->percentage;
                    }
                }

                // Outsourcing is contractual supplier work, independent from
                // employee payroll. Snapshot both the provider and its agreed
                // share now so later agreement changes cannot alter invoices.
                if ($contractService->outsourcingCompany && $contractService->outsourcing_percentage !== null) {
                    $outsourcingCompanyId = $contractService->outsourcingCompany->id;
                    $outsourcingPercentage = (float) $contractService->outsourcing_percentage;
                }

                if (! $sourceCurrency || (float) $sourceCurrency->rate <= 0) {
                    throw ValidationException::withMessages([
                        'services' => 'business.service_currency_rate_invalid',
                    ]);
                }

                $quantity = $row['quantity'] ?? 1;
                $convertedFee = round(
                    $this->currencies->convert($contractedFee, $sourceCurrency, $invoiceCurrency, $invoiceCurrency->decimal_places),
                    $invoiceCurrency->decimal_places
                );
                $lineTotal = round($convertedFee * $quantity, $invoiceCurrency->decimal_places);
                $subtotal += $lineTotal;

                $pivotRows[$service->id] = [
                    'contract_service_id' => $contractService->id,
                    'snapshot_name' => $service->type,
                    'snapshot_service_fee' => $contractedFee,
                    'snapshot_service_currency_code' => $sourceCurrency->code,
                    'snapshot_service_currency_symbol' => $sourceCurrency->symbol,
                    'snapshot_service_rate' => $this->currencies->rate($sourceCurrency),
                    'snapshot_cnv_curr_shrt' => $invoiceCurrency->code,
                    'snapshot_base_curr_shrt' => $baseCurrency->code,
                    'snapshot_rate' => $invoiceRate,
                    'snapshot_invoice_rate' => $invoiceRate,
                    'snapshot_converted_fee' => $convertedFee,
                    // Legacy one-recipient fields retain the first recipient
                    // for older integrations. Recipient rows below are the
                    // authoritative immutable invoice snapshot.
                    'employee_id' => array_key_first($deliveryRecipients),
                    'marketer_employee_id' => $marketingEmployee?->id,
                    'outsourcing_company_id' => $outsourcingCompanyId,
                    'snapshot_commission_percentage' => $deliveryRecipients[array_key_first($deliveryRecipients)] ?? null,
                    'snapshot_marketer_commission_percentage' => $marketingPercentage,
                    'snapshot_outsourcing_percentage' => $outsourcingPercentage,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
                $deliveryRecipientRows[$contractService->id] = $deliveryRecipients;
            }

            $subtotal = round($subtotal, $invoiceCurrency->decimal_places);
            $taxAmount = round($subtotal * ((float) $validated['tax_percent'] / 100), $invoiceCurrency->decimal_places);
            $discount = round((float) ($validated['discount'] ?? 0), $invoiceCurrency->decimal_places);
            if ($discount > $subtotal + $taxAmount) {
                throw ValidationException::withMessages([
                    'discount' => 'business.invoice_discount_exceeds_total',
                ]);
            }
            $amount = round($subtotal + $taxAmount - $discount, $invoiceCurrency->decimal_places);
            $paymentDetail = isset($validated['payment_detail_id'])
                ? PaymentDetail::query()->lockForUpdate()->findOrFail($validated['payment_detail_id'])
                : null;

            $invoice = Invoice::create([
                'recorded_by' => $recordedBy,
                'customer_id' => $customer->id,
                'currency_id' => $invoiceCurrency->id,
                'deptor' => $customer->name,
                'customer_company' => $customer->company,
                'customer_email' => $customer->email,
                'customer_contact' => $customer->contact,
                'customer_address' => $customer->address,
                'customer_tax_number' => $customer->tax_number,
                'status' => 'Issued',
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'tax_percent' => $validated['tax_percent'],
                'currency_name' => $invoiceCurrency->name,
                'cnv_curr_shrt' => $invoiceCurrency->code,
                'base_curr_shrt' => $baseCurrency->code,
                'base_currency_id' => $baseCurrency->id,
                'rate' => $invoiceRate,
                'base_amount' => $this->currencies->toCurrentBase($amount, $invoiceCurrency),
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

            InvoiceService::query()->where('invoice_id', $invoice->id)->get()->each(function (InvoiceService $line) use ($deliveryRecipientRows): void {
                $line->commissionRecipients()->sync(
                    collect($deliveryRecipientRows[$line->contract_service_id] ?? [])
                        ->mapWithKeys(fn (float $percentage, int $employeeId) => [$employeeId => ['percentage' => $percentage]])
                        ->all(),
                );
            });

            return $invoice;
        });

        return (new InvoiceResource($invoice->load(['customer', 'currency', 'baseCurrency', 'services', 'incomes', 'recorder'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice)
    {
        return new InvoiceResource($invoice->load(['customer', 'currency', 'baseCurrency', 'services', 'incomes', 'recorder']));
    }

    public function update()
    {
        return response()->json([
            'message' => 'Issued invoices cannot be edited by users.',
        ], 405);
    }

    public function void(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $invoice = DB::transaction(function () use ($invoice, $validated) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            $invoice->assignDocumentNumber();

            if ($invoice->voided_at) {
                throw ValidationException::withMessages([
                    'invoice' => 'business.invoice_already_void',
                ]);
            }

            if ($invoice->incomes()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'business.invoice_with_payments_cannot_be_voided',
                ]);
            }

            $invoice->update([
                'status' => 'Void',
                'voided_at' => now(),
                'void_reason' => $validated['reason'],
            ]);

            return $invoice;
        });

        return new InvoiceResource($invoice->load(['customer', 'currency', 'baseCurrency', 'services', 'incomes', 'recorder']));
    }

    public function markPaid(Invoice $invoice)
    {
        $invoice = DB::transaction(function () use ($invoice) {
            $invoice = Invoice::with(['currency', 'incomes'])->lockForUpdate()->findOrFail($invoice->id);
            $precision = max(0, min(8, (int) ($invoice->currency?->decimal_places ?? 2)));
            $paid = round($invoice->incomes->sum(fn ($income) => (float) ($income->invoice_amount ?? ((float) $income->amount * (int) $income->quantity))), $precision);
            $balance = max(0, round((float) $invoice->amount - $paid, $precision));

            $tolerance = (10 ** -$precision) / 2;
            if ($invoice->status === 'Void' || $invoice->status === 'Paid' || $paid <= 0 || $balance > $tolerance) {
                throw ValidationException::withMessages(['invoice' => 'business.invoice_balance_not_eligible_for_paid']);
            }

            $invoice->update(['status' => 'Paid']);

            return $invoice;
        });

        return new InvoiceResource($invoice->load(['customer', 'currency', 'baseCurrency', 'services', 'incomes', 'recorder']));
    }

    public function destroy()
    {
        return response()->json([
            'message' => 'Invoices are permanent records. Void the invoice instead.',
        ], 405);
    }
}
