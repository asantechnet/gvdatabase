<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    /** Payroll derives worked days from the ledger, so tests seed the ledger. */
    private function markMonthPresent(Employee $employee, string $month): void
    {
        $start = Carbon::parse($month.'-01')->startOfDay();
        for ($day = $start->copy(); $day->lte($start->copy()->endOfMonth()); $day->addDay()) {
            if ($day->isFriday()) {
                continue;
            }
            Attendance::create([
                'employee_id' => $employee->id,
                'date' => $day->toDateString(),
                'status' => 'present',
                'logged_at' => '08:00:00',
            ]);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function createSalary(Employee $employee, Currency $currency, array $overrides = []): int
    {
        return $this->postJson('/api/salaries', array_merge([
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => '2026-09',
            'amountPaid' => 1000,
            'date' => '2026-09-30',
            'deductions' => [],
        ], $overrides))->assertCreated()->json('data.id');
    }

    private function recordAdvance(Employee $employee, float $amount, string $date): int
    {
        return $this->postJson('/api/employee-advances', [
            'employee_id' => $employee->id,
            'amount' => $amount,
            'date' => $date,
        ])->assertOk()->json('data.id');
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

        // A new advance owes the whole amount and is open until something
        // reduces it.
        $this->assertSame(EmployeeAdvance::STATUS_OUTSTANDING, $response->json('data.status'));
        $this->assertSame(1500.0, (float) $response->json('data.remainingAmount'));

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

    public function test_an_advance_recorded_after_the_payslip_is_recovered_from_that_unpaid_payslip(): void
    {
        $this->actingAsAdmin();
        $currency = $this->baseCurrency();
        $employee = Employee::factory()->create(['join_date' => '2026-01-01']);
        $this->markMonthPresent($employee, '2026-09');

        $salaryId = $this->createSalary($employee, $currency);

        $advanceId = $this->postJson('/api/employee-advances', [
            'employee_id' => $employee->id,
            'amount' => 300,
            'date' => '2026-09-10',
        ])->assertOk()->json('data.id');

        $salary = Salary::findOrFail($salaryId);
        $this->assertSame($salaryId, EmployeeAdvance::findOrFail($advanceId)->salary_id);
        $this->assertSame(300.0, (float) $salary->advance_recovery);
        $this->assertSame(700.0, (float) $salary->amount_due);

        // Without the recovery state on the resource the ledger can only ever
        // render every advance as outstanding.
        $ledger = $this->getJson('/api/employee-advances')->assertOk();
        $ledger->assertJsonPath('data.0.salaryId', $salaryId);
        $ledger->assertJsonPath('data.0.status', EmployeeAdvance::STATUS_SETTLED);
        $this->assertNotNull($ledger->json('data.0.recoveredAt'));
    }

    public function test_an_advance_cannot_be_recorded_in_a_month_that_already_paid_out(): void
    {
        $this->actingAsAdmin();
        $currency = $this->baseCurrency();
        $employee = Employee::factory()->create(['join_date' => '2026-01-01']);
        $this->markMonthPresent($employee, '2026-09');

        // The payslip paid out before the advance exists, so withholding it
        // now would turn the amount already paid into an overpayment.
        $this->createSalary($employee, $currency, ['initialPayment' => 1000]);

        $this->postJson('/api/employee-advances', [
            'employee_id' => $employee->id,
            'amount' => 300,
            'date' => '2026-09-10',
        ])->assertUnprocessable()->assertJsonValidationErrors('date');

        $this->assertDatabaseCount('employee_advances', 0);
    }

    public function test_an_advance_bigger_than_the_payslip_is_recovered_in_instalments(): void
    {
        $this->actingAsAdmin();
        $currency = $this->baseCurrency();
        $employee = Employee::factory()->create(['join_date' => '2026-01-01']);
        $this->markMonthPresent($employee, '2026-09');
        $this->markMonthPresent($employee, '2026-10');

        $advanceId = $this->recordAdvance($employee, 1500, '2026-09-10');

        // September can only withhold what it can pay.
        $september = Salary::findOrFail($this->createSalary($employee, $currency));
        $this->assertSame(1000.0, (float) $september->advance_recovery);
        $this->assertSame(0.0, (float) $september->amount_due);

        $advance = EmployeeAdvance::findOrFail($advanceId);
        $this->assertSame(EmployeeAdvance::STATUS_PARTIAL, $advance->status);
        $this->assertSame(500.0, $advance->remainingAmount());

        // October collects the remainder.
        $october = Salary::findOrFail($this->createSalary($employee, $currency, [
            'salaryMonth' => '2026-10',
            'date' => '2026-10-31',
        ]));
        $this->assertSame(500.0, (float) $october->advance_recovery);
        $this->assertSame(500.0, (float) $october->amount_due);

        $advance->refresh();
        $this->assertSame(EmployeeAdvance::STATUS_SETTLED, $advance->status);
        $this->assertSame(0.0, $advance->remainingAmount());
    }

    public function test_a_cash_repayment_reduces_the_advance_and_closes_it(): void
    {
        $this->actingAsAdmin();
        $this->baseCurrency();
        $employee = Employee::factory()->create();

        $id = $this->recordAdvance($employee, 800, '2026-09-10');
        $expenseId = EmployeeAdvance::findOrFail($id)->expense_id;

        $this->postJson("/api/employee-advances/{$id}/settlements", [
            'amount' => 300,
            'date' => '2026-09-20',
            'remarks' => 'Cash returned',
        ])->assertOk()
            ->assertJsonPath('data.status', EmployeeAdvance::STATUS_PARTIAL)
            ->assertJsonPath('data.remainingAmount', 500);

        // Cash came back in, so the expense recording cash out shrinks.
        $this->assertDatabaseHas('expenses', ['id' => $expenseId, 'amount' => 500]);

        $this->postJson("/api/employee-advances/{$id}/settlements", [
            'amount' => 500,
            'date' => '2026-09-25',
        ])->assertOk()
            ->assertJsonPath('data.status', EmployeeAdvance::STATUS_SETTLED)
            ->assertJsonPath('data.remainingAmount', 0);

        $this->assertSame(0.0, EmployeeAdvance::findOrFail($id)->remainingAmount());
        $this->assertSoftDeleted('expenses', ['id' => $expenseId]);
    }

    public function test_a_repayment_cannot_exceed_the_remaining_balance(): void
    {
        $this->actingAsAdmin();
        $this->baseCurrency();
        $employee = Employee::factory()->create();

        $id = $this->recordAdvance($employee, 500, '2026-09-10');

        $this->postJson("/api/employee-advances/{$id}/settlements", [
            'amount' => 600,
            'date' => '2026-09-20',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertSame(500.0, EmployeeAdvance::findOrFail($id)->remainingAmount());
    }

    public function test_an_advance_cannot_be_edited_below_what_was_paid_back(): void
    {
        $this->actingAsAdmin();
        $this->baseCurrency();
        $employee = Employee::factory()->create();

        $id = $this->recordAdvance($employee, 800, '2026-09-10');
        $this->postJson("/api/employee-advances/{$id}/settlements", [
            'amount' => 300, 'date' => '2026-09-20',
        ])->assertOk();

        $this->patchJson("/api/employee-advances/{$id}", [
            'amount' => 200,
            'date' => '2026-09-10',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertSame(800.0, (float) EmployeeAdvance::findOrFail($id)->amount);
    }

    public function test_a_payslip_can_opt_out_of_advance_recovery(): void
    {
        $this->actingAsAdmin();
        $currency = $this->baseCurrency();
        $employee = Employee::factory()->create(['join_date' => '2026-01-01']);
        $this->markMonthPresent($employee, '2026-09');

        $advanceId = $this->recordAdvance($employee, 300, '2026-09-10');
        $salaryId = $this->createSalary($employee, $currency);

        $this->assertSame(300.0, (float) Salary::findOrFail($salaryId)->advance_recovery);

        $this->patchJson("/api/salaries/{$salaryId}/advance-recovery", ['applied' => false])
            ->assertOk()
            ->assertJsonPath('data.advanceRecovery', 0)
            ->assertJsonPath('data.amountDue', 1000);

        $advance = EmployeeAdvance::findOrFail($advanceId);
        $this->assertSame(0.0, (float) $advance->settled_amount);
        $this->assertNull($advance->salary_id);
        $this->assertSame(EmployeeAdvance::STATUS_OUTSTANDING, $advance->status);

        $this->patchJson("/api/salaries/{$salaryId}/advance-recovery", ['applied' => true])
            ->assertOk()
            ->assertJsonPath('data.advanceRecovery', 300)
            ->assertJsonPath('data.amountDue', 700);
    }

    public function test_advance_recovery_cannot_change_after_the_payslip_paid_out(): void
    {
        $this->actingAsAdmin();
        $currency = $this->baseCurrency();
        $employee = Employee::factory()->create(['join_date' => '2026-01-01']);
        $this->markMonthPresent($employee, '2026-09');

        $this->recordAdvance($employee, 300, '2026-09-10');
        $salaryId = $this->createSalary($employee, $currency);
        $this->postJson("/api/salaries/{$salaryId}/payments", [
            'amount' => 700,
            'date' => '2026-09-30',
        ])->assertOk();

        $this->patchJson("/api/salaries/{$salaryId}/advance-recovery", ['applied' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('applied');
    }

    public function test_an_advance_withheld_by_a_paid_payslip_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $currency = $this->baseCurrency();
        $employee = Employee::factory()->create(['join_date' => '2026-01-01']);
        $this->markMonthPresent($employee, '2026-09');

        $advanceId = $this->recordAdvance($employee, 300, '2026-09-10');
        $salaryId = $this->createSalary($employee, $currency);
        $this->postJson("/api/salaries/{$salaryId}/payments", [
            'amount' => 700,
            'date' => '2026-09-30',
        ])->assertOk();

        $this->deleteJson("/api/employee-advances/{$advanceId}")
            ->assertUnprocessable()->assertJsonValidationErrors('advance');

        $this->assertNotSoftDeleted('employee_advances', ['id' => $advanceId]);
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
