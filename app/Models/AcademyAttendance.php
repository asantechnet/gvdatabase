<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyAttendance extends Model
{
    public const STATUSES = ['present', 'absent', 'leave'];

    protected $fillable = ['academy_enrollment_id', 'class_id', 'date', 'status', 'check_in_time', 'check_out_time', 'recorded_by'];

    protected $casts = ['date' => 'date:Y-m-d'];

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
