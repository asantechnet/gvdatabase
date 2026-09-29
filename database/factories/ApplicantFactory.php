<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'father_name' => fake()->name('male'),
            'father_phone' => fake()->phoneNumber(),
            'contact' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'tax_number' => fake()->numerify('TAX-#####'),
            'marketer' => fake()->name(),
            // Educational grade metric, a percentage of the maximum mark.
            'percentage' => fake()->randomFloat(2, 50, 100),
            'date' => fake()->date(),
        ];
    }
}
