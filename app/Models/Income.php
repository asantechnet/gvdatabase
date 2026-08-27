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
        'cnv_curr_shrt', 'base_curr_shrt', 'rate', 'base_amount',
        'date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'integer',
        'rate' => 'decimal:8',
        'base_amount' => 'decimal:4',
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
            $income->base_amount = $income->amount * $income->quantity * $income->rate;
        });

        static::updating(function ($income) {
            if ($income->isDirty(['amount', 'quantity', 'rate'])) {
                $income->base_amount = $income->amount * $income->quantity * $income->rate;
            }
        });
    }
}
