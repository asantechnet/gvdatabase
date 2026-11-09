<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyTeacherTimesheet extends Model
{
    public const STATUSES = ['present', 'absent'];

    protected $fillable = ['teacher_id', 'class_id', 'date', 'status', 'starts_at', 'ends_at', 'minutes', 'note', 'recorded_by'];

    protected $casts = ['date' => 'date:Y-m-d', 'minutes' => 'integer'];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    public function academyClass(): BelongsTo
    {
        return $this->belongsTo(AcademyClass::class, 'class_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
