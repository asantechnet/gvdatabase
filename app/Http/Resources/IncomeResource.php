<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->resource->incomeCurrency;

        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'customer_id' => $this->customer_id,
            'invoice_id' => $this->invoice_id,
            'currency_id' => $this->currency_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'type' => $this->type,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'quantity' => $this->quantity,
            'currency' => $currency ? [
                'id' => $currency->id,
                'code' => $currency->code,
                'symbol' => $currency->symbol,
                'rateToBase' => (float) $currency->rate_against_base,
                'decimalPlaces' => $currency->decimal_places,
            ] : null,
            'rate_to_base' => (float) $this->rate_to_base,
            'total_amount' => (float) $this->total_amount,
            'date' => $this->date->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
