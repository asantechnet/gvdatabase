<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidTimeOff extends Model
{
    use Auditable, HasFactory;

    protected $table = 'leaves';

    protected $fillable = ['employee_id', 'date', 'year', 'duration_days', 'reason', 'approval_note', 'is_granted', 'is_paid', 'paid_days', 'paid_override', 'requested_by', 'granted_by', 'granted_at'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'year' => 'integer', 'duration_days' => 'float', 'is_granted' => 'boolean', 'is_paid' => 'boolean', 'paid_days' => 'float', 'paid_override' => 'boolean', 'granted_at' => 'immutable_datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
