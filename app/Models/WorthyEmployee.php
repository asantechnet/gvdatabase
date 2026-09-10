<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorthyEmployee extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'currency_id', 'base_currency_id', 'currency_code', 'currency_name', 'currency_symbol', 'currency_decimal_places', 'base_currency_code', 'rate', 'base_amount',
        'currency_snapshot_source',
        'source_task_id', 'is_automatic',
        'calculation_key', 'calculation_score', 'calculation_inputs', 'rule_snapshot', 'calculated_at',
        'granted_at', 'granted_by',
        'performance_score',
        'achievement_details',
        'suggested_amount',
        'selected_amount',
        'selected_type',
        'is_granted',
        'granted_amount',
        'bonus_month',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function salaries(): BelongsToMany
    {
        return $this->belongsToMany(Salary::class, 'salary_worthy_employee')
            ->withPivot(['source_amount'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'performance_score' => 'float',
            'suggested_amount' => 'float',
            'selected_amount' => 'float',
            'granted_amount' => 'float',
            'rate' => 'float',
            'currency_decimal_places' => 'integer',
            'base_amount' => 'float',
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
