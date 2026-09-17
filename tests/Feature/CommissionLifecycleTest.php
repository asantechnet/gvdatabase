<?php

namespace Tests\Feature;

use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Position;
use App\Models\OutsourcingAccrual;
use App\Models\OutsourcingSettlement;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_is_attributed_snapshotted_settled_and_posted_as_an_expense(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1, 'code' => 'USD']);
        $service = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 100]);
        $customer = Customer::factory()->create();
        $customerService = CustomerService::create(['customer_id' => $customer->id, 'service_id' => $service->id, 'fee' => 100, 'currency_id' => $currency->id]);
        $employee = Employee::factory()->create(['employment_type' => 'commission', 'salary' => null]);
        $otherEmployee = Employee::factory()->create(['employment_type' => 'commission', 'salary' => null]);
        $employee->commissionServices()->attach($service->id, ['percentage' => 10]);
        $otherEmployee->commissionServices()->attach($service->id, ['percentage' => 20]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'currency_id' => $currency->id, 'base_currency_id' => $currency->id,
            'subtotal' => 100, 'tax_percent' => 10, 'discount' => 10, 'amount' => 100, 'base_amount' => 100,
        ]);
        $invoice->services()->attach($service->id, [
            'customer_service_id' => $customerService->id,
            'employee_id' => $employee->id, 'snapshot_commission_percentage' => 10,
            'snapshot_name' => $service->type, 'snapshot_service_fee' => 100,
            'snapshot_service_currency_code' => 'USD', 'snapshot_service_currency_symbol' => '$',
            'snapshot_service_rate' => 1, 'snapshot_cnv_curr_shrt' => 'USD', 'snapshot_base_curr_shrt' => 'USD',
            'snapshot_rate' => 1, 'snapshot_invoice_rate' => 1, 'snapshot_converted_fee' => 100,
            'quantity' => 1, 'line_total' => 100,
        ]);

        $income = Income::create([
            'invoice_id' => $invoice->id, 'currency_id' => $currency->id, 'base_currency_id' => $currency->id,
            'type' => 'Service', 'description' => 'Partial receipt', 'amount' => 50, 'quantity' => 1,
            'cnv_curr_shrt' => 'USD', 'base_curr_shrt' => 'USD', 'rate' => 1,
            'invoice_amount' => 50, 'date' => '2026-09-01',
        ]);

        $accrual = CommissionAccrual::sole();
        $this->assertSame($employee->id, $accrual->employee_id);
        $this->assertSame(45.0, (float) $accrual->eligible_base_amount);
        $this->assertSame(4.5, (float) $accrual->commission_base_amount);
        $this->assertFalse($otherEmployee->commissionAccruals()->exists());

        $employee->commissionServices()->updateExistingPivot($service->id, ['percentage' => 30]);
        $this->assertSame(10.0, (float) $accrual->fresh()->percentage);
        $this->assertSame(4.5, (float) $accrual->fresh()->commission_base_amount);

        Sanctum::actingAs($this->superAdmin(), ['access']);
        $this->postJson('/api/commissions/adjustments', [
            'employee_id' => $employee->id, 'base_currency_id' => $currency->id,
            'effective_date' => '2026-09-02', 'amount' => -1.5,
            'reason' => 'Customer refund clawback',
        ])->assertCreated()->assertJsonPath('data.entry_type', 'adjustment');
        $settlementId = $this->postJson('/api/commission-settlements', [
            'employee_id' => $employee->id, 'base_currency_id' => $currency->id,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
        ])->assertCreated()->assertJsonPath('data.status', 'approved')->json('data.id');

        $this->putJson("/api/incomes/{$income->id}", ['amount' => 40])->assertUnprocessable()
            ->assertJsonPath('errors.income.0', 'business.commission_payment_reserved');

        Sanctum::actingAs($this->superAdmin(), ['access']);
        $this->postJson("/api/commission-settlements/{$settlementId}/pay", ['payment_date' => '2026-10-01'])->assertOk();

        $settlement = CommissionSettlement::findOrFail($settlementId);
        $this->assertSame('paid', $settlement->status);
        $this->assertSame(3.0, (float) $settlement->gross_base_amount);
        $this->assertSame('paid', $accrual->fresh()->status);
        $this->assertNotNull($settlement->expense_id);
        $expense = Expense::findOrFail($settlement->expense_id);
        $this->assertSame('Commissions', $expense->type);
        $this->assertSame(3.0, (float) $expense->amount);
        $this->putJson("/api/expenses/{$expense->id}", ['amount' => 5])->assertUnprocessable();
    }

    public function test_cancelling_an_unpaid_settlement_releases_its_accruals(): void
    {
        [$currency, $employee, $accrual] = $this->pendingAccrual();
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $id = $this->postJson('/api/commission-settlements', [
            'employee_id' => $employee->id, 'base_currency_id' => $currency->id,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/commission-settlements/{$id}/cancel")->assertOk();
        $this->assertSame('pending', $accrual->fresh()->status);
        $this->assertNull($accrual->fresh()->commission_settlement_id);
    }

    public function test_settlement_can_pay_only_the_explicitly_selected_accruals(): void
    {
        [$currency, $employee, $accrual] = $this->pendingAccrual();
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $adjustmentId = $this->postJson('/api/commissions/adjustments', [
            'employee_id' => $employee->id,
            'base_currency_id' => $currency->id,
            'effective_date' => '2026-09-02',
            'amount' => 2,
            'reason' => 'Approved service quality bonus',
        ])->assertCreated()->json('data.id');

        $settlement = $this->postJson('/api/commission-settlements', [
            'employee_id' => $employee->id,
            'base_currency_id' => $currency->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'accrual_ids' => [$accrual->id],
        ])->assertCreated()->json('data');

        $this->assertSame(5.0, (float) $settlement['gross_base_amount']);
        $this->assertSame('approved', $accrual->fresh()->status);
        $this->assertSame('pending', CommissionAccrual::findOrFail($adjustmentId)->status);
    }

    public function test_non_super_admin_creator_still_needs_an_independent_approver(): void
    {
        [$currency, $employee] = $this->pendingAccrual();
        $managerRole = Role::firstOrCreate(['name' => 'commission_manager']);
        $managerRole->permissions()->sync(Permission::query()
            ->whereIn('name', ['manage_commission_settlements', 'approve_commissions'])
            ->pluck('id'));
        $manager = User::factory()->create(['acc_status' => 'active']);
        $manager->roles()->attach($managerRole);
        Sanctum::actingAs($manager, ['access']);

        $settlementId = $this->postJson('/api/commission-settlements', [
            'employee_id' => $employee->id,
            'base_currency_id' => $currency->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

        $this->postJson("/api/commission-settlements/{$settlementId}/approve")
            ->assertUnprocessable();
    }

    public function test_commissionable_invoice_service_derives_and_snapshots_the_customer_service_delivery_owner(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 100]);
        $employee = Employee::factory()->create(['employment_type' => 'commission', 'salary' => null]);
        $employee->commissionServices()->attach($service->id, ['percentage' => 12.5]);
        $customerService = CustomerService::create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'fee' => 175,
            'currency_id' => $currency->id,
        ]);
        $payload = [
            'customer_id' => $customer->id, 'currency_id' => $currency->id,
            'services' => [['customer_service_id' => $customerService->id, 'quantity' => 1]], 'tax_percent' => 0,
        ];

        // A forged client-side employee_id is deliberately ignored. The
        // customer-service delivery assignment is the authoritative source.
        $payload['services'][0]['employee_id'] = Employee::factory()->create(['employment_type' => 'commission'])->id;
        $invoiceId = $this->postJson('/api/invoices', $payload)->assertCreated()->json('data.id');
        $line = DB::table('invoice_service')->where('invoice_id', $invoiceId)->sole();
        $this->assertSame($employee->id, (int) $line->employee_id);
        $this->assertSame(12.5, (float) $line->snapshot_commission_percentage);
        $this->assertSame(175.0, (float) $line->snapshot_service_fee);
        $this->assertSame($customerService->id, (int) $line->customer_service_id);
    }

    public function test_one_customer_service_can_accrue_delivery_commissions_for_multiple_employees(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 100]);
        $first = Employee::factory()->create(['employment_type' => 'commission']);
        $second = Employee::factory()->create(['employment_type' => 'commission']);
        $first->commissionServices()->attach($service->id, ['percentage' => 10]);
        $second->commissionServices()->attach($service->id, ['percentage' => 15]);
        $customerService = CustomerService::create(['customer_id' => $customer->id, 'service_id' => $service->id, 'currency_id' => $currency->id]);
        $customerService->deliveryEmployees()->sync([
            $first->id => ['percentage' => 10],
            $second->id => ['percentage' => 15],
        ]);

        $invoiceId = $this->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'services' => [['customer_service_id' => $customerService->id]],
            'tax_percent' => 0,
        ])->assertCreated()->json('data.id');

        $line = DB::table('invoice_service')->where('invoice_id', $invoiceId)->sole();
        $this->assertDatabaseCount('invoice_service_commission_recipients', 2);
        Income::create([
            'invoice_id' => $invoiceId, 'currency_id' => $currency->id, 'base_currency_id' => $currency->id,
            'type' => 'Service', 'description' => 'Receipt', 'amount' => 100, 'quantity' => 1,
            'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $currency->code, 'rate' => 1,
            'invoice_amount' => 100, 'date' => '2026-09-01',
        ]);

        $this->assertDatabaseHas('commission_accruals', [
            'invoice_service_id' => $line->id, 'employee_id' => $first->id, 'commission_role' => 'delivery', 'percentage' => 10,
        ]);
        $this->assertDatabaseHas('commission_accruals', [
            'invoice_service_id' => $line->id, 'employee_id' => $second->id, 'commission_role' => 'delivery', 'percentage' => 15,
        ]);
    }

    public function test_employee_profiles_allow_zero_base_salary_for_salaried_and_commission_employees(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $position = Position::factory()->create(['salary' => 0]);
        $currency = Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1]);
        $service = Service::factory()->create(['currency_id' => $currency->id]);

        $basePayload = [
            'father_name' => 'Ahmad Khan', 'contact' => '0700000000', 'education_lvl' => 'Bachelor',
            'position_id' => $position->id, 'addr' => 'Kabul, Afghanistan', 'join_date' => '2026-09-02',
            'create_user' => false,
        ];

        $this->postJson('/api/employees', $basePayload + [
            'name' => 'Zero Salary Employee', 'id_number' => 'ZERO-SALARY-001',
            'employment_type' => 'salaried', 'salary' => 0,
        ])->assertCreated()->assertJsonPath('data.salary', 0);

        $this->postJson('/api/employees', $basePayload + [
            'name' => 'Commission Employee', 'id_number' => 'COMMISSION-001',
            'employment_type' => 'commission', 'salary' => 0,
            'commissions' => [['service_id' => $service->id, 'percentage' => 10]],
        ])->assertCreated()->assertJsonPath('data.salary', 0);
    }

    public function test_commission_employee_can_receive_a_regular_salary_as_well(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1]);
        $employee = Employee::factory()->create(['employment_type' => 'commission', 'salary' => 500]);

        $this->postJson('/api/salaries', [
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => today()->format('Y-m'),
            'amountPaid' => 500,
            'date' => today()->toDateString(),
            'deductions' => [],
        ])->assertCreated()->assertJsonPath('data.employee_id', $employee->id);
    }

    public function test_outsourced_customer_service_is_snapshotted_accrued_and_paid_to_its_company(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1]);
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 100]);
        $company = Company::create(['legal_name' => 'External Delivery Ltd', 'is_active' => true]);
        $assignment = CustomerService::create([
            'customer_id' => $customer->id, 'service_id' => $service->id, 'currency_id' => $currency->id,
            'fee' => 100, 'outsourcing_company_id' => $company->id, 'outsourcing_percentage' => 25,
        ]);

        $invoiceId = $this->postJson('/api/invoices', [
            'customer_id' => $customer->id, 'currency_id' => $currency->id,
            'services' => [['customer_service_id' => $assignment->id]], 'tax_percent' => 0,
        ])->assertCreated()->json('data.id');
        $line = DB::table('invoice_service')->where('invoice_id', $invoiceId)->sole();
        $this->assertSame($company->id, (int) $line->outsourcing_company_id);
        $this->assertSame(25.0, (float) $line->snapshot_outsourcing_percentage);

        Income::create(['invoice_id' => $invoiceId, 'currency_id' => $currency->id, 'base_currency_id' => $currency->id,
            'type' => 'Service', 'description' => 'Receipt', 'amount' => 100, 'quantity' => 1,
            'cnv_curr_shrt' => $currency->code, 'base_curr_shrt' => $currency->code, 'rate' => 1, 'invoice_amount' => 100, 'date' => '2026-09-01']);
        $accrual = OutsourcingAccrual::sole();
        $this->assertSame(25.0, (float) $accrual->outsourcing_base_amount);

        $settlementId = $this->postJson('/api/outsourcing/settlements', ['company_id' => $company->id, 'base_currency_id' => $currency->id,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30'])->assertCreated()->assertJsonPath('data.status', 'approved')->json('data.id');
        $this->postJson("/api/outsourcing/settlements/{$settlementId}/pay", ['payment_date' => '2026-10-01'])->assertOk();
        $this->assertSame('paid', OutsourcingSettlement::findOrFail($settlementId)->status);
    }

    public function test_outsourcing_adjustments_are_auditable_pending_ledger_entries(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1]);
        $company = Company::create(['legal_name' => 'External Delivery Ltd', 'is_active' => true]);

        $this->postJson('/api/outsourcing/adjustments', [
            'company_id' => $company->id,
            'base_currency_id' => $currency->id,
            'effective_date' => '2026-09-16',
            'amount' => -12.5,
            'reason' => 'Supplier overcharge correction',
        ])->assertCreated()->assertJsonPath('data.entry_type', 'adjustment');

        $entry = OutsourcingAccrual::sole();
        $this->assertSame(-12.5, (float) $entry->outsourcing_base_amount);
        $this->assertSame('pending', $entry->status);
    }

    private function pendingAccrual(): array
    {
        $currency = Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1, 'code' => 'USD']);
        $service = Service::factory()->create(['currency_id' => $currency->id]);
        $customer = Customer::factory()->create();
        $customerService = CustomerService::create(['customer_id' => $customer->id, 'service_id' => $service->id, 'currency_id' => $currency->id]);
        $employee = Employee::factory()->create(['employment_type' => 'commission', 'salary' => null]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'currency_id' => $currency->id, 'base_currency_id' => $currency->id, 'amount' => 100, 'subtotal' => 100]);
        $invoice->services()->attach($service->id, [
            'customer_service_id' => $customerService->id,
            'employee_id' => $employee->id, 'snapshot_commission_percentage' => 10,
            'snapshot_name' => $service->type, 'snapshot_service_fee' => 100,
            'snapshot_service_currency_code' => 'USD', 'snapshot_service_currency_symbol' => '$',
            'snapshot_service_rate' => 1, 'snapshot_cnv_curr_shrt' => 'USD', 'snapshot_base_curr_shrt' => 'USD',
            'snapshot_rate' => 1, 'snapshot_invoice_rate' => 1, 'snapshot_converted_fee' => 100, 'quantity' => 1, 'line_total' => 100,
        ]);
        Income::create([
            'invoice_id' => $invoice->id, 'currency_id' => $currency->id, 'base_currency_id' => $currency->id,
            'type' => 'Service', 'description' => 'Receipt', 'amount' => 50, 'quantity' => 1,
            'cnv_curr_shrt' => 'USD', 'base_curr_shrt' => 'USD', 'rate' => 1, 'invoice_amount' => 50, 'date' => '2026-09-01',
        ]);

        return [$currency, $employee, CommissionAccrual::sole()];
    }

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach($role);

        return $user;
    }
}
