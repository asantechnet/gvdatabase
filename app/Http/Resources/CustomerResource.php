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
            'date' => $this->date->toDateString(),
            'services' => CustomerServiceResource::collection($this->whenLoaded('customerServices')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
