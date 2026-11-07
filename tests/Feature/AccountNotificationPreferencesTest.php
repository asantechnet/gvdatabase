<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountNotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_sound_preference_is_persisted_and_returned_with_the_account(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $this->putJson('/api/auth/account/notifications', [
            'notifications' => ['work_updates' => ['in_app' => true]],
            'notification_sound' => false,
        ])->assertOk();

        $this->assertFalse($user->fresh()->account_settings['notification_sound']);
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.notification_sound', false);
    }

    public function test_notification_sound_defaults_to_disabled(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.notification_sound', false);
    }

    public function test_saving_the_event_matrix_alone_keeps_the_sound_preference(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $this->putJson('/api/auth/account/notifications', [
            'notifications' => ['work_updates' => ['in_app' => true]],
            'notification_sound' => false,
        ])->assertOk();

        $this->putJson('/api/auth/account/notifications', [
            'notifications' => ['work_updates' => ['in_app' => false]],
        ])->assertOk();

        $this->assertFalse($user->fresh()->account_settings['notification_sound']);
    }

    public function test_notification_sound_must_be_boolean(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $this->putJson('/api/auth/account/notifications', [
            'notifications' => ['work_updates' => ['in_app' => true]],
            'notification_sound' => 'loud',
        ])->assertUnprocessable()->assertJsonValidationErrors('notification_sound');
    }
}
