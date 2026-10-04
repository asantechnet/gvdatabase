<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ContractService;
use App\Models\CustomerPackage;
use App\Models\Income;
use App\Models\Setting;
use App\Support\DocumentLetterheads;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the printable receipt payload for a single income record.
 *
 * Receipts are the customer-facing proof of payment for an educational visa
 * firm, so they carry the party identity, the case services/packages, a
 * serial number, the total fee, and the running paid amount for the applicant
 * — see RECEIPTS_AND_DOCUMENT_LETTERHEADS_PLAN.md.
 */
class IncomeReceiptService
{
    public function forIncome(Income $income): array
    {
        $income->loadMissing([
            'applicant.country',
            'applicant.educationLevel',
            'incomeCurrency',
            'invoice.currency',
            'recorder',
        ]);

        $setting = Setting::latest('id')->first();
        $currency = $income->incomeCurrency;
        $receiptRate = (float) ($income->rate ?: ($currency?->rate ?? 1)) ?: 1.0;
        $applicant = $income->applicant;

        $services = $applicant ? $this->serviceLines($applicant, $receiptRate) : collect();
        $packages = $applicant ? $this->packageLines($applicant, $receiptRate) : collect();

        $paidAmount = $applicant ? $this->paidAmount($applicant, $receiptRate) : $this->lineTotal($income, $receiptRate);
        // A package-issued contract carries one service line per member service
        // (tagged `package_service`) *and* the package purchase record. They
        // describe the same fee, so count the package once — otherwise a
        // package sale would be billed twice on its receipt.
        $totalFee = $services->where('kind', '!=', 'package_service')->sum('converted_fee')
            + $packages->sum('converted_fee');

        if ($totalFee <= 0 && $income->invoice) {
            $totalFee = $this->convert(
                (float) $income->invoice->amount,
                (float) ($income->invoice->rate ?: 1),
                $receiptRate,
            );
        }

        if ($totalFee <= 0) {
            $totalFee = $this->lineTotal($income, $receiptRate);
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
            'invoice' => $income->invoice ? [
                'id' => $income->invoice->id,
                'invoice_number' => $income->invoice->invoice_number,
                'status' => $income->invoice->status,
                'issue_date' => $income->invoice->issue_date?->toDateString(),
                'due_date' => $income->invoice->due_date?->toDateString(),
                'amount' => (float) $income->invoice->amount,
                'currency' => [
                    'code' => $income->invoice->currency_shortname,
                    'symbol' => $income->invoice->currency_symbol,
                    'decimalPlaces' => max(0, min(8, (int) ($income->invoice->currency?->decimal_places ?? 2))),
                ],
            ] : null,
            'services' => $services->values()->all(),
            'packages' => $packages->values()->all(),
            'totals' => [
                'total_fee' => round($totalFee, $precision),
                'paid_amount' => round($paidAmount, $precision),
                'balance' => round(max(0, $totalFee - $paidAmount), $precision),
                'currency' => $this->currencyPayload($currency),
            ],
            'letterhead' => DocumentLetterheads::resolve($setting, 'receipt'),
            'signature' => [
                'url' => $setting?->company_signature ? Storage::url($setting->company_signature) : null,
                'name' => $setting?->original_company_name ?: $setting?->short_company_name,
            ],
            'recorded_by' => $income->recorder ? ['id' => $income->recorder->id, 'name' => $income->recorder->name] : null,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function serviceLines(Applicant $applicant, float $receiptRate): Collection
    {
        return ContractService::query()
            ->with(['service:id,type,code', 'variant:id,name', 'currency:id,code,symbol,rate,decimal_places'])
            ->where('applicant_id', $applicant->id)
            ->get()
            ->map(function (ContractService $line) use ($receiptRate): array {
                $fee = (float) ($line->fee ?? 0);
                $sourceRate = (float) ($line->currency?->rate ?: 1);

                return [
                    'kind' => $line->customer_package_id ? 'package_service' : 'service',
                    'id' => $line->id,
                    'service_id' => $line->service_id,
                    'name' => $line->variant?->name ?? $line->service?->type ?? 'Service',
                    'fee' => $fee,
                    'rate' => $sourceRate,
                    'converted_fee' => round($this->convert($fee, $sourceRate, $receiptRate), 2),
                    'currency' => [
                        'code' => $line->currency?->code,
                        'symbol' => $line->currency?->symbol,
                        'decimalPlaces' => (int) ($line->currency?->decimal_places ?? 2),
                    ],
                ];
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function packageLines(Applicant $applicant, float $receiptRate): Collection
    {
        return CustomerPackage::query()
            ->with(['package:id,name,code', 'currency:id,code,symbol,rate,decimal_places'])
            ->where('applicant_id', $applicant->id)
            ->get()
            ->map(function (CustomerPackage $record) use ($receiptRate): array {
                $fee = (float) ($record->fee ?? 0);
                $sourceRate = (float) ($record->currency?->rate ?: 1);

                return [
                    'id' => $record->id,
                    'package_id' => $record->package_id,
                    'name' => $record->package?->name ?? 'Package',
                    'code' => $record->package?->code,
                    'fee' => $fee,
                    'status' => $record->status,
                    'purchased_at' => $record->purchased_at?->toDateString(),
                    'converted_fee' => round($this->convert($fee, $sourceRate, $receiptRate), 2),
                    'currency' => [
                        'code' => $record->currency?->code,
                        'symbol' => $record->currency?->symbol,
                        'decimalPlaces' => (int) ($record->currency?->decimal_places ?? 2),
                    ],
                ];
            });
    }

    /** Every payment recorded for the applicant, expressed in the receipt currency. */
    private function paidAmount(Applicant $applicant, float $receiptRate): float
    {
        return (float) Income::query()
            ->with('incomeCurrency:id,rate')
            ->where('applicant_id', $applicant->id)
            ->get()
            ->sum(function (Income $income) use ($receiptRate): float {
                $rate = (float) ($income->rate ?: ($income->incomeCurrency?->rate ?: 1));

                return $this->convert((float) $income->amount * (int) $income->quantity, $rate, $receiptRate);
            });
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
            'tax_number' => $applicant->tax_number,
            'country' => $applicant->country?->name,
            'education_level' => $applicant->educationLevel?->name,
            'application_date' => $applicant->application_date?->toDateString(),
            'status' => $applicant->status,
        ];
    }

    /** @return array<string, mixed> */
    private function companyPayload(?Setting $setting): array
    {
        return [
            'name' => $setting?->original_company_name ?: $setting?->short_company_name ?: $setting?->website_title,
            'short_name' => $setting?->short_company_name,
            'logo_url' => $setting?->website_logo ? Storage::url($setting->website_logo) : null,
            'signature_url' => $setting?->company_signature ? Storage::url($setting->company_signature) : null,
            'address' => $setting?->physical_address,
            'phone' => $setting?->phone,
            'email' => $setting?->company_email,
            'website' => $setting?->website_address,
            'licence' => $setting?->business_licence,
        ];
    }
}
