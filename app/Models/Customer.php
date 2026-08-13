<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'company',
        'contact',
        'email',
        'address',
        'tax_number',
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
            ->wherePivotNull('deleted_at')
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
