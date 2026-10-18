<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAttendanceSummary extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'present_days' => 'float', 'absent_days' => 'float',
            'paid_leave_days' => 'float', 'off_days' => 'float', 'unpaid_leave_days' => 'float',
            'calculated_at' => 'immutable_datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
