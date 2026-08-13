<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Salary extends Model
{
    use HasDocumentNumber, HasFactory, Auditable;

    protected $fillable = [
        'employee_id',
        'currency_id',
        'currency_code', 'currency_name', 'currency_symbol', 'currency_decimal_places',
        'exchange_rate_to_base', 'amount_paid_base', 'bonus_base', 'total_amount_base',
        'currency_snapshot_source', 'currency_snapshot_note',
        'salary_month',
        'amount_paid',
        'bonus',
        'worked_days',
        'paid_off_days',
        'absent_days',
        'unpaid_off_days',
        'total_days',
        'total_amount',
        'remarks',
        'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'float',
            'bonus' => 'float',
            'total_amount' => 'float',
            'worked_days' => 'integer',
            'paid_off_days' => 'integer',
            'absent_days' => 'integer',
            'unpaid_off_days' => 'integer',
            'total_days' => 'integer',
            'payment_date' => 'date',
            'exchange_rate_to_base' => 'decimal:8',
            'amount_paid_base' => 'decimal:4',
            'bonus_base' => 'decimal:4',
            'total_amount_base' => 'decimal:4',
            'currency_decimal_places' => 'integer',
        ];
    }

    protected function documentNumberColumn(): string
    {
        return 'payslip_number';
    }

    protected function documentNumberPrefix(): string
    {
        return 'PAY';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class); // Make sure you have an Employee model
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bonuses()
    {
        return $this->belongsToMany(WorthyEmployee::class, 'salary_worthy_employee')
            ->withPivot(['source_amount', 'source_currency_code', 'source_rate_to_base', 'base_amount', 'salary_currency_code', 'salary_rate_to_base', 'converted_amount', 'conversion_formula', 'conversion_snapshot_source'])
            ->withTimestamps();
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(SalaryDeduction::class)->orderBy('sort_order');
    }

    public function expense()
    {
        return $this->hasOne(Expense::class);
    }
}
