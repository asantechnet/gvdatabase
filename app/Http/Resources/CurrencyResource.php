<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
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
            'code' => $this->code,
            'symbol' => $this->symbol,
            'rate' => (float) $this->rate,
            'isBase' => $this->is_base,
            'isActive' => $this->is_active,
            'decimalPlaces' => $this->decimal_places,
            'updatedAt' => $this->updated_at->toDateString(),
        ];
    }
}
