<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An organisational group inside the firm (for example "Visa Counselors" or
 * "Finance"). A department may name several senior managers; membership, not
 * a reporting line, is what defines the team.
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

    /** Every senior employee who co-manages this department. */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'department_manager')
            ->withTimestamps()
            ->orderBy('employees.name');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'department_employee')
            ->withTimestamps();
    }

    public function board(): HasOne
    {
        return $this->hasOne(Board::class);
    }
}
