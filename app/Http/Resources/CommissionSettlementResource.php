<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionSettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'settlement_number' => $this->settlement_number,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee?->name),
            'base_currency_id' => $this->base_currency_id,
            'base_currency' => $this->whenLoaded('baseCurrency', fn () => $this->baseCurrency ? [
                'id' => $this->baseCurrency->id, 'code' => $this->baseCurrency->code,
                'symbol' => $this->baseCurrency->symbol, 'decimal_places' => $this->baseCurrency->decimal_places,
            ] : null),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'gross_base_amount' => (float) $this->gross_base_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'accrual_count' => $this->whenCounted('accruals'),
            'accruals' => CommissionAccrualResource::collection($this->whenLoaded('accruals')),
            'approved_at' => $this->approved_at?->toISOString(),
            'payment_date' => $this->payment_date?->toDateString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'expense_id' => $this->expense_id,
            'recorded_by' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
            'approved_by' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'paid_by' => $this->whenLoaded('payer', fn () => $this->payer?->name),
        ];
    }
}
