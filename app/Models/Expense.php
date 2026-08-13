<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'customer_id',
        'salary_id',
        'currency_id',
        'currency',
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
        'currency_id' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function expenseCurrency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function salary()
    {
        return $this->belongsTo(Salary::class);
    }

    protected static function booted()
    {
        static::creating(function ($expense) {
            $expense->total_amount = $expense->amount * $expense->quantity * $expense->rate_to_base;
        });

        static::updating(function ($expense) {
            if ($expense->isDirty(['amount', 'quantity', 'rate_to_base'])) {
                $expense->total_amount = $expense->amount * $expense->quantity * $expense->rate_to_base;
            }
        });
    }
}
