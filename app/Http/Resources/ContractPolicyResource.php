<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'country_id' => $this->country_id,
            'language_id' => $this->language_id,
            'country' => $this->whenLoaded('country', fn () => $this->country?->only(['id', 'name'])),
            'language' => $this->whenLoaded('language', fn () => $this->language?->only(['id', 'name', 'code'])),
            'is_active' => $this->is_active,
            'header' => $this->header,
            'content' => $this->content,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
