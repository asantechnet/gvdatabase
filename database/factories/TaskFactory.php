<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake('en_US')->sentence(4),
            'description' => fake('en_US')->paragraph(),
            'priority' => fake('en_US')->randomElement(['low', 'medium', 'high', 'urgent']),
            'progress' => fake('en_US')->numberBetween(0, 100),
            'start_date' => fake('en_US')->dateTimeBetween('-1 week', 'now')->format('Y-m-d'),
            'due_date' => fake('en_US')->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'order' => 0,
        ];
    }
}
