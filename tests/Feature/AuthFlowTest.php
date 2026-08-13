<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_cannot_assign_privileged_roles_and_sends_verification(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
            'phone' => '+123456789',
            'address' => 'Test address',
            'roles' => ['super_admin'],
        ])->assertCreated()->assertJsonPath('email_verification_required', true);

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame(['viewer'], $user->roleNames());
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_accounts_cannot_login(): void
    {
        $user = User::factory()->unverified()->create(['acc_status' => 'active']);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('email_verification_required', true);
    }

    public function test_signed_email_link_verifies_account(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect(url('/verify-email?verified=1'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_remembered_login_issues_separate_access_and_refresh_tokens(): void
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ])->assertOk();

        $access = PersonalAccessToken::findToken($response->json('data.access_token'));
        $refresh = PersonalAccessToken::findToken($response->json('data.refresh_token'));
        $this->assertTrue($access->can('access'));
        $this->assertFalse($access->can('refresh'));
        $this->assertTrue($refresh->can('refresh'));
        $this->assertSame('refresh-token-remembered', $refresh->name);

        $this->withToken($response->json('data.refresh_token'))
            ->getJson('/api/auth/me')
            ->assertForbidden();
    }

    public function test_password_reset_email_and_token_flow_work(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }
}
