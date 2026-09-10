<?php

namespace Database\Factories;

use App\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'template_id' => ContractTemplate::factory(),
            'contract_type_id' => fn (array $attributes) => ContractTemplate::query()->find($attributes['template_id'])?->contract_type_id,
            'status' => 'draft',
            'start_date' => fake()->date(),
            'counterparty' => fake()->company(),
            'is_cancelled' => false,
        ];
    }
}
