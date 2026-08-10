<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class InvoiceService extends Pivot
{
    protected $table = 'invoice_service';

    public $incrementing = true;

    protected $fillable = [
        'invoice_id',
        'service_id',
        'snapshot_name',
        'snapshot_service_fee',
        'snapshot_service_currency_code',
        'snapshot_service_currency_symbol',
        'snapshot_service_currency_rate',
        'snapshot_invoice_currency_rate',
        'snapshot_converted_fee',
        'quantity',
        'line_total',
    ];

    protected $casts = [
        'snapshot_service_fee' => 'decimal:2',
        'snapshot_service_currency_rate' => 'decimal:6',
        'snapshot_invoice_currency_rate' => 'decimal:6',
        'snapshot_converted_fee' => 'decimal:2',
        'line_total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
