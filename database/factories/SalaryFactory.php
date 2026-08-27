<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalaryFactory extends Factory
{
    public function definition(): array
    {
        $amountPaid = fake()->numberBetween(20000, 100000);
        $bonus = fake()->numberBetween(0, 10000);
        $totalAmount = $amountPaid + $bonus;

        return [
            'employee_id' => Employee::factory(),
            'currency_id' => Currency::factory(),
            'salary_month' => fake()->date('Y-m'),
            'amount_paid' => $amountPaid,
            'bonus' => $bonus,
            'worked_days' => 30,
            'total_days' => 30,
            'total_amount' => $totalAmount,
            'currency_code' => 'USD',
            'currency_name' => 'United States Dollar',
            'currency_symbol' => '$',
            'currency_decimal_places' => 2,
            'cnv_curr_shrt' => 'USD',
            'base_curr_shrt' => 'USD',
            'rate' => 1,
            'base_amount' => $totalAmount,
            'remarks' => null,
            'payment_date' => fake()->date(),
        ];
    }
}
