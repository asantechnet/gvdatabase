<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Salary extends Model
{
    use HasDocumentNumber, HasFactory;

    protected $fillable = [
        'employee_id',
        'currency_id',
        'salary_month',
        'amount_paid',
        'bonus',
        'worked_days',
        'total_days',
        'total_amount',
        'remarks',
        'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'float',
            'bonus' => 'float',
            'total_amount' => 'float',
            'worked_days' => 'integer',
            'total_days' => 'integer',
            'payment_date' => 'date',
        ];
    }

    protected function documentNumberColumn(): string
    {
        return 'payslip_number';
    }

    protected function documentNumberPrefix(): string
    {
        return 'PAY';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class); // Make sure you have an Employee model
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bonuses()
    {
        return $this->belongsToMany(WorthyEmployee::class, 'salary_worthy_employee')
            ->withTimestamps();
    }
}
