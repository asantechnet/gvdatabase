<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorthyEmployee extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'employee_id',
        'source_task_id', 'is_automatic',
        'calculation_key', 'calculation_score', 'calculation_inputs', 'rule_snapshot', 'calculated_at',
        'granted_at', 'granted_by',
        'currency_id', 'currency_code', 'currency_name', 'currency_symbol', 'currency_decimal_places',
        'performance_score',
        'eligibility_reason',
        'achievement_details',
        'suggested_amount',
        'suggested_rate_to_base', 'suggested_base_amount',
        'selected_amount',
        'selected_currency_id', 'selected_currency_code', 'selected_currency_name', 'selected_currency_symbol',
        'selected_currency_decimal_places', 'selected_rate_to_base', 'selected_base_amount',
        'selected_type',
        'is_granted',
        'granted_amount', 'granted_currency_id', 'granted_currency_code', 'granted_currency_name',
        'granted_currency_symbol', 'granted_currency_decimal_places', 'granted_rate_to_base', 'granted_base_amount',
        'currency_snapshot_source', 'currency_snapshot_note',
        'bonus_month',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaries(): BelongsToMany
    {
        return $this->belongsToMany(Salary::class, 'salary_worthy_employee')
            ->withPivot(['source_amount', 'source_currency_code', 'source_rate_to_base', 'base_amount', 'salary_currency_code', 'salary_rate_to_base', 'converted_amount', 'conversion_formula', 'conversion_snapshot_source'])
            ->withTimestamps();
    }

    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
    public function selectedCurrency(): BelongsTo { return $this->belongsTo(Currency::class, 'selected_currency_id'); }
    public function grantedCurrency(): BelongsTo { return $this->belongsTo(Currency::class, 'granted_currency_id'); }

    protected function casts(): array
    {
        return [
            'performance_score' => 'float',
            'suggested_amount' => 'float',
            'selected_amount' => 'float',
            'suggested_rate_to_base' => 'float',
            'suggested_base_amount' => 'float',
            'selected_rate_to_base' => 'float',
            'selected_base_amount' => 'float',
            'granted_amount' => 'float',
            'granted_rate_to_base' => 'float',
            'granted_base_amount' => 'float',
            'currency_decimal_places' => 'integer',
            'selected_currency_decimal_places' => 'integer',
            'granted_currency_decimal_places' => 'integer',
            'is_granted' => 'boolean',
            'is_automatic' => 'boolean',
            'calculation_score' => 'float',
            'calculation_inputs' => 'array',
            'rule_snapshot' => 'array',
            'calculated_at' => 'immutable_datetime',
            'granted_at' => 'immutable_datetime',
            'bonus_month' => 'date',
        ];
    }
}
