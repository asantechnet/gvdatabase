<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CustomerService extends Pivot
{
    use Auditable, HasFactory;

    protected $table = 'customer_service';

    public $incrementing = true;

    protected $fillable = [
        'customer_id',
        'service_id',
        'employee_id',
        'marketer_id',
        'fee',
        'currency_id',
        'deadline',
        'status',
        'notes',
    ];

    protected $casts = [
        'deadline' => 'date',
        'fee' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** The employee responsible for delivering this customer's service. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** The employee credited with winning this customer's service. */
    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'marketer_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

}
