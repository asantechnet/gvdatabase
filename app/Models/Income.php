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

    public const DRAFT = 'draft';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'receipt_number',
        'applicant_id',
        'applicant_contract_id',
        'visitor_id',
        'employee_id',
        'currency_id',
        'base_currency_id',
        'type',
        'description',
        'amount',
        'quantity',
        'cnv_curr_shrt', 'base_curr_shrt', 'rate', 'base_amount',
        // Required when an issued receipt's value fields change; audited with
        // the row so the reason cannot be separated from the change.
        'revaluation_reason',
        'date',
        'recorded_by',
        'status',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
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
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Only approved receipts count as ledger money. */
    public function scopeApproved($query)
    {
        return $query->where('status', self::APPROVED);
    }

    protected function documentNumberColumn(): string
    {
        return 'receipt_number';
    }

    /** A receipt number is issued only once the receipt is approved. */
    protected function documentNumberIsIssued(): bool
    {
        return $this->status === self::APPROVED;
    }

    /** Receipt numbers use the shape RCPT-{year}-{000123}. */
    protected function documentNumberPrefix(): string
    {
        return 'RCPT';
    }

    protected static function booted()
    {
        static::creating(function ($income) {
            // Directly recorded receipts are approved; an explicit draft stays
            // out of the ledger until a finance user approves it.
            $income->status = $income->status ?: self::APPROVED;
            $income->base_amount = ($income->amount * $income->quantity) / $income->rate;
        });

        static::created(function (Income $income): void {
            if ($income->status === self::APPROVED) {
                app(ApplicantContractPaymentService::class)->refreshForIncome($income);
            }
        });

        static::updating(function ($income) {
            if ($income->isDirty(['amount', 'quantity', 'rate'])) {
                $income->base_amount = ($income->amount * $income->quantity) / $income->rate;
            }
        });

        static::updated(function (Income $income) {
            if ($income->wasChanged(['applicant_contract_id', 'amount', 'quantity', 'rate', 'date', 'base_currency_id', 'base_amount', 'status'])) {
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
            if ($income->status === self::APPROVED) {
                app(ApplicantContractPaymentService::class)->refreshForIncome($income);
            }
        });
    }
}
