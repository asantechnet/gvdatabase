<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Salary extends Model
{
    use Auditable, HasDocumentNumber, HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'currency_id',
        'base_currency_id',
        'currency_code', 'currency_name', 'currency_symbol', 'currency_decimal_places', 'cnv_curr_shrt', 'base_curr_shrt', 'rate', 'base_amount',
        'currency_snapshot_source', 'currency_snapshot_note',
        'salary_month',
        'amount_paid',
        'bonus',
        'worked_days',
        'paid_leave_days',
        'absent_days',
        'unpaid_leave_days',
        'total_days',
        'total_amount',
        'remarks',
        'payment_date',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'float',
            'bonus' => 'float',
            'total_amount' => 'float',
            'worked_days' => 'float',
            'paid_leave_days' => 'float',
            'absent_days' => 'float',
            'unpaid_leave_days' => 'float',
            'total_days' => 'integer',
            'payment_date' => 'date',
            'rate' => 'decimal:8', 'base_amount' => 'decimal:4',
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
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bonuses()
    {
        return $this->belongsToMany(WorthyEmployee::class, 'salary_worthy_employee')
            ->withPivot(['source_amount', 'source_currency_code', 'source_rate', 'salary_currency_code', 'salary_rate', 'converted_amount', 'conversion_formula', 'conversion_snapshot_source'])
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
