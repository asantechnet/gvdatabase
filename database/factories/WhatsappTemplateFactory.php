<?php

namespace Database\Factories;

use App\Support\WhatsappAudience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsappTemplate>
 */
class WhatsappTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audience' => WhatsappAudience::VISITOR,
            'title' => fake('en_US')->unique()->words(3, true),
            'labels' => [fake('en_US')->word()],
            'message' => 'Hello {{name}}, this is a test message.',
            'active' => true,
            'sort_order' => 0,
        ];
    }

    public function forAudience(string $audience): static
    {
        return $this->state(fn () => ['audience' => $audience]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
