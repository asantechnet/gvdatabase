<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notification> */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['info', 'success', 'warning', 'task']),
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(12),
            'date_time' => fake()->dateTimeBetween('-14 days'),
            'is_read' => fake()->optional()->boolean(),
            'employee_id' => null,
            'go_to_url' => '/home',
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn () => ['employee_id' => $employee->id]);
    }
}
