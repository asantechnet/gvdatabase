<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'type',
        'duration',
        'fee',
        'status',
        'currency_id',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
    ];

    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
        ];
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function customers()
    {
        return $this->belongsToMany(Customer::class)
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
        return $this->belongsToMany(Invoice::class)
            ->using(InvoiceService::class)
            ->withPivot([
                'snapshot_name',
                'snapshot_service_fee',
                'snapshot_service_currency_code',
                'snapshot_service_currency_symbol',
                'snapshot_service_currency_rate',
                'snapshot_invoice_currency_rate',
                'snapshot_converted_fee',
                'quantity',
                'line_total',
            ])
            ->withTimestamps();
    }
}
