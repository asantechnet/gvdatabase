<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAdvanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'advanceNumber' => $this->advance_number,
            'employee_id' => $this->employee_id,
            'employeeName' => $this->employee?->name ?? 'Unknown',
            'currencyId' => $this->currency_id,
            'currency' => [
                'id' => $this->currency_id,
                'code' => $this->currency_code ?? $this->currency?->code,
                'symbol' => $this->currency_symbol ?? $this->currency?->symbol,
                'decimalPlaces' => $this->currency?->decimal_places,
                'rate' => (float) $this->rate,
            ],
            'amount' => (float) $this->amount,
            'rate' => (float) $this->rate,
            'baseAmount' => (float) $this->base_amount,
            'date' => $this->advance_date?->format('Y-m-d'),
            'remarks' => $this->remarks ?? '',
            'expenseId' => $this->expense_id,
            'recordedBy' => $this->whenLoaded('recorder', fn () => $this->recorder ? [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ] : null),
        ];
    }
}
