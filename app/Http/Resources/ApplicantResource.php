<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

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
        $assignments = $this->assignmentEmployees();
        $primary = $assignments->first();
        $assignmentLoaded = $this->resource->relationLoaded('responsibleEmployees')
            || $this->resource->relationLoaded('responsibleEmployee');

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
            'responsible_employee_ids' => $this->when($assignmentLoaded, fn () => $assignments->pluck('id')->all()),
            'responsible_employees' => $this->when($assignmentLoaded, fn () => $assignments->map(fn ($employee) => $employee->only(['id', 'name', 'avatar']))->values()->all()),
            'notes' => $this->notes,
            'consultant_review' => $this->consultant_review,
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
            // Overall payment state across the contracted lines: one chip for
            // the applications list. Only a viewer allowed to see case finances
            // receives it, so the list omits the column instead of rendering a
            // dash for every row.
            'payment_status' => $this->when(
                $request->user()?->hasPermission('view_applicant_financials') === true
                    && $this->relationLoaded('applicantContracts'),
                fn () => $this->overallPaymentStatus(),
                new MissingValue,
            ),
            // Case.
            'parent_applicant_id' => $this->parent_applicant_id,
            'status' => $this->status,
            'responsible_employee_id' => $primary?->id ?? $this->responsible_employee_id,
            'current_stage_id' => $this->current_stage_id,
            // The stage the applications list displays for this row: the
            // service line's under a service filter, the mirror otherwise.
            'display_stage' => $this->when(
                $this->resource->getAttribute('display_stage') !== null,
                fn () => $this->resource->getAttribute('display_stage'),
                new MissingValue,
            ),
            'current_stage' => $this->whenLoaded('currentStage', fn () => $this->currentStage ? [
                'id' => $this->currentStage->id,
                'name' => $this->currentStage->name,
                'key' => $this->currentStage->key,
            ] : null),
            'responsible_employee' => $this->when($assignmentLoaded, fn () => $primary ? [
                'id' => $primary->id,
                'name' => $primary->name,
                'avatar' => $primary->avatar,
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

    /** The loaded plural assignment, with a legacy singular-only fallback. */
    private function assignmentEmployees()
    {
        $resource = $this->resource;
        if ($resource->relationLoaded('responsibleEmployees') && $resource->responsibleEmployees->isNotEmpty()) {
            return $resource->responsibleEmployees;
        }
        if ($resource->relationLoaded('responsibleEmployee') && $resource->responsibleEmployee) {
            return collect([$resource->responsibleEmployee]);
        }

        return collect();
    }
}
