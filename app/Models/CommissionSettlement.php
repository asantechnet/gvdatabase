<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionSettlement extends Model
{
    use Auditable, HasDocumentNumber;

    protected $fillable = [
        'employee_id', 'base_currency_id', 'period_start', 'period_end',
        'gross_base_amount', 'status', 'notes', 'recorded_by', 'approved_by',
        'approved_at', 'paid_by', 'payment_date', 'paid_at', 'expense_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'payment_date' => 'date',
            'approved_at' => 'datetime', 'paid_at' => 'datetime',
            'gross_base_amount' => 'decimal:6',
        ];
    }

    protected function documentNumberColumn(): string
    {
        return 'settlement_number';
    }

    protected function documentNumberPrefix(): string
    {
        return 'COM';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(CommissionAccrual::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
