<?php

namespace App\Http\Resources;

use App\Models\Contract;
use App\Support\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'service' => $this->service,
            'contract_role' => $this->contract_role,
            'parent_contract_id' => $this->parent_contract_id,
            'parent_contract_number' => $this->whenLoaded('parentContract', fn () => $this->parentContract?->contract_number),
            // Derived presentation only: the stored status is never relabelled.
            'is_scheduled' => $this->isScheduled(),
            'display_status' => $this->isScheduled() ? 'scheduled' : $this->status,
            'contract' => $this->name ?? $this->template?->name ?? 'Untitled Contract',
            'type' => $this->type,
            'contract_type_id' => $this->contract_type_id,
            'services' => ApplicantContractResource::collection($this->whenLoaded('applicantContracts')),
            'type_slug' => $this->type_slug,
            'status' => strtolower((string) $this->status),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days_remaining' => $this->daysRemaining(),
            'expiry_state' => $this->expiryState(),
            'company_signatory_name' => $this->company_signatory_name,
            'counterparty' => $this->counterparty,
            'counterparty_company' => $this->counterparty_company,
            'counterparty_email' => $this->counterparty_email,
            'counterparty_contact' => $this->counterparty_contact,
            'counterparty_address' => $this->counterparty_address,
            'is_cancelled' => $this->is_cancelled || $this->status === 'cancelled',
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'template_id' => $this->template_id,
            'contract_template_version_id' => $this->contract_template_version_id,
            'contract_country_id' => $this->contract_country_id,
            'contract_language_id' => $this->contract_language_id,
            'employee_id' => $this->employee_id,
            'sold_by_employee_id' => $this->sold_by_employee_id,
            'applicant_id' => $this->applicant_id,
            'cover_text' => $this->cover_text,
            'content' => $this->content,
            'snapshot_data' => $this->snapshotWithDocumentUrls(),
            'is_custom' => $this->is_custom,
            // Service-line payment state, read from the already-calculated line
            // values. Viewers without case-finance access never receive it, so
            // the register can omit the column instead of showing dashes.
            'payment' => $this->when(
                $request->user()?->hasPermission('view_applicant_financials') === true,
                fn () => $this->paymentSummary(),
            ),

            // Snapshot of the counterparty at issue time; historical
            // contracts stay renderable even if the source record changes.
            'parties' => $this->snapshot_data['parties'] ?? null,

            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'applicant' => new ApplicantResource($this->whenLoaded('applicant')),
            'contract_type' => new ContractTypeResource($this->whenLoaded('contractType')),
            'contract_template' => $this->whenLoaded('template', function () {
                return new ContractTemplateResource($this->template);
            }),
            'contract_template_version' => $this->whenLoaded('templateVersion', fn () => ['id' => $this->templateVersion?->id, 'name' => $this->templateVersion?->name, 'country_id' => $this->templateVersion?->country_id, 'language_id' => $this->templateVersion?->language_id, 'version_number' => $this->templateVersion?->version_number, 'status' => $this->templateVersion?->status]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /** Days until the end date; negative once it has passed. */
    private function daysRemaining(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false);
    }

    /** Expiry badge state; only a live active contract can be expiring/expired. */
    private function expiryState(): string
    {
        if ($this->status !== 'active' || $this->is_cancelled) {
            return 'none';
        }

        $days = $this->daysRemaining();

        if ($days === null) {
            return 'none';
        }

        if ($days < 0) {
            return 'expired';
        }

        return $days <= 30 ? 'expiring' : 'none';
    }

    /**
     * An extension sold before its start date is not effective yet; it keeps
     * its stored status but is presented as scheduled.
     */
    private function isScheduled(): bool
    {
        return $this->contract_role === Contract::ROLE_EXTENSION
            && $this->start_date !== null
            && $this->start_date->toDateString() > now()->toDateString();
    }

    /**
     * Payment state for the register: the contract's service lines, or a
     * defined not-applicable state for employee/other agreements.
     */
    private function paymentSummary(): array
    {
        $lines = $this->relationLoaded('applicantContracts') ? $this->applicantContracts : collect();

        if ($lines->isEmpty()) {
            return ['applicable' => false, 'status' => null, 'outstanding' => null, 'currency' => null, 'lines' => 0];
        }

        $payable = (float) $lines->sum(fn ($line) => $line->payableTotal());
        $paid = (float) $lines->sum(fn ($line) => (float) $line->amount_paid);
        $currency = $lines->first()->currency;

        return [
            'applicable' => true,
            'status' => PaymentStatus::derive($paid, $payable),
            'outstanding' => round((float) $lines->sum(fn ($line) => $line->remainingTotal()), 2),
            'currency' => $currency ? ['code' => $currency->code, 'symbol' => $currency->symbol] : null,
            'lines' => $lines->count(),
        ];
    }

    private function snapshotWithDocumentUrls(): ?array
    {
        $snapshot = $this->snapshot_data;
        if (! is_array($snapshot) || ! isset($snapshot['document_design']) || ! is_array($snapshot['document_design'])) {
            return $snapshot;
        }

        foreach (['letterhead', 'cover_design'] as $field) {
            $path = $snapshot['document_design'][$field] ?? null;
            $snapshot['document_design']["{$field}_url"] = $path ? Storage::disk('public')->url($path) : null;
        }

        return $snapshot;
    }
}
