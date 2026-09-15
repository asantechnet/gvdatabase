<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsCurrentBaseMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    use PresentsCurrentBaseMoney;

    public function toArray(Request $request): array
    {
        $paidAmount = $this->incomes->sum(fn ($income) =>
            (float) ($income->invoice_amount ?? ((float) $income->amount * (int) $income->quantity))
        );
        $currentCurrency = $this->resource->currency;
        // Older installations can have issued invoices with a base-currency
        // snapshot but no currently configured base currency. The issued
        // snapshot is the only reliable display currency in that situation.
        $baseCurrency = $this->currentBaseCurrency() ?? $this->resource->baseCurrency;
        $precision = max(0, min(8, (int) ($currentCurrency?->decimal_places ?? 2)));
        $balance = max(0, round((float) $this->amount - $paidAmount, $precision));
        $canMarkPaid = $this->status !== 'Paid'
            && $this->status !== 'Void'
            && $paidAmount > 0
            && $balance > 0
            && $balance < (float) $this->amount * 0.05;

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer_id' => $this->customer_id,
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->deptor,
                'company' => $this->customer?->company,
                'contact' => $this->customer?->contact,
                'email' => $this->customer_email,
                'address' => $this->customer_address,
                'tax_number' => $this->customer_tax_number,
            ],
            'deptor' => $this->deptor,
            'status' => $this->status,
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'tax_percent' => (float) $this->tax_percent,
            'currency' => [
                'id' => $this->currency_id,
                'name' => $this->currency_name,
                'rate' => (float) $this->rate,
                'symbol' => $this->currency_symbol,
                'shortname' => $this->currency_shortname,
                'decimalPlaces' => $precision,
            ],
            'subtotal' => (float) $this->subtotal,
            'amount' => (float) $this->amount,
            'total_amount' => (float) $this->amount,
            'paid_amount' => round($paidAmount, $precision),
            'balance' => $balance,
            'can_mark_paid' => $canMarkPaid,
            'mark_paid_threshold' => round((float) $this->amount * 0.05, $precision),
            'currentBaseAmount' => $baseCurrency
                ? ($this->currentBaseCurrency() ? $this->currentBaseAmount((float) $this->amount, $currentCurrency) : (float) $this->base_amount)
                : null,
            'currentBasePaidAmount' => $baseCurrency
                ? ($this->currentBaseCurrency() ? $this->currentBaseAmount((float) $paidAmount, $currentCurrency) : round((float) $this->base_amount * ($paidAmount / max((float) $this->amount, 1)), $baseCurrency->decimal_places))
                : null,
            'currentBaseBalance' => $baseCurrency
                ? ($this->currentBaseCurrency() ? $this->currentBaseAmount($balance, $currentCurrency) : round((float) $this->base_amount * ($balance / max((float) $this->amount, 1)), $baseCurrency->decimal_places))
                : null,
            'currentBaseCurrency' => $baseCurrency ? [
                'id' => $baseCurrency->id,
                'code' => $baseCurrency->code,
                'symbol' => $baseCurrency->symbol,
                'decimalPlaces' => $baseCurrency->decimal_places,
            ] : null,
            'discount' => (float) $this->discount,
            'notes' => $this->notes,
            'payment_detail_id' => $this->payment_detail_id,
            'payment_method' => $this->payment_method,
            'payment_instructions' => $this->payment_instructions ?? [],
            'recorded_by' => $this->recorded_by,
            'recordedBy' => $this->whenLoaded('recorder', fn () => $this->recorder ? ['id' => $this->recorder->id, 'name' => $this->recorder->name] : null),
            'services' => $this->services->map(fn ($service) => [
                'id' => $service->id,
                'customer_service_id' => $service->pivot->customer_service_id,
                'name' => $service->pivot->snapshot_name,
                'price' => (float) $service->pivot->snapshot_converted_fee,
                'quantity' => $service->pivot->quantity,
                'line_total' => (float) $service->pivot->line_total,
                'commission_employee_id' => $service->pivot->employee_id,
                'commission_percentage' => $service->pivot->snapshot_commission_percentage !== null
                    ? (float) $service->pivot->snapshot_commission_percentage : null,
                'commission_recipients' => $service->pivot->commissionRecipients->map(fn ($employee) => [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'percentage' => (float) $employee->pivot->percentage,
                ])->values(),
                'source_fee' => (float) $service->pivot->snapshot_service_fee,
                'source_currency' => [
                    'code' => $service->pivot->snapshot_service_currency_code,
                    'symbol' => $service->pivot->snapshot_service_currency_symbol,
                    'rate' => (float) ($service->pivot->snapshot_service_rate ?? $service->pivot->snapshot_rate),
                ],
                'currency' => [
                    'id' => $this->currency_id,
                    'name' => $this->currency_name,
                    'rate' => (float) ($service->pivot->snapshot_invoice_rate ?? $service->pivot->snapshot_rate),
                    'symbol' => $this->currency_symbol,
                    'shortname' => $this->currency_shortname,
                    'decimalPlaces' => $precision,
                ],
                'subDescription' => '',
            ]),
            'voided_at' => $this->voided_at,
            'void_reason' => $this->void_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
