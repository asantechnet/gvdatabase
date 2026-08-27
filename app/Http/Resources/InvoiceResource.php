<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paidAmount = $this->incomes->sum(function ($income) {
            if ((float) $income->rate <= 0) {
                return 0;
            }

            return ((float) $income->base_amount / (float) $this->rate);
        });

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
            'class-type' => $this->class_type,
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
            ],
            'subtotal' => (float) $this->subtotal,
            'amount' => (float) $this->amount,
            'total_amount' => (float) $this->amount,
            'paid_amount' => round($paidAmount, 2),
            'balance' => max(0, round((float) $this->amount - $paidAmount, 2)),
            'discount' => (float) $this->discount,
            'notes' => $this->notes,
            'payment_detail_id' => $this->payment_detail_id,
            'payment_method' => $this->payment_method,
            'payment_instructions' => $this->payment_instructions ?? [],
            'services' => $this->services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->pivot->snapshot_name,
                'price' => (float) $service->pivot->snapshot_converted_fee,
                'quantity' => $service->pivot->quantity,
                'line_total' => (float) $service->pivot->line_total,
                'source_fee' => (float) $service->pivot->snapshot_service_fee,
                'source_currency' => [
                    'code' => $service->pivot->snapshot_service_currency_code,
                    'symbol' => $service->pivot->snapshot_service_currency_symbol,
                    'rate' => (float) $service->pivot->snapshot_rate,
                ],
                'currency' => [
                    'id' => $this->currency_id,
                    'name' => $this->currency_name,
                    'rate' => (float) $service->pivot->snapshot_rate,
                    'symbol' => $this->currency_symbol,
                    'shortname' => $this->currency_shortname,
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
