<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company',
        'contact',
        'marketer',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class)
            ->using(CustomerService::class)
            ->withPivot('id', 'deadline')
            ->withTimestamps();
    }

    public function customerServices()
    {
        return $this->hasMany(CustomerService::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
