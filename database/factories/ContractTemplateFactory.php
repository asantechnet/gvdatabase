<?php

namespace Database\Factories;

use App\Models\ContractType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'contract_type_id' => fn () => ContractType::query()->where('slug', 'customer')->value('id'),
            'type' => 'Customer',
            'cover_text' => fake()->paragraph(),
            'content' => '<p>'.e(fake()->paragraph()).'</p>',
        ];
    }
}
