<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->words(2, true),
            'fee' => fake()->randomFloat(2, 0, 10000),
            'status' => Service::STATUS_ACTIVE,
            'currency_id' => Currency::factory(),
        ];
    }
}
