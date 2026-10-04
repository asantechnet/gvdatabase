<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'service_type' => $this->service_type,
            'is_active' => (bool) $this->is_active,
            'currency_id' => $this->currency_id,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'fee' => $this->fee === null ? null : (float) $this->fee,
            'service_manifest' => $this->service_manifest,
            // The packing list: member services with their share of the
            // package price, so clients never reach into the pivot directly.
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($service) => [
                ...(new ServiceResource($service))->toArray($request),
                'allocated_fee' => $service->pivot->allocated_fee === null ? null : (float) $service->pivot->allocated_fee,
                'sort_order' => (int) ($service->pivot->sort_order ?? 0),
            ])->values()),
            // The contract template that represents this package at sale time.
            // Null means issuance falls back to one contract per member service.
            'contract_template' => $this->whenLoaded('contractTemplate', fn () => $this->contractTemplate ? [
                'id' => $this->contractTemplate->id,
                'name' => $this->contractTemplate->name,
                'is_active' => (bool) $this->contractTemplate->is_active,
                'contract_type_id' => $this->contractTemplate->contract_type_id,
                'package_id' => $this->contractTemplate->package_id,
            ] : null),
            'has_contract_template' => $this->relationLoaded('contractTemplate')
                ? $this->contractTemplate !== null
                : false,
            // What the bundle would cost without the discount, and the discount.
            'total_service_fee' => $this->whenLoaded('services', fn () => $this->totalServiceFee()),
            'discount' => $this->whenLoaded('services', fn () => $this->discount()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
