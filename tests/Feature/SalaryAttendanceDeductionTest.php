<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Employee;
use App\Models\Salary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalaryAttendanceDeductionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    private function employee(): Employee
    {
        return Employee::factory()->create(['join_date' => '2026-01-01']);
    }

    private function baseCurrency(): Currency
    {
        return Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1, 'decimal_places' => 2]);
    }

    private function createSalary(Employee $employee, Currency $currency, array $overrides = [])
    {
        return $this->postJson('/api/salaries', array_merge([
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => '2026-08',
            'amountPaid' => 2500,
            'workedDays' => 20,
            'totalDays' => 25,
            'remarks' => null,
            'date' => '2026-08-31',
            'deductions' => [],
        ], $overrides));
    }

    public function test_worked_days_drive_an_automatic_absence_deduction(): void
    {
        $this->actingAsAdmin();
        $employee = $this->employee();

        $response = $this->createSalary($employee, $this->baseCurrency())->assertCreated();

        // 2500 * (25 - 20) / 25 = 500 prorated absence deduction.
        $response
            ->assertJsonPath('data.totalAmount', 2500)
            ->assertJsonPath('data.totalDeductions', 500)
            ->assertJsonPath('data.netAmount', 2000)
            ->assertJsonPath('data.amountDue', 2000)
            ->assertJsonPath('data.deductions.0.isAutomatic', true)
            ->assertJsonPath('data.deductions.0.amount', 500)
            ->assertJsonPath('data.deductions.0.code', 'attendance_proration');

        $this->assertDatabaseHas('salary_deductions', [
            'salary_id' => $response->json('data.id'),
            'is_automatic' => true,
            'code' => 'attendance_proration',
            'amount' => 500,
        ]);
    }

    public function test_no_deduction_is_created_when_all_days_are_worked(): void
    {
        $this->actingAsAdmin();
        $employee = $this->employee();

        $response = $this->createSalary($employee, $this->baseCurrency(), [
            'workedDays' => 25,
            'totalDays' => 25,
        ])->assertCreated();

        $response
            ->assertJsonPath('data.totalDeductions', 0)
            ->assertJsonPath('data.netAmount', 2500)
            ->assertJsonPath('data.deductions', []);

        $this->assertDatabaseCount('salary_deductions', 0);
    }

    public function test_editing_worked_days_recomputes_the_automatic_deduction(): void
    {
        $this->actingAsAdmin();
        $employee = $this->employee();

        $salaryId = $this->createSalary($employee, $this->baseCurrency())->assertCreated()->json('data.id');

        // Editing worked days up to the total clears the automatic line.
        $this->putJson("/api/salaries/{$salaryId}", [
            'employee_id' => $employee->id,
            'currency_id' => Salary::findOrFail($salaryId)->currency_id,
            'salaryMonth' => '2026-08',
            'amountPaid' => 2500,
            'workedDays' => 25,
            'totalDays' => 25,
            'remarks' => null,
            'date' => '2026-08-31',
            'deductions' => [],
        ])->assertOk()
            ->assertJsonPath('data.totalDeductions', 0)
            ->assertJsonPath('data.netAmount', 2500);

        $this->assertDatabaseCount('salary_deductions', 0);
    }

    public function test_manual_deductions_coexist_and_the_automatic_row_cannot_be_overridden(): void
    {
        $this->actingAsAdmin();
        $employee = $this->employee();
        $currency = $this->baseCurrency();

        $response = $this->createSalary($employee, $currency, [
            'deductions' => [['description' => 'Equipment', 'amount' => 100]],
        ])->assertCreated();

        // Automatic 500 + manual 100.
        $response
            ->assertJsonPath('data.totalDeductions', 600)
            ->assertJsonPath('data.netAmount', 1900);

        $salaryId = $response->json('data.id');
        $deductions = collect($response->json('data.deductions'));
        $automaticId = $deductions->firstWhere('isAutomatic', true)['id'];
        $manualId = $deductions->firstWhere('isAutomatic', false)['id'];

        // A client that echoes the automatic row back must not be able to
        // rewrite it: it is stripped and recalculated on save.
        $this->putJson("/api/salaries/{$salaryId}", [
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => '2026-08',
            'amountPaid' => 2500,
            'workedDays' => 20,
            'totalDays' => 25,
            'remarks' => null,
            'date' => '2026-08-31',
            'deductions' => [
                ['id' => $automaticId, 'description' => 'tampered', 'amount' => 9999],
                ['id' => $manualId, 'description' => 'Equipment', 'amount' => 100],
            ],
        ])->assertOk()
            ->assertJsonPath('data.totalDeductions', 600)
            ->assertJsonPath('data.netAmount', 1900);

        $this->assertDatabaseHas('salary_deductions', [
            'id' => $automaticId,
            'is_automatic' => true,
            'amount' => 500,
            'description' => 'Unpaid leave / absent (5 days)',
        ]);
    }

    public function test_attendance_breakdown_endpoint_prefills_working_days(): void
    {
        $this->actingAsAdmin();
        $employee = $this->employee();

        $response = $this->getJson('/api/salaries/attendance-breakdown?employee_id='.$employee->id.'&salaryMonth=2026-08')
            ->assertOk()
            ->assertJsonStructure(['data' => ['workedDays', 'totalDays', 'paidLeaveDays', 'unpaidLeaveDays', 'absentDays']]);

        $expected = 0;
        $day = Carbon::create(2026, 8, 1);
        $end = $day->copy()->endOfMonth();
        for ($cursor = $day->copy(); $cursor->lte($end); $cursor->addDay()) {
            if (! $cursor->isFriday()) {
                $expected++;
            }
        }

        $this->assertSame($expected, $response->json('data.totalDays'));
    }
}
