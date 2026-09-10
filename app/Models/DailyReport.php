<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyReport extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'report_date',
        'summary',
        'achievements',
        'challenges',
        'next_day_plan',
        'support_needed',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date:Y-m-d',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
