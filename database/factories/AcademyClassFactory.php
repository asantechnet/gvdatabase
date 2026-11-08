<?php

namespace Database\Factories;

use App\Models\Duration;
use App\Models\Program;
use App\Models\TimeSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademyClassFactory extends Factory
{
    public function definition(): array
    {
        $starts = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'sort_order' => 0,
            'is_active' => true,
            'program_id' => Program::firstOrCreate(['name' => 'Foundation'], ['is_active' => true])->id,
            'time_id' => TimeSlot::firstOrCreate(['name' => 'Morning'], ['is_active' => true])->id,
            'duration_id' => Duration::firstOrCreate(['name' => '6 months'], ['months' => 6, 'is_active' => true])->id,
            'starts_on' => $starts->format('Y-m-d'),
            'ends_on' => (clone $starts)->modify('+6 months')->format('Y-m-d'),
            'weekdays' => [1, 3, 5],
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
            'capacity' => 20,
            'fee' => 12000,
        ];
    }
}
