<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\Income;
use App\Models\Setting;
use App\Models\Student;
use App\Support\BuiltInService;
use App\Support\PaymentStatus;
use App\Support\ThemePalette;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the printable receipt payload for a single income record.
 *
 * A receipt is the customer-facing proof of payment for **one** service of an
 * educational visa firm, so it carries the party identity, that contracted
 * service line (name, fee, discount, paid, remaining), a serial number and the
 * company's brand colours.
 */
class IncomeReceiptService
{
    public function forIncome(Income $income): array
    {
        $income->loadMissing([
            'applicant.scholarshipDetail.country',
            'applicant.scholarshipDetail.educationLevel',
            'student',
            'applicantContract.currency',
            'applicantContract.contract',
            'incomeCurrency',
            'recorder',
        ]);

        $setting = Setting::latest('id')->first();
        $currency = $income->incomeCurrency;
        $receiptRate = (float) ($income->rate ?: ($currency?->rate ?? 1)) ?: 1.0;
        $applicant = $income->applicant;
        $line = $income->applicantContract;

        $services = $line ? [$this->linePayload($line, $receiptRate)] : [];
        $totalFee = collect($services)->sum('converted_fee');
        $paidAmount = $line
            ? $this->convert((float) ($line->amount_paid ?? 0), $this->lineRate($line), $receiptRate)
            : $this->lineTotal($income, $receiptRate);

        if ($totalFee <= 0) {
            // A receipt without a contract line (e.g. a misc. payment) still
            // totals its own amount so the document is never blank.
            $totalFee = $this->lineTotal($income, $receiptRate);
            $paidAmount = $totalFee;
        }

        $precision = max(0, min(8, (int) ($currency?->decimal_places ?? 2)));

        return [
            'serial_number' => $income->receipt_number,
            'document' => 'receipt',
            'date' => $income->date?->toDateString(),
            'income' => [
                'id' => $income->id,
                'name' => $income->type,
                'type' => $income->type,
                'description' => $income->description,
                'amount' => (float) $income->amount,
                'quantity' => (int) $income->quantity,
                'line_total' => (float) $income->amount * (int) $income->quantity,
                'rate' => (float) $income->rate,
                'currency' => $this->currencyPayload($currency),
                'base_amount' => (float) $income->base_amount,
            ],
            'company' => $this->companyPayload($setting),
            'applicant' => $applicant ? $this->applicantPayload($applicant) : null,
            'student' => $income->student ? $this->studentPayload($income->student) : null,
            'contract' => $line ? [
                'id' => $line->contract_id,
                'contract_number' => $line->contract?->contract_number,
            ] : null,
            'service' => $services[0] ?? null,
            'services' => $services,
            'totals' => [
                'total_fee' => round($totalFee, $precision),
                'paid_amount' => round($paidAmount, $precision),
                'balance' => round(max(0, $totalFee - $paidAmount), $precision),
                'currency' => $this->currencyPayload($currency),
            ],
            'signature' => [
                'url' => $setting?->company_signature ? Storage::disk('public')->url($setting->company_signature) : null,
                'name' => $setting?->original_company_name ?: $setting?->short_company_name,
            ],
            'recorded_by' => $income->recorder ? ['id' => $income->recorder->id, 'name' => $income->recorder->name] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function linePayload(ApplicantContract $line, float $receiptRate): array
    {
        $fee = (float) ($line->fee ?? 0) - (float) ($line->discount ?? 0);
        $sourceRate = $this->lineRate($line);
        $service = $line->serviceKey();

        return [
            'kind' => 'service',
            'id' => $line->id,
            'contract_id' => $line->contract_id,
            'service' => $service,
            'service_label' => BuiltInService::label($service),
            'service_label_key' => BuiltInService::labelKey($service),
            'name' => $line->serviceLabel(),
            'fee' => $fee,
            'discount' => (float) ($line->discount ?? 0),
            'rate' => $sourceRate,
            'converted_fee' => round($this->convert($fee, $sourceRate, $receiptRate), 2),
            'payment_status' => $line->payment_status,
            'payment_status_label' => PaymentStatus::label($line->payment_status),
            'currency' => [
                'code' => $line->currency?->code,
                'symbol' => $line->currency?->symbol,
                'decimalPlaces' => (int) ($line->currency?->decimal_places ?? 2),
            ],
        ];
    }

    /** The rate a line is priced in, preferring its stored contract rate. */
    private function lineRate(ApplicantContract $line): float
    {
        $stored = (float) ($line->currency_rate ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        $rate = (float) ($line->currency?->rate ?? 0);

        return $rate > 0 ? $rate : 1.0;
    }

    private function lineTotal(Income $income, float $receiptRate): float
    {
        return $this->convert((float) $income->amount * (int) $income->quantity, (float) ($income->rate ?: 1), $receiptRate);
    }

    /**
     * Rates are stored as "units of local currency per one base unit", so
     * amount / sourceRate yields the base value and * receiptRate converts it
     * into the receipt currency.
     */
    private function convert(float $amount, float $sourceRate, float $receiptRate): float
    {
        if ($sourceRate <= 0 || $receiptRate <= 0) {
            return $amount;
        }

        return $amount / $sourceRate * $receiptRate;
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
            'country' => $applicant->scholarshipDetail?->country?->name,
            'education_level' => $applicant->scholarshipDetail?->educationLevel?->name,
            'created_at' => $applicant->created_at?->toDateString(),
            'status' => $applicant->status,
        ];
    }

    /** @return array<string, mixed> */
    private function studentPayload(Student $student): array
    {
        return [
            'id' => $student->id,
            'student_number' => $student->student_number,
            'name' => $student->name,
            'father_name' => $student->father_name,
            'contact' => $student->contact,
            'father_phone' => $student->father_phone,
            'email' => $student->email,
            'address' => $student->address,
            'created_at' => $student->created_at?->toDateString(),
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
