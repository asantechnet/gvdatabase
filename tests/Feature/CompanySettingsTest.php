<?php

namespace Tests\Feature;

use App\Http\Resources\SettingResource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        Storage::fake('public');
    }

    public function test_authorized_user_can_save_timezone_email_and_contract_artwork(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);

        $response = $this->post('/api/settings', [
            ...$this->validSettings(),
            'contract_letterhead' => $this->fakePng('letterhead.png', 1200, 1600),
            'contract_cover_design' => $this->fakePng('cover.png', 1200, 1600),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.company_email', 'people@example.com')
            ->assertJsonPath('data.timezone', 'Asia/Kabul');

        $setting = Setting::query()->firstOrFail();
        Storage::disk('public')->assertExists($setting->contract_letterhead);
        Storage::disk('public')->assertExists($setting->contract_cover_design);
        $this->assertFalse(\Schema::hasColumn('settings', 'business_licence_document'));
    }

    public function test_artwork_replacement_and_removal_clean_up_superseded_files(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        Storage::disk('public')->put('contract-letterheads/old.jpg', 'old letterhead');
        Storage::disk('public')->put('contract-cover-designs/old.jpg', 'old cover');
        $setting = Setting::create([
            ...$this->validSettings(),
            'contract_letterhead' => 'contract-letterheads/old.jpg',
            'contract_cover_design' => 'contract-cover-designs/old.jpg',
        ]);

        $response = $this->patch("/api/settings/{$setting->id}", [
            'contract_letterhead' => $this->fakePng('new.png', 1200, 1600),
            'remove_contract_cover_design' => '1',
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.contract_cover_design', null);

        $setting->refresh();
        Storage::disk('public')->assertMissing('contract-letterheads/old.jpg');
        Storage::disk('public')->assertMissing('contract-cover-designs/old.jpg');
        Storage::disk('public')->assertExists($setting->contract_letterhead);
        $this->assertNull($setting->contract_cover_design);
    }

    public function test_multipart_artwork_update_uses_method_override(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $setting = Setting::create($this->validSettings());

        $this->post("/api/settings/{$setting->id}", [
            '_method' => 'PATCH',
            'contract_letterhead' => $this->fakePng('letterhead.png', 1200, 1600),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.contract_letterhead', fn (?string $path) => str_starts_with($path ?? '', 'contract-letterheads/'));

        Storage::disk('public')->assertExists($setting->fresh()->contract_letterhead);
    }

    public function test_invalid_company_email_and_timezone_are_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);

        $this->postJson('/api/settings', [
            ...$this->validSettings(),
            'company_email' => 'not-an-email',
            'timezone' => 'Kabul/Somewhere',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['company_email', 'timezone']);
    }

    public function test_patch_updates_only_the_submitted_theme_token(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $setting = Setting::create([
            ...$this->validSettings(),
            'primary_color' => '#111111',
            'secondary_color' => '#222222',
            'success_color' => '#333333',
        ]);

        $this->patchJson("/api/settings/{$setting->id}", [
            'primary_color' => '#abcdef',
        ])->assertOk()
            ->assertJsonPath('data.primary_color', '#abcdef');

        $setting->refresh();
        $this->assertSame('#abcdef', $setting->primary_color);
        $this->assertSame('#222222', $setting->secondary_color);
        $this->assertSame('#333333', $setting->success_color);
    }

    public function test_patch_can_restore_a_theme_token_to_its_vuexy_default(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $setting = Setting::create([
            ...$this->validSettings(),
            'primary_color' => '#111111',
        ]);

        $this->patchJson("/api/settings/{$setting->id}", [
            'primary_color' => null,
        ])->assertOk()
            ->assertJsonPath('data.primary_color', '#0F766E');

        $this->assertSame('#0F766E', $setting->fresh()->primary_color);
    }

    public function test_theme_palette_returns_vuexy_defaults_and_tertiary_is_removed(): void
    {
        $setting = Setting::create($this->validSettings());

        $resource = (new SettingResource($setting))->resolve();

        $this->assertSame('#15803D', $resource['success_color']);
        $this->assertSame('#F5F7F4', $resource['light_background_color']);
        $this->assertSame('#173330', $resource['dark_surface_color']);
        $this->assertArrayNotHasKey('tertiary_color', $resource);
        $this->assertFalse(\Schema::hasColumn('settings', 'tertiary_color'));
    }

    public function test_authenticated_users_can_read_company_branding_without_settings_permission(): void
    {
        Setting::create($this->validSettings());
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->getJson('/api/company-profile')
            ->assertOk()
            ->assertJsonPath('data.company_email', 'people@example.com')
            ->assertJsonPath('data.timezone', 'Asia/Kabul');

        $this->getJson('/api/settings')->assertForbidden();
    }

    public function test_verification_email_uses_company_sender_identity(): void
    {
        Setting::create($this->validSettings());
        $user = User::factory()->unverified()->create();

        $mail = (new VerifyEmail)->toMail($user);

        $this->assertSame('people@example.com', $mail->from[0]);
        $this->assertSame('Asantech', $mail->from[1]);
    }

    private function validSettings(): array
    {
        return [
            'navigation_clr' => '#ffffff',
            'cards_clr' => '#ffffff',
            'footer_clr' => '#ffffff',
            'paid_leave_days_per_year' => 20,
            'website_title' => 'Asantech',
            'website_address' => 'https://example.com',
            'company_email' => 'people@example.com',
            'timezone' => 'Asia/Kabul',
            'phone' => '+93 700 000 000',
            'physical_address' => 'Kabul',
            'short_company_name' => 'Asantech',
            'original_company_name' => 'Asantech Ltd',
            'business_licence' => 'LIC-001',
            'office_open_time' => '09:00',
            'office_close_time' => '17:00',
        ];
    }

    private function fakePng(string $name, int $width, int $height): UploadedFile
    {
        $chunk = function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };
        $contents = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'super_admin']));

        return $user;
    }
}
