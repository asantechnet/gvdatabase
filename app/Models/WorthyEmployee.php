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
        'performance_score',
        'eligibility_reason',
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
