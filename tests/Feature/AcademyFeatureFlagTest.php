<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademyFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private function setting(bool $enabled): Setting
    {
        return Setting::create([
            'academy_enabled' => $enabled,
            'website_title' => 'Feature Flag Company',
            'phone' => '0000000000',
            'physical_address' => 'Test address',
            'short_company_name' => 'Feature Flag',
            'original_company_name' => 'Feature Flag Company',
        ]);
    }

    public function test_company_profile_exposes_the_academy_flag(): void
    {
        $this->setting(false);
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->getJson('/api/company-profile')
            ->assertOk()
            ->assertJsonPath('data.academy_enabled', false);
    }

    public function test_academy_api_is_hidden_when_the_flag_is_off(): void
    {
        $this->setting(false);
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);

        $this->getJson('/api/academy/overview')->assertNotFound();
        $this->postJson('/api/contracts/academy', [])->assertNotFound();
        $this->getJson('/api/contracts/academy/options')->assertNotFound();
    }

    public function test_academy_api_is_available_when_the_flag_is_on(): void
    {
        $this->setting(true);
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);

        $this->getJson('/api/academy/overview')->assertOk();
        $this->getJson('/api/contracts/academy/options')->assertOk();
    }
}
