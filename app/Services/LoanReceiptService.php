<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\PendingTransaction;
use App\Models\Setting;
use App\Support\DocumentLetterheads;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the printable receipt payload for a loan request.
 *
 * "Loans" are pending transactions (see PendingTransactionController), so this
 * mirrors IncomeReceiptService but swaps the contracted-service data for the
 * request data. The payload intentionally keeps the receipt shape (income /
 * totals / company / applicant / letterhead) so the frontend reuses the same
 * A5 receipt template and print pipeline for both documents.
 */
class LoanReceiptService
{
    public function for(PendingTransaction $loan): array
    {
        $loan->loadMissing([
            'currency',
            'applicant.scholarshipDetail.country',
            'applicant.scholarshipDetail.educationLevel',
            'requester',
            'approver',
        ]);

        $setting = Setting::latest('id')->first();
        $currency = $loan->currency;
        $rate = (float) ($currency?->rate ?? 0) ?: 1.0;
        $amount = (float) $loan->amount;
        $precision = max(0, min(8, (int) ($currency?->decimal_places ?? 2)));
        $approved = in_array($loan->approval_status, [PendingTransaction::APPROVED, PendingTransaction::POSTED], true);
        $reason = trim((string) $loan->reason) ?: null;
        $requestedAt = $loan->requested_at ?? $loan->created_at;

        return [
            'serial_number' => $this->serial($loan),
            'document' => 'loan',
            'date' => $requestedAt?->toDateString(),
            'income' => [
                'id' => $loan->id,
                'name' => $reason ?? $loan->transaction_type,
                'type' => $loan->transaction_type,
                'description' => $reason,
                'amount' => $amount,
                'quantity' => 1,
                'line_total' => $amount,
                'rate' => $rate,
                'currency' => $this->currencyPayload($currency),
                'base_amount' => round($amount / $rate, $precision),
            ],
            'company' => $this->companyPayload($setting),
            'applicant' => $loan->applicant ? $this->applicantPayload($loan->applicant) : null,
            'contract' => null,
            'service' => null,
            'services' => $reason ? [[
                'kind' => 'loan',
                'id' => $loan->id,
                'name' => $reason,
                'fee' => $amount,
                'converted_fee' => $amount,
                'currency' => [
                    'code' => $currency?->code,
                    'symbol' => $currency?->symbol,
                    'decimalPlaces' => $precision,
                ],
            ]] : [],
            'totals' => [
                'total_fee' => round($amount, $precision),
                'paid_amount' => round($approved ? $amount : 0, $precision),
                'balance' => round($approved ? 0 : $amount, $precision),
                'currency' => $this->currencyPayload($currency),
            ],
            'letterhead' => DocumentLetterheads::resolve($setting, 'receipt'),
            'signature' => [
                'url' => $setting?->company_signature ? Storage::url($setting->company_signature) : null,
                'name' => $setting?->original_company_name ?: $setting?->short_company_name,
            ],
            'recorded_by' => $loan->requester ? ['id' => $loan->requester->id, 'name' => $loan->requester->name] : null,
            'loan' => [
                'status' => $loan->approval_status,
                'transaction_type' => $loan->transaction_type,
                'requested_at' => $loan->requested_at?->toDateString(),
                'approved_at' => $loan->approved_at?->toDateString(),
                'approver' => $loan->approver ? ['id' => $loan->approver->id, 'name' => $loan->approver->name] : null,
                'rejection_reason' => $loan->rejection_reason,
            ],
        ];
    }

    /** A stable reference so a reprint of the same loan never changes number. */
    private function serial(PendingTransaction $loan): string
    {
        $requestedAt = $loan->requested_at ?? $loan->created_at;
        $year = $requestedAt?->format('Y') ?? date('Y');

        return sprintf('LN-%s-%06d', $year, $loan->id);
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
            'country' => $applicant->scholarshipDetail?->country?->name,
            'education_level' => $applicant->scholarshipDetail?->educationLevel?->name,
            'created_at' => $applicant->created_at?->toDateString(),
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
