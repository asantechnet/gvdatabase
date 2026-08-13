<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->resource->expenseCurrency;

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'currency_id' => $this->currency_id,
            'type' => $this->type,
            'description' => $this->description,
            'amount' => $this->amount,
            'quantity' => $this->quantity,
            'currency' => $currency ? [
                'id' => $currency->id,
                'code' => $currency->code,
                'symbol' => $currency->symbol,
                'rateToBase' => (float) $currency->rate_against_base,
                'decimalPlaces' => $currency->decimal_places,
            ] : null,
            'rate_to_base' => $this->rate_to_base,
            'total_amount' => $this->total_amount,
            'date' => $this->date->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
