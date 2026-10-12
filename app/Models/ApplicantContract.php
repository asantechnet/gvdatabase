<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\ApplicantContractPaymentService;
use App\Support\BuiltInService;
use App\Support\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One built-in service line, including its agreed economics and payments. */
class ApplicantContract extends Pivot
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'applicant_contract';

    public $incrementing = true;

    protected $fillable = [
        'contract_id',
        'service',
        'application_fee',
        'visa_fee',
        'booking_fee',
        'service_fee',
        'remote_fee',
        'fee',
        'discount',
        'currency_id',
        'currency_rate',
        'applicant_id',
        'sold_by_employee_id',
        'notes',
        'amount_paid',
        'payment_status',
        'payment_status_overridden',
        'amount_paid_overridden',
    ];

    protected $casts = [
        'application_fee' => 'decimal:2',
        'visa_fee' => 'decimal:2',
        'booking_fee' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'remote_fee' => 'decimal:2',
        'fee' => 'decimal:2',
        'discount' => 'decimal:6',
        'currency_rate' => 'decimal:8',
        'amount_paid' => 'decimal:6',
        'payment_status_overridden' => 'boolean',
        'amount_paid_overridden' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(fn (ApplicantContract $line) => $line->refreshPaymentState());
        static::updated(function (ApplicantContract $line): void {
            if ($line->wasChanged(['fee', 'discount', 'currency_id', 'currency_rate', 'amount_paid_overridden', 'payment_status_overridden'])) {
                if ($line->wasChanged('currency_id')) {
                    $line->unsetRelation('currency');
                }
                $line->refreshPaymentState();
            }
        });
    }

    private function refreshPaymentState(): void
    {
        // Model events run before Eloquent syncs originals. A fresh instance
        // prevents the recalculation save from re-saving dirty pricing fields.
        $calculated = app(ApplicantContractPaymentService::class)->recalculate($this->fresh());
        $this->forceFill($calculated->only(['amount_paid', 'payment_status']));
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Receipts recorded against this line. One receipt is one service, so the
     * line is the payment subject.
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class, 'applicant_contract_id');
    }

    public function soldByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sold_by_employee_id');
    }

    /** Commission accruals earned from this service line. */
    public function commissionAccruals(): HasMany
    {
        return $this->hasMany(CommissionAccrual::class, 'applicant_contract_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bonusSource()
    {
        return $this->morphOne(EducationalBonusSource::class, 'source');
    }

    /** Money owed for the line, after the agreed discount. */
    public function payableTotal(): float
    {
        return max(0.0, (float) ($this->fee ?? 0) - (float) ($this->discount ?? 0));
    }

    /** What is still to collect, in the line currency. */
    public function remainingTotal(): float
    {
        return max(0.0, round($this->payableTotal() - (float) ($this->amount_paid ?? 0), 6));
    }

    public function serviceKey(): ?string
    {
        return BuiltInService::isValid($this->service) ? $this->service : null;
    }

    public function serviceLabel(): string
    {
        return BuiltInService::label($this->serviceKey());
    }

    public function paymentStatusLabel(): string
    {
        return PaymentStatus::label($this->payment_status);
    }
}
