<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'type',
        'description',
        'amount',
        'quantity',
        'currency',
        'rate_to_base',
        'total_amount',
        'date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'integer',
        'rate_to_base' => 'decimal:6',
        'total_amount' => 'decimal:2',
        'date' => 'date',
        'customer_id' => 'integer',
        'invoice_id' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    protected static function booted()
    {
        static::creating(function ($income) {
            $income->total_amount = $income->amount * $income->quantity * $income->rate_to_base;
        });

        static::updating(function ($income) {
            if ($income->isDirty(['amount', 'quantity', 'rate_to_base'])) {
                $income->total_amount = $income->amount * $income->quantity * $income->rate_to_base;
            }
        });
    }
}
