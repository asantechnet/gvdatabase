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
            'fee' => (float) $this->service->fee,
            'currency' => new CurrencyResource($this->service->currency),
            'deadline' => $this->deadline->toDateString(),
            'status' => $this->service->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
