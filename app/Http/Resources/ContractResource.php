<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'contract' => $this->name ?? $this->template?->name ?? 'Untitled Contract',
            'type' => $this->type,
            'contract_type_id' => $this->contract_type_id,
            'service_ids' => $this->whenLoaded('contractServices', fn () => $this->contractServices->pluck('service_id')->values()),
            'services' => ContractServiceResource::collection($this->whenLoaded('contractServices')),
            'type_slug' => $this->type_slug,
            'status' => strtolower((string) $this->status),
            'start_date' => $this->start_date?->toDateString(),
            'company_signatory_name' => $this->company_signatory_name,
            'counterparty' => $this->counterparty,
            'counterparty_company' => $this->counterparty_company,
            'counterparty_email' => $this->counterparty_email,
            'counterparty_contact' => $this->counterparty_contact,
            'counterparty_address' => $this->counterparty_address,
            'is_cancelled' => $this->is_cancelled || $this->status === 'cancelled',
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'template_id' => $this->template_id,
            'contract_template_version_id' => $this->contract_template_version_id,
            'employee_id' => $this->employee_id,
            'sold_by_employee_id' => $this->sold_by_employee_id,
            'marketer_id' => $this->marketer_id,
            'customer_id' => $this->customer_id,
            'applicant_id' => $this->applicant_id,
            'company_id' => $this->company_id,
            'cover_text' => $this->cover_text,
            'content' => $this->content,
            'snapshot_data' => $this->snapshotWithDocumentUrls(),
            'is_custom' => $this->is_custom,

            // Snapshot of the counterparty at issue time; historical
            // contracts stay renderable even if the source record changes.
            'parties' => $this->snapshot_data['parties'] ?? null,

            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'contract_type' => new ContractTypeResource($this->whenLoaded('contractType')),
            'contract_template' => $this->whenLoaded('template', function () {
                return new ContractTemplateResource($this->template);
            }),
            'contract_template_version' => $this->whenLoaded('templateVersion', fn () => ['id'=>$this->templateVersion?->id,'language_id'=>$this->templateVersion?->language_id,'version_number'=>$this->templateVersion?->version_number,'status'=>$this->templateVersion?->status]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function snapshotWithDocumentUrls(): ?array
    {
        $snapshot = $this->snapshot_data;
        if (! is_array($snapshot) || ! isset($snapshot['document_design']) || ! is_array($snapshot['document_design'])) {
            return $snapshot;
        }

        foreach (['letterhead', 'cover_design'] as $field) {
            $path = $snapshot['document_design'][$field] ?? null;
            $snapshot['document_design']["{$field}_url"] = $path ? Storage::disk('public')->url($path) : null;
        }

        return $snapshot;
    }
}
