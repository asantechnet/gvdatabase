<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditManagementTest extends TestCase
{
    use RefreshDatabase;

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
        $customer = Customer::factory()->create(['name' => 'Rahimi Trading', 'contact' => '0700000000']);
        $customer->update(['contact' => '0799999999']);
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
        $customer = Customer::factory()->create(['name' => 'Safi Company', 'contact' => '0700000000']);
        $customer->update(['contact' => '0788888888']);

        $response = $this->getJson('/api/audit-logs?per_page=25')->assertOk();
        $created = collect($response->json('data'))->firstWhere('event', 'created');
        $updated = collect($response->json('data'))->firstWhere('event', 'updated');

        $this->assertSame([], $created['changes']);
        $this->assertSame('customers', $created['resource_key']);
        $this->assertSame('Safi Company', $created['subject_label']);
        $this->assertArrayNotHasKey('table_name', $created);
        $this->assertArrayNotHasKey('auditable_id', $created);
        $this->assertSame('contact', $updated['changes'][0]['field']);
    }

    public function test_undo_restores_the_record_without_deleting_or_duplicating_the_audit(): void
    {
        $auditor = $this->auditor();
        Sanctum::actingAs($auditor, ['access']);
        $customer = Customer::factory()->create(['name' => 'Ariana Store', 'contact' => '0700000000']);
        $customer->update(['contact' => '0799999999']);
        $audit = AuditLog::query()->where('subject_type', 'customers')->where('event', 'updated')->firstOrFail();
        $countBeforeUndo = AuditLog::query()->where('subject_type', 'customers')->count();

        $this->patchJson("/api/audit-logs/{$audit->id}/review", ['decision' => 'discarded'])
            ->assertOk()
            ->assertJsonPath('data.resolution', 'discarded')
            ->assertJsonPath('data.audited_by.id', $auditor->id);

        $this->assertSame('0700000000', $customer->fresh()->contact);
        $this->assertSame($countBeforeUndo, AuditLog::query()->where('subject_type', 'customers')->count());
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
        $customer = Customer::factory()->create(['name' => 'Noor Ltd', 'contact' => '0700000000']);
        $existingCustomerAudits = AuditLog::query()->where('subject_type', 'customers')->count();

        $resources = $this->getJson('/api/audit-resources?per_page=100')->assertOk();
        $customerSetting = collect($resources->json('data'))->firstWhere('resource_key', 'customers');
        $this->patchJson("/api/audit-resources/{$customerSetting['id']}", ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $customer->update(['contact' => '0799999999']);

        $this->assertSame($existingCustomerAudits, AuditLog::query()->where('subject_type', 'customers')->count());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'audit_scope_disabled',
            'subject_type' => 'audit_settings',
            'subject_label' => 'customers',
        ]);
    }

    public function test_audit_state_filter_and_pagination_are_server_driven(): void
    {
        $auditor = $this->auditor();
        Sanctum::actingAs($auditor, ['access']);
        Customer::factory()->count(12)->create();
        $first = AuditLog::query()->where('subject_type', 'customers')->whereNull('audited_at')->firstOrFail();
        $first->update(['audited_by' => $auditor->id, 'audited_at' => now(), 'review_status' => 'approved']);

        $this->getJson('/api/audit-logs?resource=customers&audit_state=pending&page=1&per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('per_page', 5)
            ->assertJsonPath('total', 11);

        $this->getJson('/api/audit-logs?resource=customers&audit_state=audited&page=1&per_page=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.audited_by.id', $auditor->id);
    }
}
