<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    use HasDocumentNumber, HasFactory, Auditable;

    protected $fillable = [
        'receipt_number',
        'customer_id',
        'invoice_id',
        'currency_id',
        'type',
        'description',
        'amount',
        'quantity',
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
        'currency_id' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function incomeCurrency()
    {
        return $this->belongsTo(Currency::class);
    }

    protected function documentNumberColumn(): string
    {
        return 'receipt_number';
    }

    protected function documentNumberPrefix(): string
    {
        return 'RCP';
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
