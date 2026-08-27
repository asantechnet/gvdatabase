<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'type_slug' => $this->type_slug,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'counterparty' => $this->counterparty,
            'counterparty_company' => $this->counterparty_company,
            'counterparty_email' => $this->counterparty_email,
            'counterparty_contact' => $this->counterparty_contact,
            'counterparty_address' => $this->counterparty_address,
            'is_cancelled' => $this->is_cancelled || $this->status === 'cancelled',
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'template_id' => $this->template_id,
            'employee_id' => $this->employee_id,
            'customer_id' => $this->customer_id,
            'company_id' => $this->company_id,
            'cover_text' => $this->cover_text,
            'content' => $this->content,
            'snapshot_data' => $this->snapshot_data,
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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
