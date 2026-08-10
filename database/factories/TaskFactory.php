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
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'progress' => $this->faker->numberBetween(0, 100),
            'start_date' => $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d'),
            'due_date' => $this->faker->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'order' => 0,
        ];
    }
}
