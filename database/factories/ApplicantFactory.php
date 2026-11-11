<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicantFactory extends Factory
{
    public function privateAdmission(): static
    {
        return $this->state(fn () => ['service' => 'private']);
    }

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'father_name' => fake()->name('male'),
            'father_phone' => fake()->phoneNumber(),
            'contact' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'service' => 'scholarship',
        ];
    }
}
