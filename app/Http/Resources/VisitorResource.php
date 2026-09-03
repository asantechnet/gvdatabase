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
            'company' => $this->company,
            'contact' => $this->contact,
            'email' => $this->email,
            'service' => $this->service,
            'marketer' => $this->marketer,
            'marketer_id' => $this->marketer_id,
            'marketer_employee' => $this->whenLoaded('marketerEmployee', fn () => $this->marketerEmployee ? [
                'id' => $this->marketerEmployee->id,
                'name' => $this->marketerEmployee->name,
            ] : null),
            'notes' => $this->notes,
            'converted_at' => $this->converted_at?->toISOString(),
            'interactions' => CrmInteractionResource::collection($this->whenLoaded('interactions')),
            'date' => $this->date->format('Y-m-d'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
