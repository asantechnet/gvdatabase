<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\Office;
use App\Models\OffDay;
use App\Models\Position;
use App\Models\Salary;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrPayrollOffDaysTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    private function baseCurrency(): Currency
    {
        return Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1, 'decimal_places' => 2]);
    }

    /** @param array<string, mixed> $overrides */
    private function employee(array $overrides = []): Employee
    {
        return Employee::factory()->create(array_merge(['join_date' => '2026-01-01'], $overrides));
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

    public function test_an_employee_stores_a_guarantor_name(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/employees', [
            'name' => 'Karim Rahimi',
            'father_name' => 'Abdul Rahimi',
            'contact' => '0700000000',
            'id_number' => 'EMP-1001',
            'education_lvl' => 'Bachelor',
            'position_id' => Position::factory()->create()->id,
            'salary' => 30000,
            'addr' => 'Street 4, Kabul',
            'join_date' => '2026-01-01',
            'guarantor_name' => 'Ahmad Khan',
            'create_user' => false,
            'deductions' => [],
        ])->assertCreated();

        $this->assertDatabaseHas('employees', [
            'id_number' => 'EMP-1001',
            'guarantor_name' => 'Ahmad Khan',
        ]);
    }

    public function test_an_approved_off_day_marks_attendance_off_and_payroll_off_days(): void
    {
        $this->actingAsSuperAdmin();
        $this->baseCurrency();
        $employee = $this->employee();

        $response = $this->postJson('/api/off-days', [
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'reason' => 'National holiday',
        ])->assertCreated();

        // A holder of `approve_off_days` announces an already-approved holiday.
        $response->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'date' => '2026-09-07',
            'status' => 'off_day',
        ]);
        $this->assertNotNull(
            Attendance::where('employee_id', $employee->id)->whereDate('date', '2026-09-07')->value('off_day_id'),
        );

        // Off days count as worked for pay but are reported separately.
        $this->getJson('/api/salaries/attendance-breakdown?employee_id='.$employee->id.'&salaryMonth=2026-09')
            ->assertOk()
            ->assertJsonPath('data.offDays', 2);
    }

    public function test_removing_an_off_day_releases_the_attendance_rows(): void
    {
        $this->actingAsSuperAdmin();
        $employee = $this->employee();

        $id = $this->postJson('/api/off-days', [
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
            'reason' => 'National holiday',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('attendances', ['employee_id' => $employee->id, 'date' => '2026-09-07']);

        $this->deleteJson("/api/off-days/{$id}")->assertOk();

        $this->assertSoftDeleted('off_days', ['id' => $id]);
        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $employee->id,
            'date' => '2026-09-07',
            'off_day_id' => $id,
        ]);
    }

    public function test_payroll_recovers_outstanding_advances_and_releases_them_on_delete(): void
    {
        $this->actingAsSuperAdmin();
        $currency = $this->baseCurrency();
        $employee = $this->employee();
        $this->markMonthPresent($employee, '2026-09');

        $advanceId = $this->postJson('/api/employee-advances', [
            'employee_id' => $employee->id,
            'amount' => 300,
            'date' => '2026-09-10',
        ])->assertOk()->json('data.id');

        $salaryId = $this->postJson('/api/salaries', [
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => '2026-09',
            'amountPaid' => 1000,
            'workedDays' => 25,
            'totalDays' => 25,
            'date' => '2026-09-30',
            'deductions' => [],
        ])->assertCreated()
            ->assertJsonPath('data.advanceRecovery', 300)
            ->assertJsonPath('data.amountDue', 700)
            ->json('data.id');

        $this->assertDatabaseHas('salary_deductions', [
            'salary_id' => $salaryId,
            'code' => 'advance_recovery',
            'amount' => 300,
            'is_automatic' => true,
        ]);
        $this->assertSame($salaryId, EmployeeAdvance::findOrFail($advanceId)->salary_id);

        $this->deleteJson("/api/salaries/{$salaryId}")->assertOk();

        $advance = EmployeeAdvance::findOrFail($advanceId);
        $this->assertNull($advance->salary_id);
        $this->assertNull($advance->recovered_at);
    }

    public function test_a_recorded_salary_amount_cannot_be_changed(): void
    {
        $this->actingAsSuperAdmin();
        $currency = $this->baseCurrency();
        $employee = $this->employee();

        $salaryId = $this->postJson('/api/salaries', [
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => '2026-09',
            'amountPaid' => 1000,
            'workedDays' => 25,
            'totalDays' => 25,
            'date' => '2026-09-30',
            'deductions' => [],
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/salaries/{$salaryId}", [
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => '2026-09',
            'amountPaid' => 1500,
            'workedDays' => 25,
            'totalDays' => 25,
            'date' => '2026-09-30',
            'deductions' => [],
        ])->assertStatus(422);

        $this->assertSame(1000.0, (float) Salary::findOrFail($salaryId)->amount_paid);
    }

    public function test_off_day_allowance_is_resolved_per_employee(): void
    {
        $this->actingAsSuperAdmin();
        Setting::create([
            'navigation_clr' => '#ffffff', 'cards_clr' => '#ffffff', 'footer_clr' => '#ffffff',
            'off_day_allowance_days_per_year' => 20, 'website_title' => 'Test',
            'website_address' => 'https://example.test', 'phone' => '123',
            'physical_address' => 'Test address', 'short_company_name' => 'Test',
            'original_company_name' => 'Test Company',
        ]);
        $employee = $this->employee(['off_day_allowance_days' => null]);

        // The company-wide value is only a creation-time catalogue default;
        // it is never a runtime fallback for an existing employee.
        $this->getJson("/api/employees/{$employee->id}/leave-requests?year=2026")
            ->assertOk()
            ->assertJsonPath('allowance', 0);

        $employee->forceFill(['off_day_allowance_days' => 12])->save();

        $this->getJson("/api/employees/{$employee->id}/leave-requests?year=2026")
            ->assertOk()
            ->assertJsonPath('allowance', 12);
    }

    public function test_the_audit_heartbeat_delivers_a_batch_once(): void
    {
        $this->actingAsSuperAdmin();
        // A second reviewer receives the real-time flag and drains it.
        $reviewer = User::factory()->create(['roles' => ['super_admin']]);
        $this->assertFalse((bool) $reviewer->unread_audit_logs);

        OffDay::create([
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
            'reason' => 'National holiday',
            'status' => OffDay::STATUS_APPROVED,
        ]);

        $this->assertTrue((bool) $reviewer->fresh()->unread_audit_logs);
        $this->assertGreaterThan(0, AuditLog::count());

        Sanctum::actingAs($reviewer, ['access']);

        $first = $this->getJson('/api/audit-logs/heartbeat')->assertOk();
        $this->assertNotEmpty($first->json('data'));
        $this->assertFalse((bool) $reviewer->fresh()->unread_audit_logs);

        // The flag was drained, so the same batch is not delivered twice.
        $this->getJson('/api/audit-logs/heartbeat')->assertOk()->assertJsonCount(0, 'data');
    }
}
