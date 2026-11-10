<?php

namespace App\Services;

use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Setting;
use App\Support\BuiltInService;
use App\Support\ThemePalette;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the printable commission slip for one settlement.
 *
 * A commission slip is the payout counterpart of an income receipt: it carries
 * the firm's branding, the employee identity, the settlement period and the
 * accrual lines that make up the amount being paid. It is a receipt-like A5
 * document, coloured with the company's brand palette.
 */
class CommissionSlipService
{
    public function forSettlement(CommissionSettlement $settlement): array
    {
        $settlement->loadMissing([
            'employee.position',
            'employee.departments',
            'baseCurrency',
            'accruals' => fn ($query) => $query->orderBy('earned_at')->orderBy('id'),
            'accruals.contract.applicant',
            'accruals.academyClass',
        ]);

        $setting = Setting::latest('id')->first();
        $currency = $settlement->baseCurrency;
        $accruals = $settlement->accruals
            ->map(fn (CommissionAccrual $accrual) => $this->accrualPayload($accrual))
            ->values()
            ->all();

        return [
            'reference' => $settlement->settlement_number,
            'document' => 'commission_slip',
            'date' => ($settlement->payment_date ?? $settlement->period_end ?? now())->toDateString(),
            'company' => $this->companyPayload($setting),
            'employee' => $settlement->employee ? $this->employeePayload($settlement->employee) : null,
            'settlement' => [
                'period_start' => $settlement->period_start?->toDateString(),
                'period_end' => $settlement->period_end?->toDateString(),
                'status' => $settlement->status,
                'payment_date' => $settlement->payment_date?->toDateString(),
                'notes' => $settlement->notes,
            ],
            'accruals' => $accruals,
            'totals' => [
                'gross' => round((float) $settlement->gross_base_amount, 6),
                'count' => count($accruals),
                'currency' => $this->currencyPayload($currency),
            ],
            'signature' => [
                'url' => $setting?->company_signature ? Storage::disk('public')->url($setting->company_signature) : null,
                'name' => $setting?->original_company_name ?: $setting?->short_company_name,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function accrualPayload(CommissionAccrual $accrual): array
    {
        return [
            'id' => $accrual->id,
            'earned_at' => $accrual->earned_at?->toDateString(),
            'entry_type' => $accrual->entry_type,
            'adjustment_reason' => $accrual->adjustment_reason,
            'contract_number' => $accrual->contract?->contract_number,
            'class_name' => $accrual->academyClass?->name,
            'case_name' => $accrual->contract?->applicant?->name ?? $accrual->academyClass?->name,
            'service' => $accrual->service,
            'service_label' => BuiltInService::label($accrual->service),
            'eligible_base_amount' => (float) $accrual->eligible_base_amount,
            'percentage' => (float) $accrual->percentage,
            'commission_base_amount' => (float) $accrual->commission_base_amount,
        ];
    }

    /** @return array<string, mixed> */
    private function employeePayload(mixed $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'father_name' => $employee->father_name,
            'contact' => $employee->contact,
            'position' => $employee->position?->name,
            'department' => $employee->department,
            'join_date' => $employee->join_date?->toDateString(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function currencyPayload(mixed $currency): ?array
    {
        if (! $currency) {
            return null;
        }

        return [
            'id' => $currency->id,
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'rate' => (float) $currency->rate,
            'decimalPlaces' => (int) $currency->decimal_places,
        ];
    }

    /** @return array<string, mixed> */
    private function companyPayload(?Setting $setting): array
    {
        return [
            'name' => $setting?->original_company_name ?: $setting?->short_company_name ?: $setting?->website_title,
            'short_name' => $setting?->short_company_name,
            'primary_color' => ThemePalette::value('primary_color', $setting?->primary_color),
            'secondary_color' => ThemePalette::value('secondary_color', $setting?->secondary_color),
            'logo_url' => $setting?->website_logo ? Storage::disk('public')->url($setting->website_logo) : null,
            'signature_url' => $setting?->company_signature ? Storage::disk('public')->url($setting->company_signature) : null,
            'address' => $setting?->physical_address,
            'phone' => $setting?->phone,
            'email' => $setting?->company_email,
            'website' => $setting?->website_address,
            'licence' => $setting?->business_licence,
        ];
    }
}
