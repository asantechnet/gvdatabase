<?php

namespace Tests\Feature;

use App\Models\ApplicantContract;
use App\Models\AuditLog;
use App\Models\AuditResourceSetting;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Income;
use App\Models\Notification;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\EducationalWorkflowNotificationService;
use App\Support\AuditRegistry;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class VisaFirmComplianceTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    public function test_role_defaults_keep_issuance_and_financial_override_separate(): void
    {
        foreach (['staff', 'counselor', 'case_officer', 'senior_counselor'] as $name) {
            $permissions = Role::where('name', $name)->firstOrFail()->permissions()->pluck('name');
            $this->assertContains('create_contracts', $permissions);
            $this->assertNotContains('override_contract_payments', $permissions);
            $this->assertNotContains('manage_contract_templates', $permissions);
            $this->assertNotContains('assign_admission_employee', $permissions);
        }
        $this->assertContains('assign_admission_employee', Role::where('name', 'manager')->firstOrFail()->permissions()->pluck('name'));
        $finance = Role::where('name', 'finance_officer')->firstOrFail()->permissions()->pluck('name');
        foreach (['view_incomes', 'create_incomes', 'edit_incomes', 'override_contract_payments'] as $name) {
            $this->assertContains($name, $finance);
        }
        $this->assertNotContains('create_contracts', $finance);
        $document = Role::where('name', 'document_officer')->firstOrFail()->permissions()->pluck('name');
        $this->assertContains('view_applicant_contracts', $document);
        $this->assertNotContains('override_contract_payments', $document);
        // Invoicing, companies, outsourcing, packages and leads stay retired.
        // The commission ledger was deliberately restored on the new
        // contract-driven architecture, so its permissions are expected.
        foreach (PermissionSeeder::catalogNames() as $name) {
            $this->assertDoesNotMatchRegularExpression('/invoice|company|outsourcing|package|lead|(?:view|manage)_services/', $name);
        }
        foreach (['view_commissions', 'manage_commission_settlements', 'approve_commissions', 'pay_commissions'] as $name) {
            $this->assertContains($name, PermissionSeeder::catalogNames());
        }
    }

    public function test_audit_resource_filters_follow_the_current_registry_independent_of_pagination(): void
    {
        $this->administrator();
        foreach (AuditRegistry::resources() as $key => $model) {
            $this->assertTrue(Schema::hasTable((new $model)->getTable()), $key);
        }
        $expected = [...array_keys(AuditRegistry::resources()), 'account_deactivations', 'audit_settings'];
        $result = $this->getJson('/api/audit-resources?per_page=5&search=academy')->assertOk()->json();
        $this->assertEqualsCanonicalizing($expected, $result['resource_keys']);
        $this->assertSame('academy_applicant_details', $result['data'][0]['resource_key']);
        foreach (['invoices', 'companies', 'commissions', 'services', 'packages', 'leads', 'customers'] as $key) {
            $this->assertArrayNotHasKey($key, AuditRegistry::resources());
            $this->assertNotContains($key, $result['resource_keys']);
        }
    }

    public function test_each_builtin_lifecycle_is_audited_and_notification_read_state_is_preserved(): void
    {
        $actor = $this->administrator();
        $owner = Employee::factory()->create();
        $admission = Employee::factory()->create([
            'position_id' => Position::query()->where('system_key', 'admission')->value('id'),
            'status' => 'active',
        ]);
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $notifier = app(EducationalWorkflowNotificationService::class);

        // Auditing is opt-in; this lifecycle test verifies the audit trail.
        AuditRegistry::ensureSettings();
        AuditResourceSetting::query()->update(['enabled' => true]);

        $services = [
            'scholarship' => ['detail' => 'scholarship_applicant_details', 'admission' => true],
            'test_offer' => ['detail' => 'test_applicant_details', 'admission' => false],
            'academy' => ['detail' => 'academy_applicant_details', 'admission' => false],
        ];

        foreach ($services as $service => $config) {
            $detail = $config['detail'];
            $payload = [
                'responsible_employee_id' => $owner->id,
                'currency_id' => $currency->id,
                'discount' => 10,
            ];
            // Admission assignment is valid only for scholarship/private.
            if ($config['admission']) {
                $payload['admission_employee_id'] = $admission->id;
            }
            $created = $this->createBuiltInContract($service, $payload);
            $contract = Contract::findOrFail($created['id']);
            $line = ApplicantContract::where('contract_id', $contract->id)->firstOrFail();
            foreach (['contracts' => $contract->id, 'applicant_contracts' => $line->id, $detail => $contract->applicant_id] as $resource => $id) {
                $query = AuditLog::where('subject_type', $resource)->where('event', 'created');
                $this->assertTrue($resource === $detail ? $query->exists() : $query->where('auditable_id', $id)->exists(), $resource);
            }
            $notificationKey = "contract.generated.{$contract->id}";
            if ($config['admission']) {
                $generated = Notification::where('notification_key', $notificationKey)->firstOrFail();
                $this->assertSame($service, $generated->data['service']);
                $this->assertSame('/contracts/'.str_replace('_', '-', $service).'?contractId='.$contract->id, $generated->go_to_url);
                $this->assertSame([$admission->user_id], $generated->recipients()->pluck('users.id')->all());
                $generated->recipients()->updateExistingPivot($admission->user_id, ['is_read' => true]);
                $notifier->contractGenerated($contract, $actor->id);
                $this->assertSame(1, Notification::where('notification_key', $notificationKey)->count());
                $this->assertEquals(1, DB::table('notification_user')->where('notification_id', $generated->id)->value('is_read'));
            }
            else {
                $this->assertNull(Notification::where('notification_key', $notificationKey)->first());
            }

            $payment = $this->postJson('/api/incomes', [
                'applicant_id' => $contract->applicant_id, 'applicant_contract_id' => $line->id,
                'type' => 'Service', 'amount' => 25, 'quantity' => 1,
                'currency_id' => $currency->id, 'rate' => 1, 'date' => now()->toDateString(),
            ])->assertCreated()->json('data');
            $income = Income::findOrFail($payment['id']);
            $recorded = Notification::where('notification_key', "income.recorded.{$income->id}")->firstOrFail();
            $this->assertSame([$owner->user_id], $recorded->recipients()->pluck('users.id')->all());
            $recorded->recipients()->updateExistingPivot($owner->user_id, ['is_read' => true]);
            $notifier->incomeRecorded($income, $actor->id);
            $this->assertSame(1, Notification::where('notification_key', $recorded->notification_key)->count());
            $this->assertEquals(1, DB::table('notification_user')->where('notification_id', $recorded->id)->value('is_read'));
            $this->assertDatabaseHas('audit_logs', ['subject_type' => 'incomes', 'auditable_id' => $income->id, 'event' => 'created']);
            $this->assertEquals(25, $line->fresh()->amount_paid);
            $this->getJson('/api/incomes/'.$income->id.'/receipt')->assertOk()->assertJsonPath('data.serial_number', $income->receipt_number);
            $this->assertDatabaseHas('tasks', ['applicant_contract_id' => $line->id]);
        }
    }

    private function administrator(): User
    {
        $user = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }
}
