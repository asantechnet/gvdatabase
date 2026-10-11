<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FinanceTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return ['kind' => 'income', 'value' => $name, 'name' => $name, 'is_system' => false];
    }
}
