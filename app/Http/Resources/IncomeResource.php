<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsAttachments;
use App\Http\Resources\Concerns\PresentsCurrentBaseMoney;
use App\Support\BuiltInService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeResource extends JsonResource
{
    use PresentsAttachments, PresentsCurrentBaseMoney;

    public function toArray(Request $request): array
    {
        $currency = $this->resource->incomeCurrency;
        $line = $this->resource->relationLoaded('applicantContract') ? $this->applicantContract : null;

        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'attachments' => $this->attachmentsPayload('incomes', $this->id),
            'applicant_id' => $this->applicant_id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'student_number' => $this->student->student_number,
                'name' => $this->student->name,
                'contact' => $this->student->contact,
                'email' => $this->student->email,
            ] : null),
            'applicant_contract_id' => $this->applicant_contract_id,
            'contract_service' => $line ? [
                'id' => $line->id,
                'contract_id' => $line->contract_id,
                'contract_number' => $line->contract?->contract_number,
                'service' => $line->serviceKey(),
                'service_label' => $line->serviceLabel(),
                'service_label_key' => BuiltInService::labelKey($line->serviceKey()),
                'fee' => (float) ($line->fee ?? 0),
                'discount' => (float) ($line->discount ?? 0),
                'payable_total' => $line->payableTotal(),
                'amount_paid' => (float) ($line->amount_paid ?? 0),
                'remaining_total' => $line->remainingTotal(),
                'payment_status' => $line->payment_status,
                'payment_status_label' => $line->paymentStatusLabel(),
                'currency' => $line->currency ? [
                    'id' => $line->currency->id,
                    'code' => $line->currency->code,
                    'symbol' => $line->currency->symbol,
                    'rate' => (float) $line->currency->rate,
                    'decimalPlaces' => (int) $line->currency->decimal_places,
                ] : null,
            ] : null,
            'currency_id' => $this->currency_id,
            'base_currency_id' => $this->base_currency_id,
            'type' => $this->type,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'quantity' => $this->quantity,
            'currency' => $currency ? [
                'id' => $currency->id,
                'code' => $currency->code,
                'symbol' => $currency->symbol,
                'rate' => (float) $currency->rate,
                'decimalPlaces' => $currency->decimal_places,
            ] : null,
            'cnv_curr_shrt' => $this->cnv_curr_shrt,
            'base_curr_shrt' => $this->base_curr_shrt,
            'rate' => (float) $this->rate,
            'base_amount' => (float) $this->base_amount,
            'revaluation_reason' => $this->revaluation_reason,
            'currentBaseAmount' => $this->currentBaseAmount((float) $this->amount * (int) $this->quantity, $currency),
            'currentBaseCurrency' => $this->currentBaseCurrencyData(),
            'recorded_by' => $this->recorded_by,
            'recordedBy' => $this->whenLoaded('recorder', fn () => $this->recorder ? ['id' => $this->recorder->id, 'name' => $this->recorder->name] : null),
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? ['id' => $this->approver->id, 'name' => $this->approver->name] : null),
            'date' => $this->date->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
