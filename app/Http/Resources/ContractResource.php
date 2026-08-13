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
            'contract' => $this->template?->name ?? 'Untitled Contract',
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'counterparty' => $this->counterparty,
            'counterparty_company' => $this->counterparty_company,
            'counterparty_email' => $this->counterparty_email,
            'counterparty_contact' => $this->counterparty_contact,
            'counterparty_address' => $this->counterparty_address,
            'is_cancelled' => $this->is_cancelled,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'template_id' => $this->template_id,
            'contract_template' => $this->whenLoaded('template', function () {
                return new ContractTemplateResource($this->template);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
