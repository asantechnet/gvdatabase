<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Employee;
use App\Models\Salary;
use Illuminate\Database\Seeder;

class SalarySeeder extends Seeder
{
    public function run(): void
    {
        $currency = Currency::query()->where('is_base', true)->first() ?? Currency::query()->first();
        if (! $currency) {
            return;
        }

        $salaryMonth = '2026-06';
        $paymentDate = '2026-06-30';

        foreach (Employee::query()->where('status', Employee::STATUS_ACTIVE)->orderBy('id')->get() as $employee) {
            $amount = (float) ($employee->salary ?: 40000);

            Salary::updateOrCreate(
                ['employee_id' => $employee->id, 'salary_month' => $salaryMonth],
                [
                    'currency_id' => $currency->id,
                    'base_currency_id' => $currency->id,
                    'amount_paid' => $amount,
                    'bonus' => 0,
                    'worked_days' => 30,
                    'off_days' => 0,
                    'absent_days' => 0,
                    'total_days' => 30,
                    'total_amount' => $amount,
                    'currency_code' => $currency->code,
                    'currency_name' => $currency->name,
                    'currency_symbol' => $currency->symbol,
                    'currency_decimal_places' => $currency->decimal_places,
                    'cnv_curr_shrt' => $currency->code,
                    'base_curr_shrt' => $currency->code,
                    'rate' => 1,
                    'base_amount' => $amount,
                    'remarks' => 'Monthly salary for '.$salaryMonth,
                    'payment_date' => $paymentDate,
                ],
            );
        }
    }
}
