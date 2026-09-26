<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use App\Services\CommissionAccrualService;
use App\Services\OutsourcingAccrualService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Income extends Model
{
    use Auditable, HasDocumentNumber, HasFactory, SoftDeletes;

    protected $fillable = [
        'receipt_number',
        'applicant_id',
        'visitor_id',
        'employee_id',
        'company_id',
        'invoice_id',
        'pending_transaction_id',
        'currency_id',
        'base_currency_id',
        'type',
        'description',
        'amount',
        'quantity',
        'cnv_curr_shrt', 'base_curr_shrt', 'rate', 'base_amount', 'invoice_amount',
        'date',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'quantity' => 'integer',
        'rate' => 'decimal:8',
        'base_amount' => 'decimal:6',
        'invoice_amount' => 'decimal:6',
        'date' => 'date',
        'applicant_id' => 'integer',
        'applicant_id' => 'integer',
        'visitor_id' => 'integer',
        'employee_id' => 'integer',
        'company_id' => 'integer',
        'invoice_id' => 'integer',
        'currency_id' => 'integer',
        'base_currency_id' => 'integer',
    ];

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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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

    public function commissionAccruals(): HasMany
    {
        return $this->hasMany(CommissionAccrual::class);
    }

    public function outsourcingAccruals(): HasMany
    {
        return $this->hasMany(OutsourcingAccrual::class);
    }

    protected function documentNumberColumn(): string
    {
        return 'receipt_number';
    }

    protected function documentNumberPrefix(): string
    {
        return 'RCP';
    }

    protected static function booted()
    {
        static::creating(function ($income) {
            $income->base_amount = ($income->amount * $income->quantity) / $income->rate;
        });

        static::created(function (Income $income): void {
            app(CommissionAccrualService::class)->syncForIncome($income);
            app(OutsourcingAccrualService::class)->syncForIncome($income);
        });

        static::updating(function ($income) {
            if ($income->isDirty(['invoice_id', 'amount', 'quantity', 'rate', 'date', 'base_currency_id'])) {
                app(CommissionAccrualService::class)->assertIncomeMutable($income);
                app(OutsourcingAccrualService::class)->assertIncomeMutable($income);
            }
            if ($income->isDirty(['amount', 'quantity', 'rate'])) {
                $income->base_amount = ($income->amount * $income->quantity) / $income->rate;
            }
        });

        static::updated(function (Income $income) {
            if ($income->wasChanged(['invoice_id', 'amount', 'quantity', 'rate', 'date', 'base_currency_id', 'base_amount'])) {
                app(CommissionAccrualService::class)->syncForIncome($income->fresh());
                app(OutsourcingAccrualService::class)->syncForIncome($income->fresh());
            }
        });

        static::deleting(function (Income $income) {
            app(CommissionAccrualService::class)->voidForIncome($income);
            app(OutsourcingAccrualService::class)->voidForIncome($income);
        });
    }
}
