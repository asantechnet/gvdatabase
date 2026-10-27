<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An employee's request for a day (or half day) away from work.
 *
 * `off_day_days` is the part covered by the employee's annual allowance (or an
 * explicit exception). The remainder of `duration_days` is recorded as an
 * absent attendance day when the request is granted. The `leaves` table name is
 * retained because it is the canonical HR leave ledger.
 */
class LeaveRequest extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'leaves';

    protected $fillable = [
        'employee_id', 'date', 'year', 'duration_days', 'off_day_days', 'off_day_override',
        'reason', 'approval_note', 'is_granted', 'requested_by', 'granted_by', 'granted_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'year' => 'integer',
            'duration_days' => 'float',
            'off_day_days' => 'float',
            'is_granted' => 'boolean',
            'off_day_override' => 'boolean',
            'granted_at' => 'immutable_datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
