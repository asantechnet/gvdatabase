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
            'application_fee_min' => $this->application_fee_min,
            'application_fee_max' => $this->application_fee_max,
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
            // The case page reads the party's display identity from the line
            // payload; it never loads the whole applicant.
            'applicant' => $this->whenLoaded('applicant', fn () => $this->applicant ? [
                'id' => $this->applicant->id,
                'name' => $this->applicant->name,
                'contact' => $this->applicant->contact,
                'email' => $this->applicant->email,
                'father_name' => $this->applicant->father_name,
                'father_phone' => $this->applicant->father_phone,
            ] : null),
            'current_stage_id' => $this->current_stage_id,
            'current_stage' => $this->whenLoaded('currentStage', fn () => $this->currentStage ? [
                'id' => $this->currentStage->id,
                'key' => $this->currentStage->key,
                'name' => $this->currentStage->name,
                'is_terminal' => (bool) $this->currentStage->is_terminal,
                'allowed_next_keys' => $this->currentStage->allowed_next_keys ?? [],
            ] : null),
            'allowed_next_stages' => $this->when(isset($this->allowed_next_stages), fn () => $this->allowed_next_stages ?? []),
            'stage_history' => $this->whenLoaded('stageHistory', fn () => $this->stageHistory->map(fn ($history) => [
                'id' => $history->id,
                'applicant_contract_id' => $history->applicant_contract_id,
                'old_stage' => $history->oldStage ? [
                    'id' => $history->oldStage->id,
                    'key' => $history->oldStage->key,
                    'name' => $history->oldStage->name,
                ] : null,
                'new_stage' => $history->newStage ? [
                    'id' => $history->newStage->id,
                    'key' => $history->newStage->key,
                    'name' => $history->newStage->name,
                ] : null,
                'note' => $history->note,
                'changed_at' => $history->changed_at?->toISOString(),
                'changer' => $history->changer ? [
                    'id' => $history->changer->id,
                    'name' => $history->changer->name,
                ] : null,
            ])),
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($task) => [
                'id' => $task->id,
                'applicant_contract_id' => $task->applicant_contract_id,
                'title' => $task->title,
                'progress' => (int) $task->progress,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'completed_at' => $task->completed_at?->toISOString(),
            ])),
            'incomes' => $this->whenLoaded('incomes', fn () => $this->incomes->map(fn ($income) => [
                'id' => $income->id,
                'applicant_contract_id' => $income->applicant_contract_id,
                'receipt_number' => $income->receipt_number,
                'amount' => (float) $income->amount,
                'date' => $income->date?->toDateString(),
                'description' => $income->description,
            ])),
            'sold_by_employee_id' => $this->sold_by_employee_id,
            'notes' => $this->notes,
            'status' => $this->contract?->status,
            'contract_number' => $this->contract?->contract_number,
            'contract_role' => $this->contract?->contract_role,
            'parent_contract_number' => $this->contract?->parentContract?->contract_number,
            'is_extension' => (bool) $this->contract?->isExtension(),
            'sold_by' => $this->whenLoaded('soldByEmployee', fn () => $this->soldByEmployee ? ['id' => $this->soldByEmployee->id, 'name' => $this->soldByEmployee->name] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
