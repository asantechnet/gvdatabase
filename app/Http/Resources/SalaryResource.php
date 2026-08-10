<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payslip_number' => $this->payslip_number,
            'employee_id' => $this->employee_id,
            'employeeName' => $this->employee?->name ?? 'Unknown',
            'currencyId' => $this->currency_id,
            'currency' => $this->currency ? [
                'id' => $this->currency->id,
                'code' => $this->currency->code,
                'symbol' => $this->currency->symbol,
                'decimalPlaces' => $this->currency->decimal_places,
            ] : null,
            'salaryMonth' => $this->salary_month,
            'amountPaid' => $this->amount_paid,
            'bonus' => $this->bonus,
            'workedDays' => $this->worked_days,
            'totalDays' => $this->total_days,
            'totalAmount' => $this->total_amount,
            'remarks' => $this->remarks ?? '',
            'date' => $this->payment_date->format('Y-m-d'),
            'bonuses' => WorthyEmployeeResource::collection($this->whenLoaded('bonuses')),
        ];
    }
}
