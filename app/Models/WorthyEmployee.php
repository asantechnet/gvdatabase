<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorthyEmployee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'performance_score',
        'eligibility_reason',
        'achievement_details',
        'suggested_amount',
        'selected_amount',
        'selected_type',
        'is_granted',
        'bonus_month',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaries(): BelongsToMany
    {
        return $this->belongsToMany(Salary::class, 'salary_worthy_employee')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'performance_score' => 'float',
            'suggested_amount' => 'float',
            'selected_amount' => 'float',
            'is_granted' => 'boolean',
            'bonus_month' => 'date',
        ];
    }
}
