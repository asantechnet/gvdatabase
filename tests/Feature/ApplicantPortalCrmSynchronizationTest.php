<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApplicantPortalCrmSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_sign_in_backfills_the_applicant_identity_once(): void
    {
        $user = User::factory()->create([
            'email' => 'applicant@example.test',
            'phone' => '+93700000000',
            'name' => 'Portal Applicant',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'acc_status' => 'active',
            'must_set_password' => false,
        ]);
        $user->roles()->attach(Role::query()->where('name', 'applicant')->firstOrFail());
        $case = Applicant::factory()->create(['email' => null, 'user_id' => $user->id, 'name' => '']);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk();

        $this->assertDatabaseCount('applicants', 1);
        $this->assertSame('Portal Applicant', $case->fresh()->name);
        $this->assertSame($user->email, $case->fresh()->email);
    }
}
