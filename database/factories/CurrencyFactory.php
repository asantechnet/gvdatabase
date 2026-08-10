<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        $code = fake()->unique()->currencyCode();

        return [
            'name' => $code,
            'code' => $code,
            'symbol' => $code,
            'rate_against_base' => 1,
            'is_base' => false,
            'is_active' => true,
            'decimal_places' => 2,
        ];
    }
}
