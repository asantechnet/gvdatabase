<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact' => $this->contact,
            'email' => $this->email,
            'service' => $this->service,
            'counselor_employee_id' => $this->counselor_employee_id,
            'counselor' => $this->whenLoaded('counselor', fn () => $this->counselor ? $this->counselor->only(['id', 'name']) : null),
            'notes' => $this->notes,
            'converted_at' => $this->converted_at?->toISOString(),
            'interactions' => CrmInteractionResource::collection($this->whenLoaded('interactions')),
            'date' => $this->date->format('Y-m-d'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
