<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'father_name' => fake()->name('male'),
            'contact' => fake()->phoneNumber(),
            'education_lvl' => 'Bachelor',
            'position_id' => Position::factory(),
            'status' => Employee::STATUS_ACTIVE,
            'salary' => fake()->numberBetween(20000, 100000),
            'addr' => fake()->address(),
            'department' => 'Operations',
            'join_date' => fake()->date(),
            'contract_duration' => '12 months',
        ];
    }
}
