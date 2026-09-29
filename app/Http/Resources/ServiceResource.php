<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
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
            // `type` remains the storage column for compatibility. New UI and
            // API consumers should use `name`; it is a service, not a type.
            'name' => $this->type,
            'type' => $this->type,
            'code' => $this->code,
            'category' => $this->category,
            'description' => $this->description,

            'fee' => (float) $this->fee,
            'status' => $this->status,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'packages' => $this->whenLoaded('packages'),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => $this->variant($variant))->values()),
            // The contract template that represents this service at assignment
            // time. Null means the generator will auto-create one on demand.
            'contract_template' => $this->whenLoaded('contractTemplate', fn () => $this->contractTemplate ? [
                'id' => $this->contractTemplate->id,
                'name' => $this->contractTemplate->name,
                'is_active' => (bool) $this->contractTemplate->is_active,
                'contract_type_id' => $this->contractTemplate->contract_type_id,
                'service_id' => $this->contractTemplate->service_id,
            ] : null),
            'has_contract_template' => $this->relationLoaded('contractTemplate')
                ? $this->contractTemplate !== null
                : false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function variant($variant): array
    {
        return [
            'id' => $variant->id,
            'service_id' => $variant->service_id,
            'name' => $variant->name,
            'code' => $variant->code,
            'fee' => $variant->fee === null ? null : (float) $variant->fee,
        ];
    }
}
