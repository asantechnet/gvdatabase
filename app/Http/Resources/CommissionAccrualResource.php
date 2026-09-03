<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionAccrualResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee?->name),
            'service_id' => $this->service_id,
            'service' => $this->whenLoaded('service', fn () => $this->service?->type),
            'customer_service_id' => $this->customer_service_id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->invoice_number),
            'income_id' => $this->income_id,
            'receipt_number' => $this->whenLoaded('income', fn () => $this->income?->receipt_number),
            'base_currency_id' => $this->base_currency_id,
            'base_currency' => $this->whenLoaded('baseCurrency', fn () => $this->baseCurrency ? [
                'id' => $this->baseCurrency->id, 'code' => $this->baseCurrency->code,
                'symbol' => $this->baseCurrency->symbol, 'decimal_places' => $this->baseCurrency->decimal_places,
            ] : null),
            'earned_at' => $this->earned_at?->toDateString(),
            'entry_type' => $this->entry_type,
            'adjustment_reason' => $this->adjustment_reason,
            'eligible_base_amount' => (float) $this->eligible_base_amount,
            'percentage' => (float) $this->percentage,
            'commission_base_amount' => (float) $this->commission_base_amount,
            'status' => $this->status,
            'settlement_id' => $this->commission_settlement_id,
            'recorded_by' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
        ];
    }
}
