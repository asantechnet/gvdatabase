<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'applicant_id',
        'visitor_id',
        'employee_id',
        'company_id',
        'project_id',
        'salary_id',
        'pending_transaction_id',
        'currency_id',
        'base_currency_id',
        'currency',
        'type',
        'description',
        'document_path', 'document_name', 'document_mime', 'document_size',
        'amount',
        'quantity',
        'cnv_curr_shrt', 'base_curr_shrt', 'rate', 'base_amount',
        'date',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'integer',
        'rate' => 'decimal:8',
        'base_amount' => 'decimal:4',
        'date' => 'date',
        'customer_id' => 'integer',
        'applicant_id' => 'integer',
        'visitor_id' => 'integer',
        'employee_id' => 'integer',
        'company_id' => 'integer',
        'project_id' => 'integer',
        'currency_id' => 'integer',
        'base_currency_id' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function expenseCurrency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function baseCurrency()
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function salary()
    {
        return $this->belongsTo(Salary::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected static function booted()
    {
        static::creating(function ($expense) {
            $expense->base_amount = ($expense->amount * $expense->quantity) / $expense->rate;
        });

        static::updating(function ($expense) {
            if ($expense->isDirty(['amount', 'quantity', 'rate'])) {
                $expense->base_amount = ($expense->amount * $expense->quantity) / $expense->rate;
            }
        });
    }
}
