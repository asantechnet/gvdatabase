<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Income;
use App\Models\Role;
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

        // Formal receipt identity: a serial in the receipt shape, the party,
        // the payment date and the receiving company.
        $this->assertSame($income->receipt_number, $payload['serial_number']);
        $this->assertStringStartsWith('RCPT-', $payload['serial_number']);
        $this->assertSame($applicant->name, $payload['applicant']['name']);
        $this->assertSame($applicant->contact, $payload['applicant']['contact']);
        $this->assertSame($income->date->toDateString(), $payload['date']);
        $this->assertSame('Asantech Ltd', $payload['company']['name']);
        $this->assertSame('#123456', $payload['company']['primary_color']);
        $this->assertSame('#654321', $payload['company']['secondary_color']);
        $this->assertArrayNotHasKey('letterhead', $payload);

        // The payment block names the income record so it cannot be confused
        // with another receipt for the same applicant.
        $this->assertSame($income->type, $payload['income']['name']);
        $this->assertSame($income->id, $payload['income']['id']);
        $this->assertEqualsWithDelta(125.0, $payload['totals']['paid_amount'], 0.001);

        // A receipt covers exactly one service: the line it was paid against.
        $this->assertCount(1, $payload['services']);
        $this->assertSame('scholarship', $payload['services'][0]['service']);
        $this->assertEqualsWithDelta(150.0, $payload['totals']['total_fee'], 0.001);
        $this->assertEqualsWithDelta(25.0, $payload['totals']['balance'], 0.001);

        // Signature/stamp section: the company signature is exposed.
        $this->assertSame('Asantech Ltd', $payload['signature']['name']);
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
            'revaluation_reason' => 'Fee corrected after review',
        ], ['Accept' => 'application/json'])->assertOk();

        $incomeAudit = AuditLog::query()
            ->where('auditable_type', Income::class)
            ->where('auditable_id', $income->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($incomeAudit, 'The income update must be audited.');
        $this->assertSame($income->receipt_number, $incomeAudit->subject_label);

        $this->patch('/api/settings/'.Setting::query()->firstOrFail()->id, [
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

        $contract = Contract::factory()->create([
            'applicant_id' => $applicant->id,
            'status' => 'active',
            'is_cancelled' => false,
        ]);
        $line = ApplicantContract::query()->create([
            'contract_id' => $contract->id,
            'service' => 'scholarship',
            'fee' => 150,
            'currency_id' => $currency->id,
            'currency_rate' => 1,
            'applicant_id' => $applicant->id,
        ]);
        Sanctum::actingAs($actor, ['access']);
        $income = Income::create([
            'applicant_id' => $applicant->id,
            'applicant_contract_id' => $line->id,
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
            'off_day_allowance_days_per_year' => 20,
            'website_title' => 'Asantech',
            'website_address' => 'https://example.com',
            'company_email' => 'people@example.com',
            'timezone' => 'Asia/Kabul',
            'phone' => '+93 700 000 000',
            'physical_address' => 'Kabul',
            'short_company_name' => 'Asantech',
            'original_company_name' => 'Asantech Ltd',
            'business_licence' => 'LIC-001',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
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
