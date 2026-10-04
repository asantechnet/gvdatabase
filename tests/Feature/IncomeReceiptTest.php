<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\Currency;
use App\Models\CustomerPackage;
use App\Models\Employee;
use App\Models\Income;
use App\Models\Package;
use App\Models\Role;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncomeReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        Storage::fake('public');
    }

    public function test_receipt_returns_formal_educational_receipt_payload(): void
    {
        $user = $this->actingAsSuperAdmin();
        $this->postJson('/api/settings', $this->validSettings(), ['Accept' => 'application/json'])->assertCreated();
        [$applicant, $income] = $this->createCaseWithPayment($user);

        $response = $this->getJson("/api/incomes/{$income->id}/receipt")
            ->assertOk()
            ->assertJsonPath('success', true);

        $payload = $response->json('data');

        // Formal receipt identity: a serial in the invoice shape, the party,
        // the payment date and the receiving company.
        $this->assertSame($income->receipt_number, $payload['serial_number']);
        $this->assertStringStartsWith('RCPT-', $payload['serial_number']);
        $this->assertSame($applicant->name, $payload['applicant']['name']);
        $this->assertSame($applicant->contact, $payload['applicant']['contact']);
        $this->assertSame($income->date->toDateString(), $payload['date']);
        $this->assertSame('Asantech Ltd', $payload['company']['name']);
        $this->assertNotNull($payload['letterhead']);
        $this->assertArrayHasKey('safe', $payload['letterhead']);

        // The payment block names the income record so it cannot be confused
        // with another receipt for the same applicant.
        $this->assertSame($income->type, $payload['income']['name']);
        $this->assertSame($income->id, $payload['income']['id']);
        $this->assertEqualsWithDelta(125.0, $payload['totals']['paid_amount'], 0.001);

        // Services are listed for the case.
        $this->assertCount(2, $payload['services']);
        $this->assertSame('Student visa processing', $payload['services'][0]['name']);
        $this->assertEqualsWithDelta(250.0, $payload['totals']['total_fee'], 0.001);
        $this->assertEqualsWithDelta(125.0, $payload['totals']['balance'], 0.001);

        // Signature/stamp section: the company signature is exposed.
        $this->assertSame('Asantech Ltd', $payload['signature']['name']);
    }

    public function test_receipt_letterhead_follows_the_selected_source(): void
    {
        $user = $this->actingAsSuperAdmin();
        [$applicant, $income] = $this->createCaseWithPayment($user);

        $this->postJson('/api/settings', [
            ...$this->validSettings(),
            'receipt_letterhead' => $this->fakePng('receipt.png'),
            'receipt_letterhead_source' => 'custom',
            'receipt_letterhead_safe_x' => 7.5,
            'receipt_letterhead_safe_y' => 20,
            'receipt_letterhead_safe_width' => 85,
            'receipt_letterhead_safe_height' => 60,
        ], ['Accept' => 'application/json'])->assertCreated();

        $payload = $this->getJson("/api/incomes/{$income->id}/receipt")->assertOk()->json('data');

        $this->assertSame('custom', $payload['letterhead']['source']);
        $this->assertNotNull($payload['letterhead']['url']);
        $this->assertEqualsWithDelta(7.5, $payload['letterhead']['safe']['x'], 0.001);
        $this->assertEqualsWithDelta(60.0, $payload['letterhead']['safe']['height'], 0.001);

        // Switching the source to "contract" reuses the general company
        // contract letterhead without a separate upload.
        $setting = Setting::query()->firstOrFail();
        $this->patch("/api/settings/{$setting->id}", [
            'receipt_letterhead_source' => 'contract',
        ], ['Accept' => 'application/json'])->assertOk();

        $payload = $this->getJson("/api/incomes/{$income->id}/receipt")->assertOk()->json('data');
        $this->assertSame('contract', $payload['letterhead']['source']);
    }

    public function test_receipt_endpoint_is_protected_by_view_incomes(): void
    {
        $user = $this->actingAsSuperAdmin();
        [$applicant, $income] = $this->createCaseWithPayment($user);

        // A user without view_incomes cannot read the receipt payload.
        $restricted = User::factory()->create();
        Sanctum::actingAs($restricted, ['access']);
        $this->getJson("/api/incomes/{$income->id}/receipt")->assertForbidden();

        // The finance officer role holds view_incomes and may read it.
        $officer = User::factory()->create();
        $officer->roles()->attach(Role::query()->where('name', 'finance_officer')->firstOrFail());
        Sanctum::actingAs($officer, ['access']);
        $this->getJson("/api/incomes/{$income->id}/receipt")->assertOk();
    }

    public function test_recording_an_income_notifies_the_case_owner(): void
    {
        $user = $this->actingAsSuperAdmin();
        // The case owner is notified, so the applicant needs a responsible
        // employee whose user is distinct from the acting admin.
        $employee = Employee::factory()->create();
        $applicant = Applicant::factory()->create([
            'status' => 'active',
            'responsible_employee_id' => $employee->id,
        ]);

        $this->postJson('/api/incomes', [
            'applicant_id' => $applicant->id,
            'type' => 'Service',
            'description' => 'Visa fee',
            'amount' => 80,
            'quantity' => 1,
            'currency_id' => Currency::factory()->create(['is_base' => true, 'rate' => 1])->id,
            'rate' => 1,
            'date' => '2026-09-29',
        ], ['Accept' => 'application/json'])->assertCreated();

        $income = Income::query()->latest('id')->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'notification_key' => "income.recorded.{$income->id}",
            'type' => 'finance',
        ]);
    }

    /**
     * Every change to a linked income lands in the audit log with the
     * receipt number as the subject label.
     */
    public function test_income_and_letterhead_changes_are_audited(): void
    {
        $user = $this->actingAsSuperAdmin();
        $this->postJson('/api/settings', $this->validSettings(), ['Accept' => 'application/json'])->assertCreated();
        [$applicant, $income] = $this->createCaseWithPayment($user);

        $this->putJson("/api/incomes/{$income->id}", [
            'amount' => 90,
        ], ['Accept' => 'application/json'])->assertOk();

        $incomeAudit = AuditLog::query()
            ->where('auditable_type', Income::class)
            ->where('auditable_id', $income->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($incomeAudit, 'The income update must be audited.');
        $this->assertSame($income->receipt_number, $incomeAudit->subject_label);

        $this->patch("/api/settings/".Setting::query()->firstOrFail()->id, [
            'receipt_letterhead_safe_x' => 9.5,
        ], ['Accept' => 'application/json'])->assertOk();

        $settingAudit = AuditLog::query()
            ->where('auditable_type', Setting::class)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($settingAudit, 'The letterhead change must be audited.');
        // Artwork columns are hidden from audit change payloads but the audit
        // row itself exists for the safe-zone edit.
        $fields = collect($settingAudit->changed_columns);
        $this->assertContains('receipt_letterhead_safe_x', $fields);
        $this->assertNotContains('receipt_letterhead', $fields);
    }

    /** @return array{0: Applicant, 1: Income} */
    private function createCaseWithPayment(User $actor): array
    {
        $currency = Currency::factory()->create([
            'code' => 'USD', 'symbol' => '$', 'is_base' => true, 'rate' => 1,
        ]);
        $applicant = Applicant::factory()->create(['name' => 'Zahra Ahmadi', 'status' => 'active']);

        $service = Service::factory()->create(['type' => 'Student visa processing']);
        $packageService = Service::factory()->create(['type' => 'Package service']);
        $package = Package::query()->create([
            'name' => 'Language course bundle',
            'code' => 'PKG-LANG',
            'is_active' => true,
        ]);
        $customerPackage = CustomerPackage::query()->create([
            'applicant_id' => $applicant->id,
            'package_id' => $package->id,
            'currency_id' => $currency->id,
            'fee' => 100,
            'purchased_at' => now(),
            'status' => 'issued',
            // Required idempotency key for a package issued to a case.
            'issuance_key' => "applicant:{$applicant->id}:package:{$package->id}",
        ]);
        $contract = Contract::factory()->create([
            'applicant_id' => $applicant->id,
            'status' => 'active',
            'is_cancelled' => false,
        ]);
        ContractService::query()->create([
            'contract_id' => $contract->id,
            'service_id' => $service->id,
            'fee' => 150,
            'currency_id' => $currency->id,
            'applicant_id' => $applicant->id,
        ]);
        // A second line pointing at the package row gives the receipt its
        // package service line; `customer_package_id` links it to the package.
        // It needs its own service because (contract, service) is unique.
        ContractService::query()->create([
            'contract_id' => $contract->id,
            'service_id' => $packageService->id,
            'customer_package_id' => $customerPackage->id,
            'fee' => 100,
            'currency_id' => $currency->id,
            'applicant_id' => $applicant->id,
        ]);

        Sanctum::actingAs($actor, ['access']);
        $income = Income::create([
            'applicant_id' => $applicant->id,
            'type' => 'Service',
            'description' => 'First instalment',
            'amount' => 125,
            'quantity' => 1,
            'currency_id' => $currency->id,
            'rate' => 1,
            'date' => '2026-09-28',
            'recorded_by' => $actor->id,
            // Currency snapshot columns are NOT NULL and normally filled by
            // IncomeController::applyCurrencySnapshot().
            'cnv_curr_shrt' => $currency->code,
            'base_curr_shrt' => $currency->code,
        ]);

        return [$applicant, $income];
    }

    /** A real 1x1 PNG, so uploads do not depend on the GD extension. */
    private function fakePng(string $name): UploadedFile
    {
        $data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, (string) $data);
    }

    private function validSettings(): array
    {
        return [
            'paid_leave_days_per_year' => 20,
            'website_title' => 'Asantech',
            'website_address' => 'https://example.com',
            'company_email' => 'people@example.com',
            'timezone' => 'Asia/Kabul',
            'phone' => '+93 700 000 000',
            'physical_address' => 'Kabul',
            'short_company_name' => 'Asantech',
            'original_company_name' => 'Asantech Ltd',
            'business_licence' => 'LIC-001',
        ];
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);

        return $user;
    }
}
