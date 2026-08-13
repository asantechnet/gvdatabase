<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subtask>
 */
class SubtaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'category' => $this->faker->randomElement(['General', 'Planning', 'Execution', 'Review']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'is_completed' => $this->faker->boolean(30),
        ];
    }
}
