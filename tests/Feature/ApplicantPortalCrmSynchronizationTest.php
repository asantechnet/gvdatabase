<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApplicantPortalCrmSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_applicant_portal_sign_in_creates_one_qualified_lead_for_the_applicant(): void
    {
        $user = User::factory()->create([
            'email' => 'applicant@example.test',
            'phone' => '+93700000000',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'acc_status' => 'active',
            'must_set_password' => false,
        ]);
        $user->roles()->attach(Role::query()->where('name', 'applicant')->firstOrFail());
        $case = Applicant::factory()->create(['email' => $user->email, 'user_id' => $user->id]);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk();

        $this->assertDatabaseCount('applicants', 1);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseHas('leads', [
            'applicant_id' => $case->id,
            'stage' => 'qualified',
            'probability' => 50,
        ]);
        $this->assertSame($case->id, Lead::firstOrFail()->applicant_id);
    }
}
