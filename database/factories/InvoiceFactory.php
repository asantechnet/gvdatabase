<?php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 10000);

        return [
            'applicant_id' => Applicant::factory(),
            'currency_id' => Currency::factory(),
            'deptor' => fake()->name(),
            'status' => 'Issued',
            'tax_percent' => 0,
            'currency_name' => 'US Dollar',
            'currency_symbol' => '$',
            'currency_shortname' => 'USD',
            'cnv_curr_shrt' => 'USD',
            'base_curr_shrt' => 'USD',
            'rate' => 1,
            'base_amount' => $amount,
            'subtotal' => $amount,
            'amount' => $amount,
            'discount' => 0,
            'notes' => null,
            'payment_method' => 'Bank Transfer',
            'payment_instructions' => [],
        ];
    }
}
