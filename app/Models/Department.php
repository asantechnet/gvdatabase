<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An organisational group inside the firm (for example "Visa Counselors" or
 * "Finance"). A department names the employee who heads it; the head and each
 * employee's `supervisor_id` define whose records a senior role may see.
 */
class Department extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'manager_employee_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    /**
     * Every member, including employees whose primary department is another
     * one. `is_primary` on the pivot matches `employees.department_id`.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'department_employee')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
