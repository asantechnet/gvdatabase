<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 10000);

        return [
            'class_type' => 'Standard',
            'customer_id' => Customer::factory(),
            'currency_id' => Currency::factory(),
            'deptor' => fake()->company(),
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
