<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->administrator = User::factory()->create();
        $this->administrator->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($this->administrator, ['access']);
    }

    public function test_staff_can_invite_an_applicant_and_the_portal_only_exposes_their_case(): void
    {
        $case = $this->postJson('/api/applicants', [
            'customer_id' => Customer::factory()->create()->id,
            'notes' => 'Internal: waiting for the agency finance approval.',
        ])->assertCreated()->json('data');
        $otherCase = Applicant::create(['customer_id' => Customer::factory()->create()->id]);

        $invitation = $this->postJson("/api/applicants/{$case['id']}/portal-invitation", [
            'name' => 'Amina Student',
            'email' => 'amina@example.test',
            'phone' => '+93700000000',
        ])->assertCreated()->json('data');

        $user = User::query()->findOrFail($invitation['user_id']);
        $this->assertSame(['applicant'], $user->roleNames());
        $this->assertDatabaseHas('applicants', ['id' => $case['id'], 'user_id' => $user->id]);

        Sanctum::actingAs($user, ['access']);
        $portal = $this->getJson('/api/applicant-portal')->assertOk()
            ->assertJsonPath('data.case.id', $case['id'])
            ->assertJsonMissing(['Internal: waiting for the agency finance approval.'])
            ->json('data');

        $this->assertNotSame($otherCase->id, $portal['case']['id']);
        $this->assertArrayNotHasKey('notes', $portal['case']);
        $this->assertArrayNotHasKey('tasks', $portal['case']);
        $this->assertArrayNotHasKey('incomes', $portal['case']);
    }

    public function test_applicant_can_update_only_their_portal_profile_and_role_permissions_remain_editable(): void
    {
        $case = Applicant::create(['customer_id' => Customer::factory()->create()->id]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach(Role::query()->where('name', 'applicant')->firstOrFail());
        $case->update(['user_id' => $user->id]);
        $auditCountBeforeProfileUpdate = AuditLog::query()
            ->where('auditable_type', Applicant::class)
            ->where('auditable_id', $case->id)
            ->count();

        Sanctum::actingAs($user, ['access']);
        $this->putJson('/api/applicant-portal/profile', [
            'name' => 'Amina Updated',
            'phone' => '+93701111111',
            'profile' => ['passport_number' => 'P1234567', 'nationality' => 'Afghan', 'preferred_contact_method' => 'whatsapp'],
        ])->assertOk()
            ->assertJsonPath('data.profile.name', 'Amina Updated')
            ->assertJsonPath('data.profile.passport_number', 'P1234567');

        $this->assertDatabaseHas('applicants', ['id' => $case->id]);
        $this->assertSame('P1234567', $case->fresh()->profile_data['passport_number']);
        $this->assertSame($auditCountBeforeProfileUpdate, AuditLog::query()
            ->where('auditable_type', Applicant::class)
            ->where('auditable_id', $case->id)
            ->count());

        Sanctum::actingAs($this->administrator, ['access']);
        $role = Role::query()->where('name', 'applicant')->firstOrFail();
        $this->putJson("/api/roles/{$role->id}/permissions", ['permissions' => ['view_own_applicant_portal']])
            ->assertOk()
            ->assertJsonPath('data.name', 'applicant');
        $this->deleteJson("/api/roles/{$role->id}")->assertUnprocessable();
        $this->putJson("/api/roles/{$role->id}", ['name' => 'renamed-applicant'])->assertUnprocessable();
    }

    public function test_operational_users_cannot_open_the_applicant_only_portal(): void
    {
        $this->getJson('/api/applicant-portal')->assertForbidden();
    }
}
