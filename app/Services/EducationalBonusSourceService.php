<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractService;
use App\Models\EducationalBonusSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EducationalBonusSourceService
{
    /** @return array<string, class-string<Model>> */
    public function sourceTypes(): array
    {
        return [
            'contract' => Contract::class,
            'service' => ContractService::class,
        ];
    }

    /**
     * Creates the single traceable bonus source for an eligible service.
     * The employee, customer, applicant and fee always come from the source
     * record so callers cannot misattribute a sale to another case.
     *
     * @param  array{bonus_amount?: numeric, rule_source?: string|null, status?: string, source_amount?: numeric}  $bonus
     */
    public function createFor(Model $source, array $bonus = []): EducationalBonusSource
    {
        $employeeId = $source->getAttribute('sold_by_employee_id') ?? $source->getAttribute('employee_id');
        // Contract lines carry the customer on their parent contract.
        $customerId = $source->getAttribute('customer_id') ?? $source->contract?->customer_id;
        $applicantId = $source->getAttribute('applicant_id') ?? $source->contract?->applicant_id;

        if (! $employeeId || ! $customerId) {
            throw ValidationException::withMessages([
                'source_id' => ['The selected source must identify both its selling employee and customer.'],
            ]);
        }

        $sourceAmount = $source->getAttribute('fee');
        if ($source instanceof ContractService && $sourceAmount === null) {
            $sourceAmount = $source->service?->fee;
        }
        if ($sourceAmount === null) {
            $sourceAmount = $bonus['source_amount'] ?? null;
        }
        if ($sourceAmount === null || (float) $sourceAmount <= 0) {
            throw ValidationException::withMessages([
                'source_amount' => ['A positive source amount is required for this source.'],
            ]);
        }

        $attributes = [
            'employee_id' => $employeeId,
            'customer_id' => $customerId,
            'applicant_id' => $applicantId,
            'source_amount' => $sourceAmount,
            'bonus_amount' => $bonus['bonus_amount'] ?? 0,
            'status' => $bonus['status'] ?? 'pending',
            'rule_source' => $bonus['rule_source'] ?? 'manual_rule_pending',
        ];

        return EducationalBonusSource::query()->firstOrCreate([
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
            'employee_id' => $employeeId,
        ], $attributes);
    }

    public function syncForService(Model $source): void
    {
        $source->bonusSource()->update([
            'employee_id' => $source->sold_by_employee_id ?? $source->employee_id ?? null,
            'customer_id' => $source->customer_id ?? $source->contract?->customer_id,
            'applicant_id' => $source->applicant_id ?? $source->contract?->applicant_id,
            'source_amount' => $source->fee ?? $source->service?->fee,
        ]);
    }
}
