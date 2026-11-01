<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Currency;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'serial_number' => fake()->unique()->bothify('SN-####-????'),
            'item' => fake()->randomElement(['Laptop', 'Monitor', 'Keyboard', 'Mouse', 'Printer']),
            'quantity' => fake()->numberBetween(1, 10),
            'price' => fake()->randomFloat(2, 20, 1500),
            'currency_id' => fn () => Currency::query()->where('is_base', true)->value('id')
                ?? Currency::factory()->state(['is_base' => true, 'is_active' => true])->create()->id,
            'acquisition_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'model' => fake()->bothify('MDL-###'),
            'condition' => fake()->randomElement(Asset::conditions()),
            'office_id' => Office::factory(),
        ];
    }
}
