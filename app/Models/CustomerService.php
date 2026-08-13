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
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
