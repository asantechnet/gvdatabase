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
            'contract_id' => $this->contract_id,
            'contract_number' => $this->whenLoaded('contract', fn () => $this->contract?->contract_number),
            'class_id' => $this->academy_class_id,
            'class_name' => $this->whenLoaded('academyClass', fn () => $this->academyClass?->name),
            // Commission is earned by the case, so the ledger names it and the
            // stage it sits at: a case that left the completed stage explains
            // why its accruals were voided.
            'case_id' => $this->whenLoaded('contract', fn () => $this->contract?->applicant_id),
            'case_name' => $this->whenLoaded('contract', fn () => $this->contract?->applicant?->name),
            'case_stage_key' => $this->whenLoaded('contract', fn () => $this->contract?->applicant?->currentStage?->key),
            'case_stage_name' => $this->whenLoaded('contract', fn () => $this->contract?->applicant?->currentStage?->name),
            'applicant_contract_id' => $this->applicant_contract_id,
            'employee_id' => $this->employee_id,
            'employee' => $this->employee?->name,
            'employee_avatar' => $this->employee?->avatar,
            'service' => $this->service,
            'base_currency_id' => $this->base_currency_id,
            'base_currency' => $this->baseCurrency ? [
                'id' => $this->baseCurrency->id,
                'code' => $this->baseCurrency->code,
                'symbol' => $this->baseCurrency->symbol,
                'decimal_places' => $this->baseCurrency->decimal_places,
            ] : null,
            'earned_at' => $this->earned_at?->toDateString(),
            'entry_type' => $this->entry_type,
            'adjustment_reason' => $this->adjustment_reason,
            'source_base_amount' => (float) $this->source_base_amount,
            'eligible_base_amount' => (float) $this->eligible_base_amount,
            'percentage' => (float) $this->percentage,
            'commission_base_amount' => (float) $this->commission_base_amount,
            'status' => $this->status,
            'settlement_id' => $this->commission_settlement_id,
        ];
    }
}
