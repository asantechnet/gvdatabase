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
            'company' => $this->company,
            'contact' => $this->contact,
            'email' => $this->email,
            'address' => $this->address,
            'tax_number' => $this->tax_number,
            'marketer' => $this->marketer,
            'marketer_id' => $this->marketer_id,
            'marketer_employee' => $this->whenLoaded('marketerEmployee', fn () => $this->marketerEmployee ? [
                'id' => $this->marketerEmployee->id,
                'name' => $this->marketerEmployee->name,
            ] : null),
            'notes' => $this->notes,
            'date' => $this->date?->toDateString(),
            // Case.
            'parent_applicant_id' => $this->parent_applicant_id,
            'status' => $this->status,
            'application_date' => $this->application_date?->toDateString(),
            'responsible_employee_id' => $this->responsible_employee_id,
            'country_id' => $this->country_id,
            'university_id' => $this->university_id,
            'education_level_id' => $this->education_level_id,
            'language_id' => $this->language_id,
            'semester_id' => $this->semester_id,
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
            'country' => $this->whenLoaded('country', fn () => $this->country ? $this->country->only(['id', 'name', 'code']) : null),
            'university' => $this->whenLoaded('university', fn () => $this->university ? $this->university->only(['id', 'name']) : null),
            'education_level' => $this->whenLoaded('educationLevel', fn () => $this->educationLevel ? $this->educationLevel->only(['id', 'name']) : null),
            'language' => $this->whenLoaded('language', fn () => $this->language ? $this->language->only(['id', 'name']) : null),
            'semester' => $this->whenLoaded('semester', fn () => $this->semester ? $this->semester->only(['id', 'name', 'code']) : null),
            // Contracted service lines. `compact` is the invoice picker shape.
            'services' => $request->boolean('compact')
                ? $this->whenLoaded('contractServices', fn () => $this->contractServices->map(fn ($item) => [
                    'id' => $item->id, 'service_id' => $item->service_id, 'type' => $item->service?->type,
                    'status' => $item->contract?->status,
                    'fee' => $item->fee ?? $item->service?->fee,
                    'currency' => $item->service?->currency?->only(['id', 'code', 'symbol', 'rate']),
                ]), [])
                : ContractServiceResource::collection($this->whenLoaded('contractServices')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),
            'interactions' => CrmInteractionResource::collection($this->whenLoaded('interactions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
