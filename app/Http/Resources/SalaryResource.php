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
            'employeeAvatar' => $this->employee?->avatar,
            'currencyId' => $this->currency_id,
            'currency' => [
                'id' => $this->currency_id,
                'code' => $this->currency_code,
                'name' => $this->currency_name,
                'symbol' => $this->currency_symbol,
                'decimalPlaces' => $this->currency_decimal_places,
                'rateToBase' => (float) $this->exchange_rate_to_base,
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
            'amountPaidBase' => (float) $this->amount_paid_base,
            'bonusBase' => (float) $this->bonus_base,
            'totalAmountBase' => (float) $this->total_amount_base,
            'remarks' => $this->remarks ?? '',
            'date' => $this->payment_date->format('Y-m-d'),
            'bonuses' => WorthyEmployeeResource::collection($this->whenLoaded('bonuses')),
            'deductions' => SalaryDeductionResource::collection($this->whenLoaded('deductions')),
            'totalDeductions' => (float) $this->deductions->sum('amount'),
            'netAmount' => (float) $this->total_amount - (float) $this->deductions->sum('amount'),
            'expenseId' => $this->expense?->id,
        ];
    }
}
