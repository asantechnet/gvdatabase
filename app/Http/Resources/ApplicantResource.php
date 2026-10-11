<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Presents an applicant as the single customer-facing record.
 *
 * Replaces the former `CustomerResource`: one row now carries both the billing
 * party identity and the visa case, so both halves are serialized together.
 */
class ApplicantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Party identity, absorbed from `customers`.
            'name' => $this->name,
            'father_name' => $this->father_name,
            'father_phone' => $this->father_phone,
            'backup_phone' => $this->backup_phone,
            'contact' => $this->contact,
            'email' => $this->email,
            'address' => $this->address,
            'tax_number' => $this->tax_number,
            'admission_employee_id' => $this->admission_employee_id,
            'admission_employee' => $this->whenLoaded('admissionEmployee', fn () => $this->admissionEmployee ? $this->admissionEmployee->only(['id', 'name']) : null),
            'notes' => $this->notes,
            // Which built-in service this applicant belongs to, and that
            // service's own field set (null when the record has no detail row).
            'service' => $this->service,
            'details' => $this->whenLoaded($this->detailRelationName(), fn () => $this->detail()?->toArray()),
            'favorite_subjects' => $this->whenLoaded('favoriteSubjects', fn () => $this->favoriteSubjects->map->only(['id', 'name'])),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map->only(['id', 'name'])),
            'teachers' => $this->whenLoaded('teachers', fn () => $this->teachers->map->only(['id', 'name'])),
            'favorite_subject_ids' => $this->whenLoaded('favoriteSubjects', fn () => $this->favoriteSubjects->pluck('id')->all()),
            'skill_ids' => $this->whenLoaded('skills', fn () => $this->skills->pluck('id')->all()),
            'teacher_ids' => $this->whenLoaded('teachers', fn () => $this->teachers->pluck('id')->all()),
            // Case.
            'parent_applicant_id' => $this->parent_applicant_id,
            'status' => $this->status,
            'responsible_employee_id' => $this->responsible_employee_id,
            'current_stage_id' => $this->current_stage_id,
            'current_stage' => $this->whenLoaded('currentStage', fn () => $this->currentStage ? [
                'id' => $this->currentStage->id,
                'name' => $this->currentStage->name,
                'key' => $this->currentStage->key,
            ] : null),
            'responsible_employee' => $this->whenLoaded('responsibleEmployee', fn () => $this->responsibleEmployee ? [
                'id' => $this->responsibleEmployee->id,
                'name' => $this->responsibleEmployee->name,
            ] : null),
            'attachment_count' => $this->whenCounted('attachments'),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => ['id' => $a->id, 'label' => $a->label, 'category' => $a->category, 'name' => $a->original_name, 'mime_type' => $a->mime_type, 'size' => $a->size, 'uploaded_at' => $a->created_at?->toISOString(), 'download_url' => "/api/applicants/{$this->id}/attachments/{$a->id}/download"])),
            // Contracted service lines. `compact` is the payment picker shape.
            'services' => $request->boolean('compact')
                ? $this->whenLoaded('applicantContracts', fn () => $this->applicantContracts->map(fn ($item) => [
                    'id' => $item->id, 'type' => $item->serviceLabel(),
                    'service' => $item->serviceKey(),
                    'service_label' => $item->serviceLabel(),
                    'payable_total' => $item->payableTotal(),
                    'amount_paid' => (float) $item->amount_paid,
                    'remaining_total' => $item->remainingTotal(),
                    'payment_status' => $item->payment_status,
                    'status' => $item->contract?->status,
                    'fee' => $item->fee,
                    'currency' => $item->currency?->only(['id', 'code', 'symbol', 'rate']),
                ]), [])
                : ApplicantContractResource::collection($this->whenLoaded('applicantContracts')),
            'interactions' => CrmInteractionResource::collection($this->whenLoaded('interactions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
