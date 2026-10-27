<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'date',
        'status', 'off_day_id', 'leave_request_id', 'source', 'leave_days', 'off_day_days',
        'logged_at',
        'check_in_time',
        'check_out_time',
        'checked_in_at',
        'checked_out_at',
        'office_id', 'break_minutes', 'gross_minutes', 'net_worked_minutes', 'required_minutes',
        'late_arrival_minutes', 'early_departure_minutes', 'overtime_minutes', 'missing_punch_state',
        'is_overnight_shift', 'calculation_hash', 'calculated_at',
        'check_in_latitude', 'check_in_longitude', 'check_in_accuracy_meters',
        'check_in_distance_meters', 'check_in_source', 'check_out_latitude',
        'check_out_longitude', 'check_out_accuracy_meters', 'check_out_distance_meters',
        'check_out_source', 'geofence_exit_detected_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'leave_days' => 'float', 'off_day_days' => 'float',
            'checked_in_at' => 'immutable_datetime',
            'checked_out_at' => 'immutable_datetime',
            'break_minutes' => 'integer', 'gross_minutes' => 'integer', 'net_worked_minutes' => 'integer',
            'required_minutes' => 'integer', 'late_arrival_minutes' => 'integer', 'early_departure_minutes' => 'integer',
            'overtime_minutes' => 'integer', 'is_overnight_shift' => 'boolean', 'calculated_at' => 'immutable_datetime',
            'check_in_latitude' => 'decimal:7', 'check_in_longitude' => 'decimal:7',
            'check_in_accuracy_meters' => 'integer', 'check_in_distance_meters' => 'integer',
            'check_out_latitude' => 'decimal:7', 'check_out_longitude' => 'decimal:7',
            'check_out_accuracy_meters' => 'integer', 'check_out_distance_meters' => 'integer',
            'geofence_exit_detected_at' => 'immutable_datetime',
        ];
    }

    /**
     * Attendance belongs to an Employee.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function dailyReport(): HasOne
    {
        return $this->hasOne(DailyReport::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withTrashed();
    }

    /** The announced company holiday that turned this day into an off day. */
    public function offDay(): BelongsTo
    {
        return $this->belongsTo(OffDay::class);
    }

    /** The approved employee request that turned this day into an off day. */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * Scope to filter records within a specific date range.
     */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
