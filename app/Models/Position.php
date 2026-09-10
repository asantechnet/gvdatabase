<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'salary',
        'currency_id', 'currency_code', 'currency_symbol', 'currency_decimal_places', 'rate',
        'status',
        'system_key',
    ];

    protected function casts(): array
    {
        return [
            'salary' => 'float',
            'currency_id' => 'integer',
            'currency_decimal_places' => 'integer',
            'rate' => 'float',
        ];
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->using(PositionUser::class)
            ->withPivot('assigned_at', 'ended_at')
            ->withTimestamps();
    }

    public function contractTemplate()
    {
        return $this->hasOne(ContractTemplate::class);
    }
}
