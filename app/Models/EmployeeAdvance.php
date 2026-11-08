<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAdvance extends Model
{
    use Auditable, HasDocumentNumber;

    /** Nothing has been paid back yet. */
    public const STATUS_OUTSTANDING = 'outstanding';

    /** Some of the debt has been paid back. */
    public const STATUS_PARTIAL = 'partial';

    /** The whole debt is paid back; the record is closed. */
    public const STATUS_SETTLED = 'settled';

    protected $fillable = [
        'advance_number',
        'employee_id',
        'salary_id',
        'recovered_at',
        'settled_amount',
        'status',
        'currency_id',
        'base_currency_id',
        'currency_code',
        'currency_symbol',
        'rate',
        'amount',
        'base_amount',
        'advance_date',
        'remarks',
        'expense_id',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'base_amount' => 'float',
            'settled_amount' => 'float',
            'rate' => 'decimal:8',
            'advance_date' => 'date',
            'recovered_at' => 'immutable_datetime',
        ];
    }

    protected function documentNumberColumn(): string
    {
        return 'advance_number';
    }

    protected function documentNumberPrefix(): string
    {
        return 'ADV';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /** The payroll period that recovered this advance, if any. */
    public function salary(): BelongsTo
    {
        return $this->belongsTo(Salary::class);
    }

    /** Every payroll deduction and cash repayment that reduced the debt. */
    public function settlements(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceSettlement::class)->orderBy('date')->orderBy('id');
    }

    /** What is still owed on the advance. */
    public function remainingAmount(): float
    {
        return max(0, round((float) $this->amount - (float) $this->settled_amount, 8));
    }

    /**
     * Recompute the cached balance and close the record once nothing is owed.
     * The status is always derived, never set by hand.
     */
    public function refreshSettlementState(): void
    {
        $settled = round((float) $this->settlements()->sum('amount'), 8);
        $amount = round((float) $this->amount, 8);

        $status = match (true) {
            $settled <= 0.00000001 => self::STATUS_OUTSTANDING,
            $settled + 0.00000001 >= $amount => self::STATUS_SETTLED,
            default => self::STATUS_PARTIAL,
        };

        $this->forceFill([
            'settled_amount' => $settled,
            'status' => $status,
        ])->saveQuietly();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
