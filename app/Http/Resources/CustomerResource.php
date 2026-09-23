<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'name' => $this->name,
            'company' => $this->company,
            'contact' => $this->contact,
            'email' => $this->email,
            'address' => $this->address,
            'tax_number' => $this->tax_number,
            'marketer' => $this->marketer,
            'marketer_id' => $this->marketer_id,
            'marketer_employee' => $this->whenLoaded('marketerEmployee', fn () => $this->marketerEmployee ? [
                'id' => $this->marketerEmployee->id,
                'name' => $this->marketerEmployee->name,
            ] : null),
            'notes' => $this->notes,
            'date' => $this->date->toDateString(),
            'services' => $request->boolean('compact')
                ? $this->whenLoaded('contractServices', fn () => $this->contractServices->map(fn ($item) => [
                    'id' => $item->id, 'service_id' => $item->service_id, 'type' => $item->service?->type,
                    'status' => $item->contract?->status,
                    'currency' => $item->service?->currency?->only(['id', 'code', 'symbol']),
                ]), [])
                : ContractServiceResource::collection($this->whenLoaded('contractServices')),
            'interactions' => CrmInteractionResource::collection($this->whenLoaded('interactions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
