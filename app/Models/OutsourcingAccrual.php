<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutsourcingAccrual extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'income_id', 'invoice_id', 'invoice_service_id', 'company_id', 'service_id', 'customer_service_id',
        'base_currency_id', 'outsourcing_settlement_id', 'earned_at', 'source_payment_base_amount',
        'eligible_base_amount', 'percentage', 'outsourcing_base_amount', 'entry_type', 'adjustment_reason',
        'recorded_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'earned_at' => 'date',
            'source_payment_base_amount' => 'decimal:6', 'eligible_base_amount' => 'decimal:6',
            'percentage' => 'decimal:2', 'outsourcing_base_amount' => 'decimal:6',
        ];
    }

    public function income(): BelongsTo { return $this->belongsTo(Income::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class)->withTrashed(); }
    public function invoiceService(): BelongsTo { return $this->belongsTo(InvoiceService::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class)->withTrashed(); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class)->withTrashed(); }
    public function customerService(): BelongsTo { return $this->belongsTo(CustomerService::class)->withTrashed(); }
    public function baseCurrency(): BelongsTo { return $this->belongsTo(Currency::class, 'base_currency_id'); }
    public function settlement(): BelongsTo { return $this->belongsTo(OutsourcingSettlement::class, 'outsourcing_settlement_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
