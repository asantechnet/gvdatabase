<?php

namespace Database\Factories;

use App\Models\Applicant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_number' => fake()->unique()->bothify('STU-####'),
            'applicant_id' => Applicant::factory(),
            'name' => fake()->name(),
            'father_name' => fake()->name('male'),
            'father_phone' => fake()->phoneNumber(),
            'contact' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
