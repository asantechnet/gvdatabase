<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidTimeOff extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['employee_id', 'date', 'year', 'reason', 'is_granted', 'is_paid', 'requested_by', 'granted_by', 'granted_at'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'year' => 'integer', 'is_granted' => 'boolean', 'is_paid' => 'boolean', 'granted_at' => 'immutable_datetime'];
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
