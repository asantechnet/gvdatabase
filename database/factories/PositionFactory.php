<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'description' => fake()->sentence(),
            'salary' => fake()->numberBetween(20000, 100000),
            'status' => 'active',
            'is_sys_user' => false,
        ];
    }
}
