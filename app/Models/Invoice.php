<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasDocumentNumber, HasFactory, Auditable;

    protected $fillable = [
        'class_type',
        'customer_id',
        'currency_id',
        'deptor',
        'customer_company', 'customer_email', 'customer_contact', 'customer_address', 'customer_tax_number',
        'status',
        'issue_date', 'due_date',
        'tax_percent',
        'currency_name',
        'base_currency_id', 'cnv_curr_shrt', 'base_curr_shrt', 'rate', 'base_amount',
        'currency_symbol',
        'currency_shortname',
        'subtotal',
        'amount',
        'discount',
        'notes',
        'payment_detail_id',
        'payment_method',
        'payment_instructions',
        'voided_at',
        'void_reason',
        'recorded_by',
    ];

    protected $casts = [
        'payment_instructions' => 'array',
        'tax_percent' => 'float',
        'amount' => 'decimal:6',
        'subtotal' => 'decimal:6',
        'discount' => 'decimal:6',
        'rate' => 'decimal:8', 'base_amount' => 'decimal:6',
        'voided_at' => 'datetime',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    protected $attributes = [
        'currency_name' => 'US Dollar',
        'rate' => 1,
        'currency_symbol' => '$',
        'currency_shortname' => 'USD',
        'discount' => 0,
    ];

    protected function documentNumberColumn(): string
    {
        return 'invoice_number';
    }

    protected function documentNumberPrefix(): string
    {
        return 'INV';
    }

    public function services()
    {
        return $this->belongsToMany(Service::class)
            ->using(InvoiceService::class)
            ->withPivot([
                'snapshot_name',
                'snapshot_service_fee',
                'snapshot_service_currency_code',
                'snapshot_service_currency_symbol', 'snapshot_service_rate',
                'snapshot_cnv_curr_shrt', 'snapshot_base_curr_shrt', 'snapshot_rate', 'snapshot_invoice_rate',
                'snapshot_converted_fee',
                'customer_service_id',
                'employee_id',
                'snapshot_commission_percentage',
                'quantity',
                'line_total',
            ])
            ->withTimestamps();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function baseCurrency()
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function incomes()
    {
        return $this->hasMany(Income::class);
    }

    public function paymentDetail()
    {
        return $this->belongsTo(PaymentDetail::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
