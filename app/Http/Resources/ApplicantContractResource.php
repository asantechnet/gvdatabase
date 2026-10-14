<?php

namespace App\Http\Resources;

use App\Support\BuiltInService;
use App\Support\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicantContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'built_in_service' => $this->serviceKey(),
            'service_label' => $this->serviceLabel(),
            'service_label_key' => BuiltInService::labelKey($this->serviceKey()),
            'application_fee' => $this->application_fee,
            'visa_fee' => $this->visa_fee,
            'booking_fee' => $this->booking_fee,
            'service_fee' => $this->service_fee,
            'remote_fee' => $this->remote_fee,
            'fee' => $this->fee,
            'discount' => $this->discount,
            'currency_rate' => $this->currency_rate,
            'payable_total' => $this->payableTotal(),
            'amount_paid' => $this->amount_paid,
            'remaining_total' => $this->remainingTotal(),
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->paymentStatusLabel(),
            'payment_status_label_key' => PaymentStatus::labelKey($this->payment_status),
            'payment_status_overridden' => (bool) $this->payment_status_overridden,
            'amount_paid_overridden' => (bool) $this->amount_paid_overridden,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency'),
            'applicant_id' => $this->applicant_id,
            'current_stage_id' => $this->current_stage_id,
            'current_stage' => $this->whenLoaded('currentStage', fn () => $this->currentStage ? [
                'id' => $this->currentStage->id,
                'key' => $this->currentStage->key,
                'name' => $this->currentStage->name,
                'is_terminal' => (bool) $this->currentStage->is_terminal,
                'allowed_next_keys' => $this->currentStage->allowed_next_keys ?? [],
            ] : null),
            'sold_by_employee_id' => $this->sold_by_employee_id,
            'notes' => $this->notes,
            'status' => $this->contract?->status,
            'contract_number' => $this->contract?->contract_number,
            'sold_by' => $this->whenLoaded('soldByEmployee', fn () => $this->soldByEmployee ? ['id' => $this->soldByEmployee->id, 'name' => $this->soldByEmployee->name] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
