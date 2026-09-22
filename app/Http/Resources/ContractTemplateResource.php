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
            'service_id' => $this->service_id,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'type_slug' => $this->getTypeSlugAttribute(),
            'position_id' => $this->position_id,
            'position' => new PositionResource($this->whenLoaded('position')),
            'cover_text' => $this->cover_text,
            'content' => $this->content,
            'policies' => ContractPolicyResource::collection($this->whenLoaded('policies')),
            'contracts_count' => $this->whenCounted('contracts'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn ($version) => ['id'=>$version->id,'country_id'=>$version->country_id,'country'=>$version->country?->only(['id','name']),'language_id'=>$version->language_id,'language'=>$version->language?->only(['id','name','code']),'version_number'=>$version->version_number,'status'=>$version->status,'is_current'=>$version->is_current,'created_at'=>$version->created_at?->toISOString()])),
        ];
    }
}
