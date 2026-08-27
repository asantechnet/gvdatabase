<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'registration_number' => $this->registration_number,
            'tax_number' => $this->tax_number,
            'national_id' => $this->national_id,
            'legal_form' => $this->legal_form,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'country' => $this->country,
            'province' => $this->province,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'address_line' => $this->address_line,
            'industry' => $this->industry,
            'description' => $this->description,
            'representative_name' => $this->representative_name,
            'representative_title' => $this->representative_title,
            'representative_phone' => $this->representative_phone,
            'representative_email' => $this->representative_email,
            'is_active' => (bool) $this->is_active,
            'contracts_count' => $this->whenCounted('contracts'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
