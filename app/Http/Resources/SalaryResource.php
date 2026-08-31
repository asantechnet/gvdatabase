<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsCurrentBaseMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryResource extends JsonResource
{
    use PresentsCurrentBaseMoney;

    public function toArray(Request $request): array
    {
        $deductions = (float) $this->deductions->sum('amount');
        $netAmount = (float) $this->total_amount - $deductions;
        $currency = $this->resource->currency;

        return [
            'id' => $this->id,
            'payslip_number' => $this->payslip_number,
            'employee_id' => $this->employee_id,
            'employeeName' => $this->employee?->name ?? 'Unknown',
            'employeeAvatar' => $this->employee?->avatar,
            'currencyId' => $this->currency_id,
            'currency' => [
                'id' => $this->currency_id,
                'code' => $this->currency_code,
                'name' => $this->currency_name,
                'symbol' => $this->currency_symbol,
                'decimalPlaces' => $this->currency_decimal_places,
                'rate' => (float) $this->rate,
            ],
            'currencySnapshotSource' => $this->currency_snapshot_source,
            'currencySnapshotNote' => $this->currency_snapshot_note,
            'salaryMonth' => $this->salary_month,
            'amountPaid' => $this->amount_paid,
            'bonus' => $this->bonus,
            'workedDays' => $this->worked_days,
            'paidOffDays' => $this->paid_off_days,
            'absentDays' => $this->absent_days,
            'unpaidOffDays' => $this->unpaid_off_days,
            'totalDays' => $this->total_days,
            'totalAmount' => $this->total_amount,
            'cnvCurrShrt' => $this->cnv_curr_shrt,
            'baseCurrShrt' => $this->base_curr_shrt,
            'rate' => (float) $this->rate,
            'baseAmount' => (float) $this->base_amount,
            'remarks' => $this->remarks ?? '',
            'date' => $this->payment_date->format('Y-m-d'),
            'bonuses' => WorthyEmployeeResource::collection($this->whenLoaded('bonuses')),
            'deductions' => SalaryDeductionResource::collection($this->whenLoaded('deductions')),
            'totalDeductions' => $deductions,
            'netAmount' => $netAmount,
            'currentBaseAmount' => $this->currentBaseAmount((float) $this->total_amount, $currency),
            'currentBaseNetAmount' => $this->currentBaseAmount($netAmount, $currency),
            'currentBaseCurrency' => $this->currentBaseCurrencyData(),
            'expenseId' => $this->expense?->id,
            'recordedBy' => $this->whenLoaded('recorder', fn () => $this->recorder ? ['id' => $this->recorder->id, 'name' => $this->recorder->name] : null),
        ];
    }
}
