<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Setting;
use App\Support\BuiltInService;
use App\Support\PaymentStatus;
use App\Support\ThemePalette;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the printable academy enrolment slip for an academy contract.
 *
 * The slip is the academy counterpart of a receipt: it carries the firm's
 * branding, the applicant identity, the enrolled academy program details and
 * the fee/payment state of the contract's academy service line. It is a
 * receipt-like A5 document, coloured with the company's brand palette.
 */
class AcademySlipService
{
    public function forContract(Contract $contract): array
    {
        $contract->loadMissing([
            'applicant.academyDetail.program',
            'applicant.academyDetail.time',
            'applicant.academyDetail.duration',
            'applicant.academyDetail.academyClass',
            'applicant.teachers',
            'applicantContracts.currency',
        ]);

        $setting = Setting::latest('id')->first();
        $applicant = $contract->applicant;
        $line = $contract->applicantContracts->firstWhere('service', BuiltInService::ACADEMY)
            ?? $contract->applicantContracts->first();

        $currency = $line?->currency;
        $fee = (float) ($line?->fee ?? 0);
        $discount = (float) ($line?->discount ?? 0);
        $paid = (float) ($line?->amount_paid ?? 0);
        $payable = max(0.0, $fee - $discount);

        return [
            'reference' => $contract->contract_number,
            'document' => 'academy_slip',
            'date' => ($contract->start_date ?? now())->toDateString(),
            'company' => $this->companyPayload($setting),
            'applicant' => $applicant ? $this->applicantPayload($applicant) : null,
            'contract' => [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
            ],
            'academy' => $this->academyPayload($applicant),
            'service' => $line ? [
                'id' => $line->id,
                'name' => BuiltInService::label(BuiltInService::ACADEMY),
                'service_label' => BuiltInService::label(BuiltInService::ACADEMY),
                'fee' => $fee,
                'discount' => $discount,
                'payment_status' => $line->payment_status,
                'payment_status_label' => PaymentStatus::label($line->payment_status),
                'currency' => $this->currencyPayload($currency),
            ] : null,
            'totals' => [
                'total_fee' => round($payable, 2),
                'paid_amount' => round($paid, 2),
                'balance' => round(max(0, $payable - $paid), 2),
                'currency' => $this->currencyPayload($currency),
            ],
            'signature' => [
                'url' => $setting?->company_signature ? Storage::disk('public')->url($setting->company_signature) : null,
                'name' => $setting?->original_company_name ?: $setting?->short_company_name,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function academyPayload(?Applicant $applicant): array
    {
        $detail = $applicant?->academyDetail;

        return [
            'program' => $detail?->program?->name,
            'time' => $detail?->time?->name,
            'duration' => $detail?->duration?->name,
            'class' => $detail?->academyClass?->name,
            'teachers' => $applicant ? $applicant->teachers->pluck('name')->values()->all() : [],
        ];
    }

    /** @return array<string, mixed> */
    private function applicantPayload(Applicant $applicant): array
    {
        return [
            'id' => $applicant->id,
            'name' => $applicant->name,
            'father_name' => $applicant->father_name,
            'contact' => $applicant->contact,
            'father_phone' => $applicant->father_phone,
            'email' => $applicant->email,
            'address' => $applicant->address,
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
