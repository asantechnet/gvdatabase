<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single earned commission, or a manual adjustment, owed to an employee.
 *
 * Accruals are produced from the service lines of a case that has reached the
 * terminal `completed` stage (see `CommissionService`) and grouped into
 * settlement periods by the commission workspace. The pre-restructure ledger
 * keyed this on invoices; this one keys on the contract and its
 * `applicant_contract` line.
 */
class CommissionAccrual extends Model
{
    use Auditable;

    protected $fillable = [
        'contract_id',
        'applicant_contract_id',
        'employee_id',
        'service',
        'base_currency_id',
        'commission_settlement_id',
        'entry_type',
        'earned_at',
        'source_base_amount',
        'eligible_base_amount',
        'percentage',
        'commission_base_amount',
        'adjustment_reason',
        'recorded_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'earned_at' => 'date',
            'source_base_amount' => 'decimal:6',
            'eligible_base_amount' => 'decimal:6',
            'percentage' => 'decimal:2',
            'commission_base_amount' => 'decimal:6',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withTrashed();
    }

    public function applicantContract(): BelongsTo
    {
        return $this->belongsTo(ApplicantContract::class, 'applicant_contract_id')->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class, 'commission_settlement_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
