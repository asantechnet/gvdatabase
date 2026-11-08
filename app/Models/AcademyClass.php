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
        'name', 'description', 'sort_order', 'is_active',
        'program_id', 'time_id', 'duration_id', 'starts_on', 'ends_on',
        'weekdays', 'starts_at', 'ends_at', 'capacity', 'fee',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'weekdays' => 'array',
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'capacity' => 'integer',
        'fee' => 'decimal:2',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function time(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class, 'time_id');
    }

    public function duration(): BelongsTo
    {
        return $this->belongsTo(Duration::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'academy_class_teacher', 'academy_class_id', 'teacher_id')
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
