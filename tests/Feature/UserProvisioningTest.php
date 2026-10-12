<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the two ways an admin can provision an account: a manual password that
 * never sends anything to the user's address, and the invitation flow where the
 * user verifies the address and picks their own password.
 */
class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Provisioned User',
            'email' => 'provisioned@example.com',
            'phone' => '0700000000',
            'address' => 'Kabul',
            'roles' => ['staff'],
            'acc_status' => 'active',
        ], $overrides);
    }

    public function test_a_manual_password_creates_a_ready_account_and_sends_no_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/users', $this->payload([
            'password' => 'supersecret1',
            'password_confirmation' => 'supersecret1',
        ]))->assertCreated();

        $user = User::query()->where('email', 'provisioned@example.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->must_set_password);
        $this->assertTrue(Hash::check('supersecret1', $user->password));
        Notification::assertNothingSent();
        $response->assertJsonPath('data.email_verified', true);
        $response->assertJsonPath('data.password_setup_pending', false);
    }

    public function test_without_a_password_the_user_is_invited_and_verifies_their_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/users', $this->payload())->assertCreated();

        $user = User::query()->where('email', 'provisioned@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue($user->must_set_password);
        Notification::assertSentTo($user, VerifyEmail::class);
        $response->assertJsonPath('data.password_setup_pending', true);
    }

    public function test_a_manual_password_must_be_confirmed_and_long_enough(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();

        $this->postJson('/api/users', $this->payload(['password' => 'supersecret1']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/users', $this->payload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/users', $this->payload([
            'password' => 'supersecret1',
            'password_confirmation' => 'differentsecret1',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'provisioned@example.com']);
        Notification::assertNothingSent();
    }

    public function test_editing_a_user_never_rewrites_the_stored_password(): void
    {
        $this->actingAsSuperAdmin();
        $target = User::factory()->create(['password' => Hash::make('original-pass-1')]);

        $this->putJson("/api/users/{$target->id}", [
            'name' => 'Renamed User',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertOk();

        $target->refresh();
        $this->assertSame('Renamed User', $target->name);
        $this->assertTrue(Hash::check('original-pass-1', $target->password));
    }
}
