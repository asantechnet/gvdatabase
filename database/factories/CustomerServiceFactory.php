<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'service_id' => Service::factory(),
            'deadline' => now()->addMonthsNoOverflow(fake()->numberBetween(1, 24))->toDateString(),
        ];
    }
}
