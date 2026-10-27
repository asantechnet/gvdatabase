<?php

namespace Tests\Feature;

use App\Models\Attendance;
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

    /** @return array<int, string> */
    private function workingDates(string $month): array
    {
        $start = Carbon::parse($month.'-01')->startOfDay();
        $dates = [];
        for ($day = $start->copy(); $day->lte($start->copy()->endOfMonth()); $day->addDay()) {
            if (! $day->isFriday()) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    /** @param array<int, string> $absentDates */
    private function markAttendance(Employee $employee, string $month, array $absentDates = []): void
    {
        foreach ($this->workingDates($month) as $date) {
            Attendance::create([
                'employee_id' => $employee->id,
                'date' => $date,
                'status' => in_array($date, $absentDates, true) ? 'absent' : 'present',
                'logged_at' => '08:00:00',
            ]);
        }
    }

    private function createSalary(Employee $employee, Currency $currency, array $overrides = [])
    {
        return $this->postJson('/api/salaries', array_merge([
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => '2026-08',
            'amountPaid' => 2500,
            'remarks' => null,
            'date' => '2026-08-31',
            'deductions' => [],
        ], $overrides));
    }

    public function test_attendance_absence_drives_the_automatic_absence_deduction(): void
    {
        $this->actingAsAdmin();
        $employee = $this->employee();
        $total = count($this->workingDates('2026-08'));
        $absent = array_slice($this->workingDates('2026-08'), -5);
        $this->markAttendance($employee, '2026-08', $absent);
        $deduction = round(2500 * 5 / $total, 2);

        $response = $this->createSalary($employee, $this->baseCurrency())->assertCreated();

        $response
            ->assertJsonPath('data.totalAmount', 2500)
            ->assertJsonPath('data.totalDays', $total)
            ->assertJsonPath('data.workedDays', $total - 5)
            ->assertJsonPath('data.absentDays', 5)
            ->assertJsonPath('data.totalDeductions', $deduction)
            ->assertJsonPath('data.netAmount', 2500 - $deduction)
            ->assertJsonPath('data.amountDue', 2500 - $deduction)
            ->assertJsonPath('data.deductions.0.isAutomatic', true)
            ->assertJsonPath('data.deductions.0.amount', $deduction)
            ->assertJsonPath('data.deductions.0.code', 'attendance_proration');

        $this->assertDatabaseHas('salary_deductions', [
            'salary_id' => $response->json('data.id'),
            'is_automatic' => true,
            'code' => 'attendance_proration',
            'amount' => $deduction,
        ]);
    }

    public function test_no_deduction_is_created_when_every_working_day_is_present(): void
    {
        $this->actingAsAdmin();
        $employee = $this->employee();
        $this->markAttendance($employee, '2026-08');

        $response = $this->createSalary($employee, $this->baseCurrency())->assertCreated();

        $response
            ->assertJsonPath('data.totalDeductions', 0)
            ->assertJsonPath('data.netAmount', 2500)
            ->assertJsonPath('data.deductions', []);

        $this->assertDatabaseCount('salary_deductions', 0);
    }

    public function test_worked_and_total_day_overrides_are_ignored(): void
    {
        $this->actingAsAdmin();
        $employee = $this->employee();
        $total = count($this->workingDates('2026-08'));
        $this->markAttendance($employee, '2026-08');

        $salaryId = $this->createSalary($employee, $this->baseCurrency())->assertCreated()->json('data.id');

        // Worked/total days are read-only attendance snapshots. A stale client
        // that still sends them must not be able to manufacture an absence.
        $this->putJson("/api/salaries/{$salaryId}", [
            'employee_id' => $employee->id,
            'currency_id' => Salary::findOrFail($salaryId)->currency_id,
            'salaryMonth' => '2026-08',
            'amountPaid' => 2500,
            'workedDays' => 1,
            'totalDays' => 99,
            'remarks' => null,
            'date' => '2026-08-31',
            'deductions' => [],
        ])->assertOk()
            ->assertJsonPath('data.totalDays', $total)
            ->assertJsonPath('data.workedDays', $total)
            ->assertJsonPath('data.absentDays', 0)
            ->assertJsonPath('data.totalDeductions', 0)
            ->assertJsonPath('data.netAmount', 2500);

        $this->assertDatabaseCount('salary_deductions', 0);
    }

    public function test_manual_deductions_coexist_and_the_automatic_row_cannot_be_overridden(): void
    {
        $this->actingAsAdmin();
        $employee = $this->employee();
        $currency = $this->baseCurrency();
        $total = count($this->workingDates('2026-08'));
        $absent = array_slice($this->workingDates('2026-08'), -5);
        $this->markAttendance($employee, '2026-08', $absent);
        $automaticAmount = round(2500 * 5 / $total, 2);

        $response = $this->createSalary($employee, $currency, [
            'deductions' => [['description' => 'Equipment', 'amount' => 100]],
        ])->assertCreated();

        // Automatic absence + manual equipment.
        $response
            ->assertJsonPath('data.totalDeductions', $automaticAmount + 100)
            ->assertJsonPath('data.netAmount', 2500 - $automaticAmount - 100);

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
            'remarks' => null,
            'date' => '2026-08-31',
            'deductions' => [
                ['id' => $automaticId, 'description' => 'tampered', 'amount' => 9999],
                ['id' => $manualId, 'description' => 'Equipment', 'amount' => 100],
            ],
        ])->assertOk()
            ->assertJsonPath('data.totalDeductions', $automaticAmount + 100)
            ->assertJsonPath('data.netAmount', 2500 - $automaticAmount - 100);

        $this->assertDatabaseHas('salary_deductions', [
            'id' => $automaticId,
            'is_automatic' => true,
            'amount' => $automaticAmount,
            'description' => 'Absent (5 days)',
        ]);
    }

    public function test_attendance_breakdown_endpoint_reports_the_attendance_snapshot(): void
    {
        $this->actingAsAdmin();
        $employee = $this->employee();
        $total = count($this->workingDates('2026-08'));

        $response = $this->getJson('/api/salaries/attendance-breakdown?employee_id='.$employee->id.'&salaryMonth=2026-08')
            ->assertOk()
            ->assertJsonStructure(['data' => ['workedDays', 'totalDays', 'offDays', 'absentDays']]);

        $this->assertSame($total, $response->json('data.totalDays'));
        $this->assertSame(0, $response->json('data.workedDays'));
        $this->assertSame(0, $response->json('data.offDays'));
        $this->assertSame($total, $response->json('data.absentDays'));
    }
}
