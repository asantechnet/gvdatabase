<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAnalytics extends Model
{
    protected $table = 'daily_analytics';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d', 'calculated_at' => 'immutable_datetime',
            'income_total' => 'float', 'expense_total' => 'float', 'bonus_base_total' => 'float',
            'attendance_present' => 'integer', 'attendance_absent' => 'integer', 'attendance_off_day' => 'integer',
            'attendance_incomplete' => 'integer', 'worked_minutes' => 'integer', 'overtime_minutes' => 'integer',
            'tasks_created' => 'integer', 'tasks_completed' => 'integer', 'contracts_created' => 'integer',
            'contracts_cancelled' => 'integer', 'employees_onboarded' => 'integer',
            'bonus_nominations' => 'integer', 'bonuses_granted' => 'integer',
        ];
    }
}
