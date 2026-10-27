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
use Illuminate\Support\Facades\DB;
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
            ->assertJsonPath('data.email_delivery_enabled', false);

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
            'mail_reply_to' => 'reply@example.com',
            'email_delivery_enabled' => false,
        ]);

        $profile = new CompanyProfile;

        $this->assertFalse($profile->emailDeliveryEnabled());
        $this->assertNull($profile->configureMailer());
        $this->assertNull($profile->mailFrom());
        $this->assertNull($profile->replyTo());
    }

    public function test_email_delivery_is_opt_in_for_the_model_database_and_missing_profile(): void
    {
        $this->assertFalse((new CompanyProfile)->emailDeliveryEnabled());
        $this->assertFalse((new Setting)->email_delivery_enabled);
        DB::table('settings')->insert($this->basePayload());
        $this->assertFalse(Setting::firstOrFail()->email_delivery_enabled);
    }

    public function test_an_explicit_email_opt_in_survives_other_settings_updates(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $id = $this->postJson('/api/settings', [...$this->basePayload(), 'email_delivery_enabled' => true])->assertCreated()->json('data.id');
        $this->patchJson('/api/settings/'.$id, ['website_title' => 'Updated portal'])->assertOk()
            ->assertJsonPath('data.email_delivery_enabled', true);
    }

    public function test_email_default_migration_round_trip_preserves_explicit_preferences(): void
    {
        $setting = Setting::create([...$this->basePayload(), 'email_delivery_enabled' => true]);
        $migration = require database_path('migrations/2026_10_14_000002_default_company_email_delivery_to_disabled.php');
        $migration->down();
        $this->assertTrue($setting->fresh()->email_delivery_enabled);
        $migration->up();
        $this->assertTrue($setting->fresh()->email_delivery_enabled);
        DB::table('settings')->insert($this->basePayload());
        $this->assertFalse(Setting::latest('id')->firstOrFail()->email_delivery_enabled);
    }

    public function test_partial_updates_report_required_fields_with_their_names_and_preserve_the_record(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $setting = Setting::create($this->basePayload());
        $response = $this->patchJson('/api/settings/'.$setting->id, ['phone' => '', 'website_title' => '', 'physical_address' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone', 'website_title', 'physical_address']);
        $this->assertSame('validation.required', $response->json('errors.website_title.0'));
        $this->assertStringContainsString('Website Title', $response->json('messages.website_title.0'));
        $this->assertStringContainsString('before saving', $response->json('messages.website_title.0'));
        $this->assertSame($this->basePayload()['phone'], $setting->fresh()->phone);
    }

    public function test_invalid_email_operations_and_colors_have_descriptive_field_errors(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $setting = Setting::create($this->basePayload());
        $response = $this->patchJson('/api/settings/'.$setting->id, [
            'company_email' => 'invalid', 'mail_port' => 70000, 'mail_reply_to' => 'invalid',
            'timezone' => 'Invalid/Zone', 'primary_color' => '#12', 'off_day_allowance_days_per_year' => 400,
            'office_open_time' => '25:90', 'default_break_minutes' => -1, 'automatic_attendance_enabled' => 'invalid',
            'website_address' => 'example.com', 'mail_host' => 'https://smtp.example.com:587',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'company_email', 'mail_port', 'mail_reply_to', 'timezone', 'primary_color',
            'off_day_allowance_days_per_year', 'office_open_time', 'default_break_minutes', 'automatic_attendance_enabled',
            'website_address', 'mail_host',
        ]);
        $this->assertStringContainsString('SMTP port', $response->json('messages.mail_port.0'));
        $this->assertStringContainsString('65535', $response->json('messages.mail_port.0'));
        $this->assertStringContainsString('#7367F0', $response->json('messages.primary_color.0'));
        $this->assertStringContainsString('http://', $response->json('messages.website_address.0'));
        $this->assertStringContainsString('port separately', $response->json('messages.mail_host.0'));
    }

    public function test_every_document_safe_area_reports_overflow_and_accepts_a_corrected_partial_update(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $setting = Setting::create($this->basePayload());
        foreach (['contract', 'receipt', 'payslip'] as $kind) {
            $prefix = $kind.'_letterhead_safe_';
            $response = $this->patchJson('/api/settings/'.$setting->id, [$prefix.'x' => 95])
                ->assertUnprocessable()->assertJsonValidationErrors([$prefix.'x', $prefix.'width']);
            $this->assertStringContainsString('100%', $response->json('messages.'.$prefix.'width.0'));
            $this->patchJson('/api/settings/'.$setting->id, [$prefix.'x' => 90, $prefix.'width' => 10])->assertOk();
        }
    }

    public function test_invalid_artwork_reports_the_file_field_and_supported_types(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $setting = Setting::create($this->basePayload());
        $response = $this->post('/api/settings/'.$setting->id, [
            '_method' => 'PATCH', 'receipt_letterhead' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['receipt_letterhead']);
        $this->assertStringContainsString('receipt letterhead', strtolower($response->json('messages.receipt_letterhead.0')));
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
            'off_day_allowance_days_per_year' => 20,
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
