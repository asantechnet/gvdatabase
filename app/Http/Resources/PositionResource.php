<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'salary' => $this->salary,
            'currencyId' => $this->currency_id,
            'currency' => [
                'code' => $this->currency_code,
                'symbol' => $this->currency_symbol,
                'decimalPlaces' => $this->currency_decimal_places,
                'rate' => $this->rate === null ? null : (float) $this->rate,
            ],
            'status' => $this->status,
            'employee_count' => $this->whenCounted('employees'),
            'user_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
