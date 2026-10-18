<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_manifest_is_public_and_uses_company_branding(): void
    {
        Storage::disk('public')->put('logos/company.png', 'png');
        Setting::create([
            ...$this->settings(),
            'short_company_name' => 'Asantech',
            'website_title' => 'Asantech Portal',
            'website_logo' => 'logos/company.png',
            'primary_color' => '#123456',
        ]);

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', 'Asantech')
            ->assertJsonPath('short_name', 'Asantech')
            ->assertJsonPath('start_url', '/')
            ->assertJsonPath('scope', '/')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('theme_color', '#123456');

        $sources = array_column($response->json('icons'), 'src');

        $this->assertContains('/storage/logos/company.png', $sources);
        $this->assertContains('/icon-192.png', $sources);
        $this->assertContains('/icon-512.png', $sources);
        $this->assertContains('/maskable-512.png', $sources);
    }

    public function test_manifest_falls_back_to_bundled_defaults_without_settings(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $response->assertOk()
            ->assertJsonPath('name', 'Educational Agency Portal')
            ->assertJsonPath('theme_color', '#7367F0');

        $sources = array_column($response->json('icons'), 'src');

        $this->assertContains('/icon-192.png', $sources);
        $this->assertContains('/maskable-512.png', $sources);
        $this->assertCount(3, $sources);
    }

    public function test_application_shell_links_the_manifest_and_apple_icon(): void
    {
        Setting::create($this->settings());

        $this->get('/')
            ->assertOk()
            ->assertSee('rel="manifest" href="/manifest.webmanifest"', false)
            ->assertSee('rel="apple-touch-icon" href="/apple-touch-icon.png"', false)
            ->assertSee('name="theme-color"', false);
    }

    public function test_version_endpoint_is_public_and_reports_a_version(): void
    {
        $response = $this->getJson('/api/version');

        $response->assertOk()
            ->assertJsonStructure(['version', 'built_at'])
            ->assertJsonPath('version', fn (string $version) => $version !== '');
    }

    private function settings(): array
    {
        return [
            'navigation_clr' => '#ffffff',
            'cards_clr' => '#ffffff',
            'footer_clr' => '#ffffff',
            'paid_leave_days_per_year' => 20,
            'website_title' => 'Asantech',
            'phone' => '+93 700 000 000',
            'physical_address' => 'Kabul',
            'short_company_name' => 'Asantech',
            'original_company_name' => 'Asantech Ltd',
        ];
    }
}
