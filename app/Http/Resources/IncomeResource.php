<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'type' => $this->type,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'quantity' => $this->quantity,
            'currency' => $this->currency,
            'rate_to_base' => (float) $this->rate_to_base,
            'total_amount' => (float) $this->total_amount,
            'date' => $this->date->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
