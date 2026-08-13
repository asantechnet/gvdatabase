<?php

namespace Tests\Feature;

use App\Models\AccountDeactivationRequest;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeactivationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_request_requires_password_and_preserves_no_password_data(): void
    {
        $reviewer = $this->user('manager');
        $requester = $this->user('staff');
        Sanctum::actingAs($requester, ['access']);

        $this->postJson('/api/auth/account/deactivation-requests', [
            'current_password' => 'wrong-password',
            'reason' => 'Moving abroad',
        ])->assertUnprocessable()->assertJsonPath('code', 'business.current_password_invalid');

        $response = $this->postJson('/api/auth/account/deactivation-requests', [
            'current_password' => 'password',
            'reason' => 'Moving abroad',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $requestId = $response->json('data.id');
        $this->assertDatabaseHas('account_deactivation_requests', [
            'id' => $requestId,
            'user_id' => $requester->id,
            'pending_user_id' => $requester->id,
            'status' => 'pending',
        ]);
        $this->assertStringNotContainsString('password', strtolower(json_encode(
            AccountDeactivationRequest::findOrFail($requestId)->toArray(),
        )));
        $this->assertStringNotContainsString('password', strtolower(json_encode(
            AuditLog::query()->where('event', 'account_deactivation_requested')->firstOrFail()->toArray(),
        )));
        $this->assertDatabaseHas('notification_user', ['user_id' => $reviewer->id]);

        $this->postJson('/api/auth/account/deactivation-requests', [
            'current_password' => 'password',
        ])->assertConflict()->assertJsonPath('code', 'business.deactivation_request_pending');
    }

    public function test_only_higher_rank_reviewer_can_approve_and_all_tokens_are_revoked(): void
    {
        $admin = $this->user('admin');
        $equalRankReviewer = $this->user('manager');
        $requester = $this->user('manager');
        $requester->createToken('phone', ['access']);
        $requester->createToken('browser', ['refresh']);

        Sanctum::actingAs($requester, ['access']);
        $id = $this->postJson('/api/auth/account/deactivation-requests', [
            'current_password' => 'password',
            'reason' => 'Leaving the company',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($equalRankReviewer, ['access']);
        $this->patchJson("/api/account-deactivation-requests/{$id}/review", [
            'decision' => 'approve',
        ])->assertForbidden()->assertJsonPath('code', 'business.reviewer_rank_insufficient');

        Sanctum::actingAs($requester, ['access']);
        $this->patchJson("/api/account-deactivation-requests/{$id}/review", [
            'decision' => 'approve',
        ])->assertForbidden();

        Sanctum::actingAs($admin, ['access']);
        $this->patchJson("/api/account-deactivation-requests/{$id}/review", [
            'decision' => 'approve',
            'decision_notes' => 'Handover complete',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertSame('deactivated', $requester->fresh()->acc_status);
        $this->assertSame(0, $requester->tokens()->count());

        Sanctum::actingAs($this->user('super_admin'), ['access']);
        $this->putJson("/api/users/{$requester->id}", [
            'acc_status' => 'active',
        ])->assertUnprocessable()->assertJsonPath('code', 'business.use_account_reactivation_workflow');
        $this->assertDatabaseHas('account_deactivation_requests', [
            'id' => $id,
            'pending_user_id' => null,
            'reviewer_id' => $admin->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'account_deactivated', 'auditable_id' => $requester->id]);
    }

    public function test_rejection_and_cancellation_keep_account_active_and_preserve_history(): void
    {
        $manager = $this->user('manager');
        $requester = $this->user('staff');

        Sanctum::actingAs($requester, ['access']);
        $cancelId = $this->postJson('/api/auth/account/deactivation-requests', [
            'current_password' => 'password',
        ])->assertCreated()->json('data.id');
        $this->deleteJson("/api/auth/account/deactivation-requests/{$cancelId}")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $rejectId = $this->postJson('/api/auth/account/deactivation-requests', [
            'current_password' => 'password',
        ])->assertCreated()->json('data.id');
        Sanctum::actingAs($manager, ['access']);
        $this->patchJson("/api/account-deactivation-requests/{$rejectId}/review", [
            'decision' => 'reject',
            'decision_notes' => 'Please complete the handover first.',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame('active', $requester->fresh()->acc_status);
        $this->assertDatabaseCount('account_deactivation_requests', 2);
        $this->assertDatabaseHas('audit_logs', ['event' => 'account_deactivation_cancelled']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'account_deactivation_rejected']);
    }

    public function test_super_admin_requires_another_super_admin_and_reactivation_is_audited(): void
    {
        $first = $this->user('super_admin');
        Sanctum::actingAs($first, ['access']);
        $this->postJson('/api/auth/account/deactivation-requests', [
            'current_password' => 'password',
        ])->assertUnprocessable()->assertJsonPath('code', 'business.no_eligible_deactivation_reviewer');

        $second = $this->user('super_admin');
        $id = $this->postJson('/api/auth/account/deactivation-requests', [
            'current_password' => 'password',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($second, ['access']);
        $this->patchJson("/api/account-deactivation-requests/{$id}/review", [
            'decision' => 'approve',
        ])->assertOk();

        $this->postJson("/api/users/{$first->id}/reactivate", [
            'decision_notes' => 'Returning to service',
        ])->assertOk()->assertJsonPath('data.acc_status', 'active');
        $this->assertDatabaseHas('audit_logs', ['event' => 'account_reactivated', 'auditable_id' => $first->id]);
        $this->assertTrue(Notification::query()->where('notification_key', 'account_deactivation.reactivated')->exists());
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'roles' => [$role],
            'acc_status' => 'active',
            'password' => 'password',
        ]);
    }
}
