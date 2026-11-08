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
        $amountDue = (float) ($this->amount_due ?? $netAmount);
        $amountPaid = (float) ($this->amount_paid_total ?? $this->amount_paid);
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
            'amountPaid' => $amountPaid,
            'amountDue' => $amountDue,
            'amountOutstanding' => max(0, $amountDue - $amountPaid),
            'paymentStatus' => $this->payment_status ?? ($amountPaid >= $amountDue ? 'paid' : 'open'),
            'bonus' => $this->bonus,
            'workedDays' => (float) $this->worked_days,
            'offDays' => (float) $this->off_days,
            'advanceRecovery' => (float) $this->advance_recovery,
            // Driven by the settlement ledger, not by the advance's current
            // pointer: an advance split over several payslips still appears
            // on the one that withheld it.
            'advances' => $this->whenLoaded('advanceSettlements', fn () => $this->advanceSettlements->map(fn ($settlement) => [
                'id' => $settlement->employee_advance_id,
                'advanceNumber' => $settlement->advance?->advance_number,
                'date' => $settlement->advance?->advance_date?->toDateString(),
                'amount' => (float) ($settlement->advance?->amount ?? $settlement->amount),
                'deductedAmount' => (float) $settlement->amount,
                'remarks' => $settlement->advance?->remarks,
            ])),
            'absentDays' => (float) $this->absent_days,
            'totalDays' => (int) $this->total_days,
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
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => $payment->id, 'paymentNumber' => $payment->payment_number, 'amount' => (float) $payment->amount,
                'date' => $payment->payment_date?->toDateString(), 'remarks' => $payment->remarks, 'expenseId' => $payment->expense_id,
            ])),
            'recordedBy' => $this->whenLoaded('recorder', fn () => $this->recorder ? ['id' => $this->recorder->id, 'name' => $this->recorder->name] : null),
        ];
    }
}
