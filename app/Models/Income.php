<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use App\Services\ApplicantContractPaymentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Income extends Model
{
    use Auditable, HasDocumentNumber, HasFactory, SoftDeletes;

    protected $fillable = [
        'receipt_number',
        'applicant_id',
        'applicant_contract_id',
        'visitor_id',
        'employee_id',
        'pending_transaction_id',
        'currency_id',
        'base_currency_id',
        'type',
        'description',
        'amount',
        'quantity',
        'cnv_curr_shrt', 'base_curr_shrt', 'rate', 'base_amount',
        'date',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'quantity' => 'integer',
        'rate' => 'decimal:8',
        'base_amount' => 'decimal:6',
        'date' => 'date',
        'applicant_id' => 'integer',
        'applicant_contract_id' => 'integer',
        'visitor_id' => 'integer',
        'employee_id' => 'integer',
        'currency_id' => 'integer',
        'base_currency_id' => 'integer',
    ];

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** The contracted service line this receipt pays for. */
    public function applicantContract(): BelongsTo
    {
        return $this->belongsTo(ApplicantContract::class, 'applicant_contract_id')->withTrashed();
    }

    public function incomeCurrency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function baseCurrency()
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function documentNumberColumn(): string
    {
        return 'receipt_number';
    }

    /** Receipt numbers use the shape RCPT-{year}-{000123}. */
    protected function documentNumberPrefix(): string
    {
        return 'RCPT';
    }

    protected static function booted()
    {
        static::creating(function ($income) {
            $income->base_amount = ($income->amount * $income->quantity) / $income->rate;
        });

        static::created(function (Income $income): void {
            app(ApplicantContractPaymentService::class)->refreshForIncome($income);
        });

        static::updating(function ($income) {
            if ($income->isDirty(['amount', 'quantity', 'rate'])) {
                $income->base_amount = ($income->amount * $income->quantity) / $income->rate;
            }
        });

        static::updated(function (Income $income) {
            if ($income->wasChanged(['applicant_contract_id', 'amount', 'quantity', 'rate', 'date', 'base_currency_id', 'base_amount'])) {
                app(ApplicantContractPaymentService::class)->refreshForIncome(
                    $income,
                    $income->getOriginal('applicant_contract_id') !== null
                        ? (int) $income->getOriginal('applicant_contract_id')
                        : null,
                );
            }
        });

        // Runs after the soft delete so the removed receipt no longer counts
        // towards the line's paid amount.
        static::deleted(function (Income $income) {
            app(ApplicantContractPaymentService::class)->refreshForIncome($income);
        });
    }
}
