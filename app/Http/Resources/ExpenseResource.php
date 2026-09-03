<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsCurrentBaseMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    use PresentsCurrentBaseMoney;

    public function toArray(Request $request): array
    {
        $currency = $this->resource->expenseCurrency;

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company ? [
                'id' => $this->company->id, 'name' => $this->company->trade_name ?: $this->company->legal_name,
            ] : null),
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id, 'name' => $this->project->name,
            ] : null),
            'currency_id' => $this->currency_id,
            'base_currency_id' => $this->base_currency_id,
            'type' => $this->type,
            'description' => $this->description,
            'amount' => $this->amount,
            'quantity' => $this->quantity,
            'currency' => $currency ? [
                'id' => $currency->id,
                'code' => $currency->code,
                'symbol' => $currency->symbol,
                'rate' => (float) $currency->rate,
                'decimalPlaces' => $currency->decimal_places,
            ] : null,
            'cnv_curr_shrt' => $this->cnv_curr_shrt,
            'base_curr_shrt' => $this->base_curr_shrt,
            'rate' => (float) $this->rate,
            'base_amount' => (float) $this->base_amount,
            'currentBaseAmount' => $this->currentBaseAmount((float) $this->amount * (int) $this->quantity, $currency),
            'currentBaseCurrency' => $this->currentBaseCurrencyData(),
            'date' => $this->date->toDateString(),
            'recorded_by' => $this->recorded_by,
            'recordedBy' => $this->whenLoaded('recorder', fn () => $this->recorder ? ['id' => $this->recorder->id, 'name' => $this->recorder->name] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
