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
            'category' => $this->category,
            'description' => $this->description,
            'duration' => $this->duration,
            'fee' => (float) $this->fee,
            'status' => $this->status,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'packages' => $this->whenLoaded('packages'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
