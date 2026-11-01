<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create([
            'password' => 'password123',
            'email_verified_at' => now(),
            'acc_status' => 'active',
            ...$attributes,
        ]);
    }

    public function test_begin_uses_the_company_issuer_and_never_exposes_the_secret_in_account_data(): void
    {
        Setting::create([
            'website_title' => 'Acme Portal',
            'short_company_name' => 'Acme Agency',
            'original_company_name' => 'Acme Agency Limited',
            'phone' => '0700000000',
            'physical_address' => 'Kabul',
        ]);
        $user = $this->user();
        Sanctum::actingAs($user, ['access']);

        $response = $this->postJson('/api/auth/account/two-factor')->assertOk();

        $this->assertSame('Acme Agency', $response->json('issuer'));
        $this->assertStringStartsWith('otpauth://totp/', $response->json('otpauth_url'));
        $this->assertStringContainsString('issuer=Acme%20Agency', $response->json('otpauth_url'));
        $this->assertNotEmpty($response->json('secret'));

        $account = $this->getJson('/api/auth/me')->assertOk();

        $this->assertArrayNotHasKey('two_factor_secret', $account->json('data'));
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $account->json('data'));
    }

    public function test_confirm_returns_ten_recovery_codes_and_stores_only_hashes(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user, ['access']);

        $secret = $this->postJson('/api/auth/account/two-factor')->json('secret');
        $response = $this->postJson('/api/auth/account/two-factor/confirm', [
            'code' => $this->totp($secret),
        ])->assertOk();

        $codes = $response->json('recovery_codes');

        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));
        $this->assertSame(10, DB::table('two_factor_recovery_codes')->where('user_id', $user->id)->count());

        $stored = DB::table('two_factor_recovery_codes')->where('user_id', $user->id)->pluck('code_hash');

        foreach ($codes as $code) {
            $this->assertNotContains($code, $stored);
        }
        $this->assertTrue(password_get_info($stored->first())['algo'] !== null);
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $account = $this->getJson('/api/auth/me')->assertOk();
        $this->assertArrayNotHasKey('two_factor_secret', $account->json('data'));
    }

    public function test_a_recovery_code_authenticates_once_and_the_totp_path_still_works(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user, ['access']);

        $secret = $this->postJson('/api/auth/account/two-factor')->json('secret');
        $codes = $this->postJson('/api/auth/account/two-factor/confirm', [
            'code' => $this->totp($secret),
        ])->json('recovery_codes');

        $credentials = ['email' => $user->email, 'password' => 'password123'];

        $this->postJson('/api/auth/login', [...$credentials, 'recovery_code' => $codes[0]])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/auth/login', [...$credentials, 'recovery_code' => $codes[0]])
            ->assertUnprocessable()
            ->assertJsonPath('two_factor_required', true);

        $this->postJson('/api/auth/login', [...$credentials, 'two_factor_code' => $this->totp($secret)])
            ->assertOk();

        $this->postJson('/api/auth/login', [...$credentials, 'recovery_code' => 'NOT-A-CODE'])
            ->assertUnprocessable()
            ->assertJsonPath('two_factor_required', true);
    }

    public function test_sessions_are_scoped_to_the_owner_and_revoking_another_users_token_is_refused(): void
    {
        $owner = $this->user();
        $foreignUser = $this->user();

        $current = $owner->createToken('access-token|Device A', ['access'], now()->addHours(2));
        $other = $owner->createToken('access-token|Device B', ['access'], now()->addHours(2));
        $foreign = $foreignUser->createToken('access-token|Foreign device', ['access'], now()->addHours(2));

        $response = $this->withToken($current->plainTextToken)
            ->getJson('/api/auth/account/sessions')
            ->assertOk();

        $rows = collect($response->json('data'));
        $ids = $rows->pluck('id');

        $this->assertTrue($ids->contains($current->accessToken->id));
        $this->assertTrue($ids->contains($other->accessToken->id));
        $this->assertFalse($ids->contains($foreign->accessToken->id));
        $this->assertTrue($rows->firstWhere('id', $current->accessToken->id)['current']);
        $this->assertFalse($rows->firstWhere('id', $other->accessToken->id)['current']);
        $this->assertSame('Device A', $rows->firstWhere('id', $current->accessToken->id)['device']);

        $this->withToken($current->plainTextToken)
            ->deleteJson('/api/auth/account/sessions/'.$other->accessToken->id)
            ->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);

        $this->withToken($current->plainTextToken)
            ->deleteJson('/api/auth/account/sessions/'.$foreign->accessToken->id)
            ->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->accessToken->id]);
    }

    private function totp(string $secret): string
    {
        $hash = hash_hmac('sha1', pack('N*', 0, intdiv(time(), 30)), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 15;
        $value = ((ord($hash[$offset]) & 127) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $data): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $bits = '';

        foreach (str_split(str_replace('=', '', strtoupper($data))) as $character) {
            $bits .= str_pad(decbin($alphabet[$character]), 5, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 8) as $part) {
            if (strlen($part) === 8) {
                $output .= chr(bindec($part));
            }
        }

        return $output;
    }
}
