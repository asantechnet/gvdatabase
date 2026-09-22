<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryPayment extends Model
{
    use Auditable, HasDocumentNumber, SoftDeletes;

    protected $fillable = ['salary_id', 'expense_id', 'pending_transaction_id', 'amount', 'payment_date', 'remarks', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'payment_date' => 'date'];
    }

    protected function documentNumberColumn(): string { return 'payment_number'; }
    protected function documentNumberPrefix(): string { return 'SPY'; }
    public function salary(): BelongsTo { return $this->belongsTo(Salary::class); }
    public function expense(): BelongsTo { return $this->belongsTo(Expense::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
