<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InvoiceService extends Pivot
{
    protected $table = 'invoice_service';

    public $incrementing = true;

    protected $fillable = [
        'invoice_id',
        'service_id',
        'customer_service_id',
        'employee_id',
        'marketer_employee_id',
        'snapshot_commission_percentage',
        'snapshot_marketer_commission_percentage',
        'snapshot_name',
        'snapshot_service_fee',
        'snapshot_service_currency_code',
        'snapshot_service_currency_symbol',
        'snapshot_cnv_curr_shrt',
        'snapshot_base_curr_shrt',
        'snapshot_rate',
        'snapshot_converted_fee',
        'quantity',
        'line_total',
    ];

    protected $casts = [
        'snapshot_service_fee' => 'decimal:2',
        'snapshot_rate' => 'decimal:8',
        'snapshot_converted_fee' => 'decimal:2',
        'line_total' => 'decimal:2',
        'quantity' => 'integer',
        'snapshot_commission_percentage' => 'decimal:2',
        'snapshot_marketer_commission_percentage' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function customerService()
    {
        return $this->belongsTo(CustomerService::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function marketerEmployee()
    {
        return $this->belongsTo(Employee::class, 'marketer_employee_id');
    }

    /** Immutable delivery-commission recipients snapshotted when invoiced. */
    public function commissionRecipients(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'invoice_service_commission_recipients', 'invoice_service_id', 'employee_id')
            ->withPivot('percentage')
            ->withTimestamps();
    }
}
