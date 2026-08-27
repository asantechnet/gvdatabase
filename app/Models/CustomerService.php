<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CustomerService extends Pivot
{
    use Auditable, HasFactory;

    protected $table = 'customer_service';

    public $incrementing = true;

    protected $fillable = [
        'customer_id',
        'service_id',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
