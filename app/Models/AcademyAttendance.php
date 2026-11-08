<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyAttendance extends Model
{
    public const STATUSES = ['present', 'absent', 'late', 'excused'];

    protected $fillable = ['academy_enrollment_id', 'class_id', 'date', 'status', 'minutes', 'note', 'recorded_by'];

    protected $casts = ['date' => 'date:Y-m-d', 'minutes' => 'integer'];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(AcademyEnrollment::class, 'academy_enrollment_id');
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
