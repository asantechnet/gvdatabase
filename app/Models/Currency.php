<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'symbol',
        'rate',
        'is_base',
        'is_active',
        'decimal_places',
    ];

    protected $casts = [
        'rate' => 'float',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($currency) {
            if ($currency->is_base) {
                $currency->rate = 1.0;
                $currency->is_active = true;
            }
        });

        static::updating(function ($currency) {
            if ($currency->isDirty('is_base') && $currency->is_base) {
                $currency->rate = 1.0;
                $currency->is_active = true;
            }
        });
    }

    public function applicantContracts()
    {
        return $this->hasMany(ApplicantContract::class);
    }

    public function salaries()
    {
        return $this->hasMany(Salary::class);
    }

    public function incomes()
    {
        return $this->hasMany(Income::class, 'currency_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'currency_id');
    }
}
