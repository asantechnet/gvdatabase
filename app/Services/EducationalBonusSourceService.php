<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\EducationalBonusSource;
use App\Models\Scholarship;
use App\Models\TestOffer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EducationalBonusSourceService
{
    /** @return array<string, class-string<Model>> */
    public function sourceTypes(): array
    {
        return [
            'contract' => Contract::class,
            'scholarship' => Scholarship::class,
            'test_offer' => TestOffer::class,
        ];
    }

    /**
     * Creates the single traceable bonus source for an eligible service.
     * The employee, customer, applicant and fee always come from the source
     * record so callers cannot misattribute a sale to another case.
     *
     * @param array{bonus_amount?: numeric, rule_source?: string|null, status?: string, source_amount?: numeric} $bonus
     */
    public function createFor(Model $source, array $bonus = []): EducationalBonusSource
    {
        $employeeId = $source->getAttribute('employee_id');
        $customerId = $source->getAttribute('customer_id');

        if (! $employeeId || ! $customerId) {
            throw ValidationException::withMessages([
                'source_id' => ['The selected source must identify both its selling employee and customer.'],
            ]);
        }

        $sourceAmount = $source->getAttribute('fee');
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
            'applicant_id' => $source->getAttribute('applicant_id'),
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
            'employee_id' => $source->employee_id,
            'customer_id' => $source->customer_id,
            'applicant_id' => $source->applicant_id,
            'source_amount' => $source->fee,
        ]);
    }
}
