<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->contractType?->name,
            'contract_type_id' => $this->contract_type_id,
            'type_slug' => $this->getTypeSlugAttribute(),
            'position_id' => $this->position_id,
            'position' => new PositionResource($this->whenLoaded('position')),
            'cover_text' => $this->cover_text,
            'content' => $this->content,
            'policies' => ContractPolicyResource::collection($this->whenLoaded('policies')),
            'contracts_count' => $this->whenCounted('contracts'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
