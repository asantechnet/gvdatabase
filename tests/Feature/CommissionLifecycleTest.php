<?php

namespace Tests\Feature;

use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Position;
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
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->superAdmin(), ['access']);
        $this->postJson("/api/commission-settlements/{$settlementId}/approve")->assertOk();
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
            'customer_id' => $customer->id, 'currency_id' => $currency->id, 'class_type' => 'Standard',
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
