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
            'title' => fake('en_US')->sentence(3),
            'category' => fake('en_US')->randomElement(['General', 'Planning', 'Execution', 'Review']),
            'priority' => fake('en_US')->randomElement(['low', 'medium', 'high', 'urgent']),
            'is_completed' => fake('en_US')->boolean(30),
        ];
    }
}
