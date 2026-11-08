<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reduction of a salary advance debt: either the part a payslip withheld
 * (`payroll`) or money the employee handed back (`cash`).
 */
class EmployeeAdvanceSettlement extends Model
{
    public const KIND_PAYROLL = 'payroll';

    public const KIND_CASH = 'cash';

    protected $fillable = [
        'employee_advance_id',
        'salary_id',
        'kind',
        'amount',
        'date',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'date' => 'date',
        ];
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }

    public function salary(): BelongsTo
    {
        return $this->belongsTo(Salary::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
