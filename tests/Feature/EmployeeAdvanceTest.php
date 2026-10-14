<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($admin, ['access']);

        return $admin;
    }

    private function baseCurrency(): Currency
    {
        return Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1, 'decimal_places' => 2]);
    }

    public function test_recording_an_advance_writes_the_ledger_row_and_payroll_expense(): void
    {
        $this->actingAsAdmin();
        $currency = $this->baseCurrency();
        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/employee-advances', [
            'employee_id' => $employee->id,
            'amount' => 1500,
            'date' => '2026-09-20',
            'remarks' => 'Mid-month advance',
        ])->assertOk();

        $this->assertSame(1500.0, (float) $response->json('data.amount'));
        $this->assertSame($employee->id, $response->json('data.employee_id'));
        $this->assertSame($currency->code, $response->json('data.currency.code'));

        $advance = EmployeeAdvance::firstOrFail();
        $this->assertStringStartsWith('ADV-', $advance->advance_number);
        $this->assertNotNull($advance->expense_id);

        $this->assertDatabaseHas('expenses', [
            'id' => $advance->expense_id,
            'employee_id' => $employee->id,
            'type' => 'Salaries',
        ]);
    }

    public function test_advances_always_use_the_base_currency(): void
    {
        $this->actingAsAdmin();
        $base = $this->baseCurrency();
        $other = Currency::factory()->create(['is_base' => false, 'is_active' => true, 'rate' => 60, 'decimal_places' => 2]);
        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/employee-advances', [
            'employee_id' => $employee->id,
            // A secondary currency is intentionally ignored: payroll is base-only.
            'currency_id' => $other->id,
            'amount' => 900,
            'date' => '2026-09-20',
        ])->assertOk();

        $this->assertSame($base->id, $response->json('data.currencyId'));
        $this->assertSame($base->id, EmployeeAdvance::firstOrFail()->currency_id);
    }

    public function test_advance_validation_rejects_bad_input(): void
    {
        $admin = $this->actingAsAdmin();
        $this->baseCurrency();
        $employee = Employee::factory()->create();

        // Amount must be positive.
        $this->postJson('/api/employee-advances', [
            'employee_id' => $employee->id, 'amount' => 0, 'date' => '2026-09-20',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        // Advance date cannot be in the future.
        $this->postJson('/api/employee-advances', [
            'employee_id' => $employee->id, 'amount' => 100, 'date' => now()->addMonth()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('date');

        // A super-admin's own linked employee is not selectable for payroll.
        $blocked = Employee::factory()->create(['user_id' => $admin->id]);
        $this->postJson('/api/employee-advances', [
            'employee_id' => $blocked->id, 'amount' => 100, 'date' => '2026-09-20',
        ])->assertUnprocessable()->assertJsonValidationErrors('employee_id');

        $this->assertDatabaseCount('employee_advances', 0);
    }

    public function test_advances_can_be_filtered_by_employee_and_searched(): void
    {
        $this->actingAsAdmin();
        $this->baseCurrency();
        $first = Employee::factory()->create();
        $second = Employee::factory()->create();

        $this->postJson('/api/employee-advances', ['employee_id' => $first->id, 'amount' => 500, 'date' => '2026-09-01', 'remarks' => 'Fuel'])->assertOk();
        $this->postJson('/api/employee-advances', ['employee_id' => $second->id, 'amount' => 700, 'date' => '2026-09-02', 'remarks' => 'Medical'])->assertOk();

        $this->getJson('/api/employee-advances')->assertOk()->assertJsonCount(2, 'data');

        $filtered = $this->getJson('/api/employee-advances?employee_id='.$first->id)->assertOk();
        $filtered->assertJsonCount(1, 'data')->assertJsonPath('data.0.remarks', 'Fuel');

        $searched = $this->getJson('/api/employee-advances?search=Medical')->assertOk();
        $searched->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 700);
    }

    public function test_deleting_an_advance_removes_its_expense(): void
    {
        $this->actingAsAdmin();
        $this->baseCurrency();
        $employee = Employee::factory()->create();

        $id = $this->postJson('/api/employee-advances', [
            'employee_id' => $employee->id, 'amount' => 800, 'date' => '2026-09-10',
        ])->assertOk()->json('data.id');

        $expenseId = EmployeeAdvance::findOrFail($id)->expense_id;

        $this->deleteJson("/api/employee-advances/{$id}")->assertOk();

        $this->assertSoftDeleted('employee_advances', ['id' => $id]);
        $this->assertSoftDeleted('expenses', ['id' => $expenseId]);
    }
}
