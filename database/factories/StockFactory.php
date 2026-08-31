<?php

namespace Database\Factories;

use App\Models\Stock;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function definition(): array
    {
        return [
            'serial_number' => fake()->unique()->bothify('SN-####-????'),
            'item' => fake()->randomElement(['Laptop', 'Monitor', 'Keyboard', 'Mouse', 'Printer']),
            'quantity' => fake()->numberBetween(1, 10),
            'model' => fake()->bothify('MDL-###'),
            'condition' => fake()->randomElement(Stock::conditions()),
            'office_id' => Office::factory(),
        ];
    }
}
