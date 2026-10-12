<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee's commission rate for one built-in service.
 *
 * The service is stored as the `BuiltInService` string (scholarship,
 * test_offer, academy, private) because the old `services` table is retired.
 */
class EmployeeServiceCommission extends Model
{
    use Auditable;

    protected $fillable = [
        'employee_id',
        'service',
        'percentage',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'employee_id' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
