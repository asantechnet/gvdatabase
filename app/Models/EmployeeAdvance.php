<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvance extends Model
{
    use Auditable, HasDocumentNumber;

    protected $fillable = [
        'advance_number',
        'employee_id',
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
            'rate' => 'decimal:8',
            'advance_date' => 'date',
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
