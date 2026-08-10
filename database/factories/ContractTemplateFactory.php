<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ContractTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'type' => 'Service',
            'cover_text' => fake()->paragraph(),
            'content' => ['ops' => [['insert' => fake()->paragraph()]]],
        ];
    }
}
