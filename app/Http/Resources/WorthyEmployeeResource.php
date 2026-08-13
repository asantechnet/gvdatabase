<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorthyEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'employeeId' => (string) $this->employee_id,
            'employeeName' => $this->employee->name,
            'employeeEmail' => $this->employee->user?->email,
            'department' => $this->employee->department,
            'performanceScore' => $this->performance_score,
            'eligibilityReason' => $this->eligibility_reason,
            'achievementDetails' => $this->achievement_details,
            'suggestedAmount' => $this->suggested_amount,
            'originalCurrency' => $this->currencyPayload('', 'currency_id'),
            'suggestedRateToBase' => (float) $this->suggested_rate_to_base,
            'suggestedBaseAmount' => (float) $this->suggested_base_amount,
            'selectedAmount' => $this->selected_amount,
            'selectedCurrency' => $this->currencyPayload('selected_', 'selected_currency_id'),
            'selectedRateToBase' => (float) $this->selected_rate_to_base,
            'selectedBaseAmount' => (float) $this->selected_base_amount,
            'selectedType' => $this->selected_type,
            'isGranted' => $this->is_granted,
            'grantedAt' => $this->granted_at?->toISOString(),
            'grantedBy' => $this->granted_by,
            'grantedAmount' => $this->granted_amount === null ? null : (float) $this->granted_amount,
            'grantedCurrency' => $this->granted_currency_id ? $this->currencyPayload('granted_', 'granted_currency_id') : null,
            'grantedRateToBase' => $this->granted_rate_to_base === null ? null : (float) $this->granted_rate_to_base,
            'grantedBaseAmount' => $this->granted_base_amount === null ? null : (float) $this->granted_base_amount,
            'currencySnapshotSource' => $this->currency_snapshot_source,
            'currencySnapshotNote' => $this->currency_snapshot_note,
            'isAutomatic' => (bool) $this->is_automatic,
            'calculationKey' => $this->calculation_key,
            'calculationScore' => $this->calculation_score === null ? null : (float) $this->calculation_score,
            'calculationInputs' => $this->calculation_inputs,
            'ruleSnapshot' => $this->rule_snapshot,
            'calculatedAt' => $this->calculated_at?->toISOString(),
            'salaryConversion' => $this->when($this->pivot !== null, fn () => [
                'sourceAmount' => (float) $this->pivot->source_amount,
                'sourceCurrencyCode' => $this->pivot->source_currency_code,
                'sourceRateToBase' => (float) $this->pivot->source_rate_to_base,
                'baseAmount' => (float) $this->pivot->base_amount,
                'salaryCurrencyCode' => $this->pivot->salary_currency_code,
                'salaryRateToBase' => (float) $this->pivot->salary_rate_to_base,
                'convertedAmount' => (float) $this->pivot->converted_amount,
                'formula' => $this->pivot->conversion_formula,
                'snapshotSource' => $this->pivot->conversion_snapshot_source,
            ]),
            'bonusMonth' => $this->bonus_month->format('Y-m'),
        ];
    }

    private function currencyPayload(string $prefix, string $idField): array
    {
        return [
            'id' => $this->{$idField},
            'code' => $this->{$prefix.'currency_code'},
            'name' => $this->{$prefix.'currency_name'},
            'symbol' => $this->{$prefix.'currency_symbol'},
            'decimalPlaces' => $this->{$prefix.'currency_decimal_places'},
        ];
    }
}
