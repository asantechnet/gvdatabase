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
            'contract' => $this->name ?? $this->label ?? $this->contract_template->name ?? 'Untitled Contract',
            'status' => $this->status,
            'start_date' => $this->start_date,
            'counterparty' => $this->signee ?? $this->counterparty ?? 'Unknown Counterparty',
            'is_cancelled' => $this->is_cancelled,
            'template_id' => $this->template_id,
            'contract_template' => $this->whenLoaded('contractTemplate', function () {
                return new ContractTemplateResource($this->contractTemplate);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
