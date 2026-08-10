<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'symbol',
        'rate_against_base',
        'is_base',
        'is_active',
        'decimal_places',
    ];

    protected $casts = [
        'rate_against_base' => 'float',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($currency) {
            if ($currency->is_base) {
                $currency->rate_against_base = 1.0;
                $currency->is_active = true;
            }
        });

        static::updating(function ($currency) {
            if ($currency->isDirty('is_base') && $currency->is_base) {
                $currency->rate_against_base = 1.0;
                $currency->is_active = true;
            }
        });
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function salaries()
    {
        return $this->hasMany(Salary::class);
    }
}
