<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\CompanyProfile;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Company email delivery is optional, and every uploaded asset can be removed.
 */
class SettingsEmailAndFileRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        Storage::fake('public');
    }

    public function test_company_profile_saves_without_any_email_configuration(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);

        $response = $this->postJson('/api/settings', $this->basePayload());

        $response->assertCreated()
            ->assertJsonPath('data.company_email', null)
            ->assertJsonPath('data.email_delivery_enabled', true);

        $setting = Setting::query()->firstOrFail();
        $this->assertNull($setting->company_email);
        $this->assertNull($setting->mail_host);
    }

    public function test_disabling_email_delivery_ignores_the_company_mailer(): void
    {
        Setting::create([
            ...$this->basePayload(),
            'company_email' => 'people@example.com',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_username' => 'mailer',
            'mail_password' => 'secret',
            'email_delivery_enabled' => false,
        ]);

        $profile = new CompanyProfile;

        $this->assertFalse($profile->emailDeliveryEnabled());
        $this->assertNull($profile->configureMailer());
        $this->assertNull($profile->mailFrom());
    }

    public function test_logo_and_signature_files_can_be_removed(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);

        $id = $this->post('/api/settings', [
            ...$this->basePayload(),
            'website_logo' => $this->fakePng('logo.png', 400, 400),
            'company_signature' => $this->fakePng('signature.png', 400, 200),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $setting = Setting::findOrFail($id);
        Storage::disk('public')->assertExists($setting->website_logo);
        Storage::disk('public')->assertExists($setting->company_signature);

        $this->patchJson("/api/settings/{$id}", [
            'remove_website_logo' => true,
            'remove_company_signature' => true,
        ])->assertOk()
            ->assertJsonPath('data.website_logo', null)
            ->assertJsonPath('data.company_signature', null);

        $setting->refresh();
        $this->assertNull($setting->website_logo);
        $this->assertNull($setting->company_signature);
        Storage::disk('public')->assertMissing('logos/logo.png');
        Storage::disk('public')->assertMissing('signatures/signature.png');
    }

    /** @return array<string, mixed> */
    private function basePayload(): array
    {
        return [
            'paid_leave_days_per_year' => 20,
            'website_title' => 'Asantech',
            'timezone' => 'Asia/Kabul',
            'phone' => '+93 700 000 000',
            'physical_address' => 'Kabul',
            'short_company_name' => 'Asantech',
            'original_company_name' => 'Asantech Ltd',
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
