<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OffDay;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OffDayOverlapTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($admin, ['access']);

        return $admin;
    }

    /** @param array<string, mixed> $overrides */
    private function employee(array $overrides = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'join_date' => '2026-01-01',
        ], $overrides));
    }

    public function test_an_overlapping_range_is_rejected_and_materializes_nothing(): void
    {
        $this->admin();
        $employee = $this->employee();

        $this->postJson('/api/off-days', [
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'reason' => 'National holiday',
        ])->assertCreated();

        $this->assertDatabaseCount('attendances', 2);

        $this->postJson('/api/off-days', [
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-10',
            'reason' => 'Overlapping holiday',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'business.off_day_overlaps_existing')
            ->assertJsonStructure(['errors' => ['start_date']])
            ->assertJsonPath('conflict.start_date', '2026-09-07')
            ->assertJsonPath('conflict.end_date', '2026-09-08')
            ->assertJsonPath('conflict.status', 'approved');

        $this->assertDatabaseCount('off_days', 1);
        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $employee->id,
            'date' => '2026-09-09',
        ]);
    }

    public function test_a_pending_range_also_blocks_a_new_range(): void
    {
        $this->admin();
        $this->employee();

        OffDay::create([
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
            'reason' => 'Awaiting approval',
            'status' => OffDay::STATUS_PENDING,
        ]);

        $this->postJson('/api/off-days', [
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-16',
            'reason' => 'Overlapping holiday',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'business.off_day_overlaps_existing')
            ->assertJsonPath('conflict.status', 'pending');

        $this->assertDatabaseCount('off_days', 1);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_adjacent_ranges_are_allowed_and_a_range_can_be_edited_without_conflicting_with_itself(): void
    {
        $this->admin();
        $employee = $this->employee();

        $firstId = $this->postJson('/api/off-days', [
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'reason' => 'First holiday',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/off-days', [
            'start_date' => '2026-09-09',
            'end_date' => '2026-09-10',
            'reason' => 'Adjacent holiday',
        ])->assertCreated();

        $this->putJson("/api/off-days/{$firstId}", [
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'reason' => 'Same range',
        ])->assertOk();

        $this->putJson("/api/off-days/{$firstId}", [
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-12',
            'reason' => 'Moved range',
        ])->assertOk();

        $this->assertDatabaseHas('off_days', [
            'id' => $firstId,
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-12',
        ]);

        foreach (['2026-09-07', '2026-09-08'] as $date) {
            $this->assertDatabaseMissing('attendances', [
                'employee_id' => $employee->id,
                'date' => $date,
            ]);
        }

        foreach (['2026-09-09', '2026-09-10', '2026-09-12'] as $date) {
            $this->assertDatabaseHas('attendances', [
                'employee_id' => $employee->id,
                'date' => $date,
                'status' => 'off_day',
            ]);
        }

        // The moved range starts on a Friday, which is never materialized.
        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $employee->id,
            'date' => '2026-09-11',
        ]);
        $this->assertDatabaseCount('attendances', 3);
    }

    public function test_apply_and_revert_preserve_worked_and_leave_rows(): void
    {
        $this->admin();
        $worked = $this->employee();
        $onLeave = $this->employee();
        $plain = $this->employee();

        Attendance::create([
            'employee_id' => $worked->id,
            'date' => '2026-09-07',
            'status' => 'present',
            'logged_at' => '08:00:00',
            'check_in_time' => '08:00:00',
            'check_out_time' => '16:00:00',
        ]);

        $leave = LeaveRequest::create([
            'employee_id' => $onLeave->id,
            'date' => '2026-09-08',
            'year' => 2026,
            'duration_days' => 1,
            'off_day_days' => 0,
            'reason' => 'Approved leave',
            'is_granted' => true,
        ]);

        Attendance::create([
            'employee_id' => $onLeave->id,
            'date' => '2026-09-08',
            'status' => 'absent',
            'logged_at' => '00:00:00',
            'leave_days' => 1,
            'off_day_days' => 0,
            'leave_request_id' => $leave->id,
            'source' => 'leave',
        ]);

        $offDayId = $this->postJson('/api/off-days', [
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'reason' => 'Company holiday',
        ])->assertCreated()->json('data.id');

        $workedRow = Attendance::where('employee_id', $worked->id)->whereDate('date', '2026-09-07')->firstOrFail();
        $this->assertSame('present', $workedRow->status);
        $this->assertNotNull($workedRow->check_in_time);
        $this->assertNull($workedRow->off_day_id);

        $leaveRow = Attendance::where('employee_id', $onLeave->id)->whereDate('date', '2026-09-08')->firstOrFail();
        $this->assertSame('absent', $leaveRow->status);
        $this->assertSame(1.0, (float) $leaveRow->leave_days);
        $this->assertNull($leaveRow->off_day_id);

        $this->assertSame(2, Attendance::where('employee_id', $plain->id)->where('status', 'off_day')->count());

        $this->deleteJson("/api/off-days/{$offDayId}")->assertOk();

        $this->assertSame(0, Attendance::where('employee_id', $plain->id)->count());
        $this->assertSame('present', Attendance::where('employee_id', $worked->id)->whereDate('date', '2026-09-07')->firstOrFail()->status);
        $this->assertSame('absent', Attendance::where('employee_id', $onLeave->id)->whereDate('date', '2026-09-08')->firstOrFail()->status);
    }

    public function test_payroll_never_deducts_off_days_but_prorates_excess_leave(): void
    {
        $this->admin();
        $currency = Currency::factory()->create([
            'is_base' => true,
            'is_active' => true,
            'rate' => 1,
            'decimal_places' => 2,
        ]);
        $employee = $this->employee(['off_day_allowance_days' => 0]);

        $start = Carbon::parse('2026-09-01')->startOfDay();
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

        $leaveId = $this->postJson("/api/employees/{$employee->id}/leave-requests", [
            'date' => '2026-09-08',
            'duration_days' => 1,
            'reason' => 'Uncovered personal leave',
        ])->assertCreated()->json('data.id');

        $this->patchJson('/api/leave-requests/grant', ['ids' => [$leaveId]])->assertOk();

        $this->postJson('/api/off-days', [
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
            'reason' => 'Company holiday',
        ])->assertCreated();

        $this->postJson('/api/salaries', [
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'salaryMonth' => '2026-09',
            'amountPaid' => 2600,
            'date' => '2026-09-30',
            'deductions' => [],
        ])->assertCreated()
            ->assertJsonPath('data.totalDays', 26)
            ->assertJsonPath('data.workedDays', 24)
            ->assertJsonPath('data.absentDays', 1)
            ->assertJsonPath('data.totalDeductions', 100)
            ->assertJsonPath('data.netAmount', 2500);

        $salary = Salary::latest('id')->firstOrFail();
        $this->assertSame(26, (int) $salary->total_days);
        $this->assertSame(24, (int) $salary->worked_days);
        $this->assertSame(1, (int) $salary->absent_days);
        $this->assertSame(1, (int) $salary->off_days);

        $this->assertDatabaseHas('salary_deductions', [
            'salary_id' => $salary->id,
            'code' => 'attendance_proration',
            'amount' => 100,
        ]);
    }
}
