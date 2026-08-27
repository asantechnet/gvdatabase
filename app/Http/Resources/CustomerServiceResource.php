<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'type' => $this->service->type,
            'fee' => (float) ($this->fee ?? $this->service->fee),
            'currency_id' => $this->currency_id,
            'currency' => new CurrencyResource($this->currency ?? $this->service->currency),
            'deadline' => $this->deadline?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
