<?php

namespace Database\Seeders;

use App\Models\Salary;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class SalarySeeder extends Seeder
{
    public function run(): void
    {
        $currency = Currency::where('is_base', true)->first() ?? Currency::first();

        if (!$currency) {
            return;
        }

        $salaries = [
            [
                'employee_id' => 1, // Assumes Ahmad Ahmadzai has ID 1
                'currency_id' => $currency->id,
                'salary_month' => '2026-06',
                'amount_paid' => 60000,
                'bonus' => 0,
                'worked_days' => 30,
                'total_days' => 30,
                'total_amount' => 60000,
                'currency_code' => $currency->code,
                'currency_name' => $currency->name,
                'currency_symbol' => $currency->symbol,
                'currency_decimal_places' => $currency->decimal_places,
                'cnv_curr_shrt' => $currency->code,
                'base_curr_shrt' => $currency->code,
                'rate' => 1,
                'base_amount' => 60000,
                'remarks' => 'Partial salary payment',
                'payment_date' => '2026-06-30',
            ],
            [
                'employee_id' => 2, // Assumes Fatima Rahimi has ID 2
                'currency_id' => $currency->id,
                'salary_month' => '2026-06',
                'amount_paid' => 75000,
                'bonus' => 0,
                'worked_days' => 28,
                'total_days' => 30,
                'total_amount' => 70000,
                'currency_code' => $currency->code,
                'currency_name' => $currency->name,
                'currency_symbol' => $currency->symbol,
                'currency_decimal_places' => $currency->decimal_places,
                'cnv_curr_shrt' => $currency->code,
                'base_curr_shrt' => $currency->code,
                'rate' => 1,
                'base_amount' => 70000,
                'remarks' => '2 days unpaid leave deduction applied',
                'payment_date' => '2026-06-30',
            ],
        ];

        foreach ($salaries as $salary) {
            Salary::updateOrCreate(['employee_id' => $salary['employee_id'], 'payment_date' => $salary['payment_date']], $salary);
        }
    }
}
