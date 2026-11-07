<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AuditResourceSetting;
use App\Models\Applicant;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Auditing is opt-in from this release. These tests exercise the audit
        // management surface, so explicitly enable every resource first.
        AuditRegistry::ensureSettings();
        AuditResourceSetting::query()->update(['enabled' => true]);
    }

    private function auditor(): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_review_keeps_the_audit_and_records_who_audited_it(): void
    {
        $auditor = $this->auditor();
        Sanctum::actingAs($auditor, ['access']);
        $applicant = Applicant::factory()->create(['name' => 'Rahimi Trading', 'contact' => '0700000000']);
        $applicant->update(['contact' => '0799999999']);
        $audit = AuditLog::query()->where('event', 'updated')->latest('id')->firstOrFail();

        $this->patchJson("/api/audit-logs/{$audit->id}/review", ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.audit_state', 'audited')
            ->assertJsonPath('data.resolution', 'approved')
            ->assertJsonPath('data.audited_by.id', $auditor->id);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $audit->id,
            'audited_by' => $auditor->id,
            'review_status' => 'approved',
        ]);
        $this->assertNotNull($audit->fresh()->audited_at);
    }

    public function test_presented_create_has_no_columns_and_update_hides_storage_metadata(): void
    {
        $auditor = $this->auditor();
        Sanctum::actingAs($auditor, ['access']);
        $applicant = Applicant::factory()->create(['name' => 'Safi Company', 'contact' => '0700000000']);
        $applicant->update(['contact' => '0788888888']);

        $response = $this->getJson('/api/audit-logs?per_page=25')->assertOk();
        $created = collect($response->json('data'))->firstWhere('event', 'created');
        $updated = collect($response->json('data'))->firstWhere('event', 'updated');

        $this->assertSame([], $created['changes']);
        $this->assertSame('applicants', $created['resource_key']);
        $this->assertSame('Safi Company', $created['subject_label']);
        $this->assertArrayNotHasKey('table_name', $created);
        $this->assertArrayNotHasKey('auditable_id', $created);
        $this->assertSame('contact', $updated['changes'][0]['field']);
    }

    public function test_undo_restores_the_record_without_deleting_or_duplicating_the_audit(): void
    {
        $auditor = $this->auditor();
        Sanctum::actingAs($auditor, ['access']);
        $applicant = Applicant::factory()->create(['name' => 'Ariana Store', 'contact' => '0700000000']);
        $applicant->update(['contact' => '0799999999']);
        $audit = AuditLog::query()->where('subject_type', 'applicants')->where('event', 'updated')->firstOrFail();
        $countBeforeUndo = AuditLog::query()->where('subject_type', 'applicants')->count();

        $this->patchJson("/api/audit-logs/{$audit->id}/review", ['decision' => 'discarded'])
            ->assertOk()
            ->assertJsonPath('data.resolution', 'discarded')
            ->assertJsonPath('data.audited_by.id', $auditor->id);

        $this->assertSame('0700000000', $applicant->fresh()->contact);
        $this->assertSame($countBeforeUndo, AuditLog::query()->where('subject_type', 'applicants')->count());
        $this->assertDatabaseHas('audit_logs', ['id' => $audit->id, 'review_status' => 'discarded']);
    }

    public function test_secrets_are_not_written_to_new_audit_payloads(): void
    {
        $auditor = $this->auditor();
        Sanctum::actingAs($auditor, ['access']);
        $target = User::factory()->create(['password' => 'first-secret-password']);
        $createdAudit = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', $createdAudit->new_values);
        $target->update(['password' => 'second-secret-password']);
        $this->assertDatabaseMissing('audit_logs', [
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
            'event' => 'updated',
        ]);
    }

    public function test_disabling_a_resource_stops_future_audits_without_deleting_history(): void
    {
        $auditor = $this->auditor();
        Sanctum::actingAs($auditor, ['access']);
        $applicant = Applicant::factory()->create(['name' => 'Noor Ltd', 'contact' => '0700000000']);
        $existingApplicantAudits = AuditLog::query()->where('subject_type', 'applicants')->count();

        $resources = $this->getJson('/api/audit-resources?per_page=100')->assertOk();
        $applicantSetting = collect($resources->json('data'))->firstWhere('resource_key', 'applicants');
        $this->patchJson("/api/audit-resources/{$applicantSetting['id']}", ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $applicant->update(['contact' => '0799999999']);

        $this->assertSame($existingApplicantAudits, AuditLog::query()->where('subject_type', 'applicants')->count());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'audit_scope_disabled',
            'subject_type' => 'audit_settings',
            'subject_label' => 'applicants',
        ]);
    }

    public function test_audit_state_filter_and_pagination_are_server_driven(): void
    {
        $auditor = $this->auditor();
        Sanctum::actingAs($auditor, ['access']);
        Applicant::factory()->count(12)->create();
        $first = AuditLog::query()->where('subject_type', 'applicants')->whereNull('audited_at')->firstOrFail();
        $first->update(['audited_by' => $auditor->id, 'audited_at' => now(), 'review_status' => 'approved']);

        $this->getJson('/api/audit-logs?resource=applicants&audit_state=pending&page=1&per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('per_page', 5)
            ->assertJsonPath('total', 11);

        $this->getJson('/api/audit-logs?resource=applicants&audit_state=audited&page=1&per_page=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.audited_by.id', $auditor->id);
    }

    public function test_pending_audits_can_be_reviewed_together(): void
    {
        $auditor = $this->auditor();
        Sanctum::actingAs($auditor, ['access']);
        Applicant::factory()->count(2)->create();
        $auditIds = AuditLog::query()
            ->where('subject_type', 'applicants')
            ->whereNull('audited_at')
            ->pluck('id')
            ->all();

        $this->postJson('/api/audit-logs/bulk-review', [
            'audit_ids' => $auditIds,
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('data.count', count($auditIds));

        $this->assertSame(0, AuditLog::query()->whereIn('id', $auditIds)->whereNull('audited_at')->count());
        $this->assertSame(count($auditIds), AuditLog::query()->whereIn('id', $auditIds)->where('review_status', 'approved')->count());
    }
}
