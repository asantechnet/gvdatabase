<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRateHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency_id',
        'base_currency_id',
        'rate',
        'reason',
        'recorded_by',
    ];

    protected $casts = [
        'rate' => 'float',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }
}
