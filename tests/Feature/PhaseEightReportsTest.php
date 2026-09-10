<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetAllocation;
use App\Models\Attendance;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseEightReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create([
            'navigation_clr' => '#111111', 'cards_clr' => '#ffffff', 'footer_clr' => '#111111',
            'paid_leave_days_per_year' => 20, 'website_title' => 'Report Test', 'website_address' => 'https://example.test',
            'company_email' => 'reports@example.test', 'timezone' => 'Asia/Kabul', 'phone' => '123',
            'physical_address' => 'Kabul', 'short_company_name' => 'RT', 'original_company_name' => 'Report Test Company',
        ]);
    }

    public function test_financial_report_uses_real_rows_snapshots_and_normalized_contract(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        [$currency, $customer] = $this->financialReferences();
        $invoice = Invoice::create([
            'customer_id' => $customer->id, 'currency_id' => $currency->id, 'class_type' => 'Standard',
            'deptor' => $customer->name, 'status' => 'Issued', 'currency_name' => $currency->name,
            'currency_rate' => 1, 'currency_symbol' => $currency->symbol, 'currency_shortname' => $currency->code,
            'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $currency->code, 'rate' => 1, 'base_amount' => 100,
            'subtotal' => 100, 'amount' => 100, 'discount' => 0,
        ]);
        $invoice->forceFill(['created_at' => '2026-08-05 10:00:00'])->saveQuietly();
        Income::create([
            'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'currency_id' => $currency->id,
            'type' => 'Invoice payment', 'description' => 'Partial payment', 'amount' => 25, 'quantity' => 2,
            'rate' => 1, 'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $currency->code, 'date' => '2026-08-06',
        ]);
        Expense::create([
            'customer_id' => $customer->id, 'currency_id' => $currency->id, 'type' => 'Travel',
            'description' => 'Site visit', 'amount' => 10, 'quantity' => 2, 'rate' => 1, 'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $currency->code, 'date' => '2026-08-07',
        ]);

        $response = $this->getJson('/api/reports/generate/financial?start_date=2026-08-01&end_date=2026-08-31')
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.type', 'financial')
            ->assertJsonPath('data.meta.timezone', 'Asia/Kabul')->assertJsonCount(5, 'data.sections');

        $sections = collect($response->json('data.sections'))->keyBy('key');
        $this->assertSame(100.0, (float) $sections['invoices']['rows'][0]['base_amount']);
        $this->assertSame(50.0, (float) $sections['invoices']['rows'][0]['outstanding_base']);
        $this->assertSame(50.0, (float) $sections['income']['totals']['base_amount_by_base']['TST']);
        $this->assertSame(20.0, (float) $sections['expenses']['totals']['base_amount_by_base']['TST']);
        $this->assertSame(30.0, (float) $sections['net_cash_flow']['totals']['net_base']['TST']);
    }

    public function test_financial_report_uses_asset_allocations_instead_of_the_removed_staff_relationship(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $employee = Employee::factory()->create(['name' => 'Allocated Employee']);
        $asset = Asset::factory()->create(['item' => 'Laptop', 'quantity' => 2]);
        AssetAllocation::create(['asset_id' => $asset->id, 'employee_id' => $employee->id, 'quantity' => 1]);

        $response = $this->getJson('/api/reports/generate/financial?start_date=2026-08-01&end_date=2026-08-31')
            ->assertOk();

        $assetSection = collect($response->json('data.sections'))->firstWhere('key', 'asset_assets');
        $this->assertNotNull($assetSection);
        $this->assertSame('Allocated Employee', $assetSection['rows'][0]['assigned_to']);
    }

    public function test_attendance_report_filters_incomplete_sessions_and_preserves_working_totals(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $employee = Employee::factory()->create(['join_date' => '2026-01-01']);
        Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-08-10', 'status' => 'present', 'logged_at' => '08:00:00',
            'gross_minutes' => null, 'net_worked_minutes' => null, 'overtime_minutes' => 0,
            'missing_punch_state' => 'check_out',
        ]);
        Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-08-11', 'status' => 'present', 'logged_at' => '08:00:00',
            'gross_minutes' => 510, 'break_minutes' => 30, 'net_worked_minutes' => 480,
            'required_minutes' => 450, 'overtime_minutes' => 30, 'missing_punch_state' => 'none',
        ]);

        $response = $this->getJson('/api/reports/generate/attendance?start_date=2026-08-01&end_date=2026-08-31&attendance_state=incomplete')
            ->assertOk()->assertJsonCount(1, 'data.sections.0.rows')
            ->assertJsonPath('data.sections.0.totals.incomplete', 1);
        $this->assertSame('check_out', $response->json('data.sections.0.rows.0.punch_state'));
    }

    public function test_csv_and_print_exports_replace_the_missing_pdf_facade(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $this->financialReferences();

        $csv = $this->get('/api/reports/financial/export?start_date=2026-08-01&end_date=2026-08-31&format=csv')
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->streamedContent());

        $this->get('/api/reports/financial/export?start_date=2026-08-01&end_date=2026-08-31&format=print')
            ->assertOk()->assertSee('Report Test Company')->assertSee('Print / Save PDF');
    }

    public function test_catalog_and_generation_are_permission_aware(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);
        $this->getJson('/api/reports/catalog')->assertForbidden();
        $this->getJson('/api/reports/generate/financial?start_date=2026-08-01&end_date=2026-08-31')->assertForbidden();
    }

    public function test_repaired_legacy_routes_share_the_normalized_contract(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $this->financialReferences();
        foreach (['invoices', 'expenses', 'expences', 'incomes', 'salaries', 'visitors'] as $endpoint) {
            $this->getJson("/api/reports/{$endpoint}?start_date=2026-08-01&end_date=2026-08-31")
                ->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data' => ['type', 'filters', 'sections', 'meta']]);
        }
    }

    private function financialReferences(): array
    {
        $currency = Currency::firstOrCreate(['code' => 'TST'], [
            'name' => 'Test Currency', 'symbol' => 'T', 'rate' => 1,
            'is_base' => true, 'is_active' => true, 'decimal_places' => 2,
        ]);
        $customer = Customer::firstOrCreate(['name' => 'Acme'], [
            'company' => 'Acme Ltd', 'contact' => '123', 'marketer' => 'Direct', 'date' => '2026-08-01',
        ]);

        return [$currency, $customer];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin'])->id);

        return $user;
    }
}
