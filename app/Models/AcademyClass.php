<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademyClass extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'academy_classes';

    protected $fillable = [
        'name', 'description', 'is_active',
        'program_id', 'starts_on', 'ends_on', 'completed_at',
        'weekdays', 'starts_at', 'ends_at', 'capacity', 'fee',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weekdays' => 'array',
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'capacity' => 'integer',
        'fee' => 'decimal:2',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'academy_class_teacher', 'academy_class_id', 'teacher_id')
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(AcademyEnrollment::class, 'class_id');
    }

    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->where('status', 'active');
    }
}
