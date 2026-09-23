<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OptionalServiceDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_service_is_created_without_any_duration_concept(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($admin, ['access']);
        $currency = Currency::factory()->create();

        $this->postJson('/api/services', [
            'type' => 'University application review',
            'fee' => 100,
            'status' => 'active',
            'currency_id' => $currency->id,
        ])->assertCreated()->assertJsonPath('name', 'University application review')
            ->assertJsonMissingPath('duration');
    }
}
