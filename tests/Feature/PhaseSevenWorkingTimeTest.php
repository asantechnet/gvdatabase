<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\DailyAnalytics;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MonthlyAttendanceSummary;
use App\Models\Setting;
use App\Services\AttendanceCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PhaseSevenWorkingTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create([
            'navigation_clr' => '#111111', 'cards_clr' => '#ffffff', 'footer_clr' => '#111111',
            'off_day_allowance_days_per_year' => 20, 'automatic_attendance_enabled' => true,
            'website_title' => 'Test', 'website_address' => 'https://example.test',
            'timezone' => 'Asia/Kabul', 'phone' => '123', 'physical_address' => 'Kabul',
            'short_company_name' => 'Test', 'original_company_name' => 'Test Company',
            'office_open_time' => '22:00', 'office_close_time' => '06:00', 'default_break_minutes' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_calculator_handles_overnight_shifts_and_skips_unchanged_inputs(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2026-07-01']);
        $attendance = Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-07-10', 'status' => 'present', 'logged_at' => '22:00:00',
            'checked_in_at' => Carbon::parse('2026-07-10 17:35:00', 'UTC'),
            'checked_out_at' => Carbon::parse('2026-07-11 01:45:00', 'UTC'),
            'check_in_time' => '22:05', 'check_out_time' => '06:15', 'break_minutes' => 30,
        ]);

        $calculator = app(AttendanceCalculationService::class);
        $this->assertTrue($calculator->calculate($attendance));
        $attendance->refresh();
        $this->assertSame(490, $attendance->gross_minutes);
        $this->assertSame(460, $attendance->net_worked_minutes);
        $this->assertSame(450, $attendance->required_minutes);
        $this->assertSame(5, $attendance->late_arrival_minutes);
        $this->assertSame(0, $attendance->early_departure_minutes);
        $this->assertSame(10, $attendance->overtime_minutes);
        $this->assertTrue($attendance->is_overnight_shift);
        $this->assertFalse($calculator->calculate($attendance));
    }

    public function test_incomplete_punch_preserves_break_and_is_reported_distinctly(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2026-07-01']);
        $attendance = Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-07-11', 'status' => 'present', 'logged_at' => '22:00:00',
            'check_in_time' => '22:00', 'checked_in_at' => Carbon::parse('2026-07-11 17:30:00', 'UTC'),
            'break_minutes' => 45,
        ]);

        app(AttendanceCalculationService::class)->calculate($attendance);
        $attendance->refresh();
        $this->assertSame('check_out', $attendance->missing_punch_state);
        $this->assertSame(45, $attendance->break_minutes);
        $this->assertNull($attendance->net_worked_minutes);
    }

    public function test_half_day_leave_reduces_required_minutes_without_punctuality_penalties(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2026-07-01']);
        $attendance = Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-07-11', 'status' => 'present',
            'leave_days' => 0.5, 'off_day_days' => 0.5, 'logged_at' => '13:00:00',
            'check_in_time' => '13:00', 'check_out_time' => '17:00',
            'checked_in_at' => Carbon::parse('2026-07-11 08:30:00', 'UTC'),
            'checked_out_at' => Carbon::parse('2026-07-11 12:30:00', 'UTC'), 'break_minutes' => 0,
        ]);

        app(AttendanceCalculationService::class)->calculate($attendance, true);
        $attendance->refresh();

        $this->assertSame(240, $attendance->required_minutes);
        $this->assertSame(0, $attendance->late_arrival_minutes);
        $this->assertSame(0, $attendance->early_departure_minutes);
        $this->assertSame(0, $attendance->overtime_minutes);
    }

    public function test_missing_office_hours_do_not_count_all_worked_time_as_overtime(): void
    {
        Setting::query()->update([
            'office_open_time' => null,
            'office_close_time' => null,
        ]);
        $employee = Employee::factory()->create(['join_date' => '2026-07-01']);
        $attendance = Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-07-11', 'status' => 'present', 'logged_at' => '09:00:00',
            'check_in_time' => '09:00', 'check_out_time' => '17:00',
            'checked_in_at' => Carbon::parse('2026-07-11 04:30:00', 'UTC'),
            'checked_out_at' => Carbon::parse('2026-07-11 12:30:00', 'UTC'), 'break_minutes' => 0,
        ]);

        app(AttendanceCalculationService::class)->calculate($attendance);
        $attendance->refresh();

        $this->assertSame(480, $attendance->net_worked_minutes);
        $this->assertNull($attendance->required_minutes);
        $this->assertSame(0, $attendance->overtime_minutes);

        Setting::query()->update([
            'office_open_time' => '09:00',
            'office_close_time' => '17:00',
        ]);

        $this->assertTrue(app(AttendanceCalculationService::class)->calculate($attendance));
        $this->assertSame(480, $attendance->fresh()->required_minutes);
        $this->assertSame(0, $attendance->fresh()->overtime_minutes);
    }

    public function test_monthly_command_is_idempotent_handles_off_days_and_refreshes_employee_total(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00', 'UTC'));
        $employee = Employee::factory()->create(['join_date' => '2026-07-29']);
        LeaveRequest::create([
            'employee_id' => $employee->id, 'date' => '2026-07-30', 'year' => 2026,
            'duration_days' => 1, 'off_day_days' => 1, 'is_granted' => true, 'reason' => 'Approved',
        ]);
        Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-07-29', 'status' => 'present', 'logged_at' => '22:00:00',
            'check_in_time' => '22:00', 'check_out_time' => '06:00',
            'checked_in_at' => Carbon::parse('2026-07-29 17:30:00', 'UTC'),
            'checked_out_at' => Carbon::parse('2026-07-30 01:30:00', 'UTC'), 'break_minutes' => 30,
        ]);

        $this->artisan('attendance:finalize --from=2026-07-29 --to=2026-07-30')->assertSuccessful();
        $this->artisan('attendance:finalize --from=2026-07-29 --to=2026-07-30')->assertSuccessful();

        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseHas('attendances', ['employee_id' => $employee->id, 'date' => '2026-07-30', 'status' => 'off_day']);
        $summary = MonthlyAttendanceSummary::where('employee_id', $employee->id)->where('year', 2026)->where('month', 7)->firstOrFail();
        $this->assertSame(450, $summary->net_worked_minutes);
        $this->assertSame(1.0, (float) $summary->off_days);
        $this->assertSame(7.5, (float) $employee->fresh()->total_worked_hours);
    }

    public function test_analytics_sync_upserts_only_dashboard_metrics(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2026-07-01']);
        Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-07-10', 'status' => 'present', 'logged_at' => '09:00:00',
            'net_worked_minutes' => 420, 'overtime_minutes' => 30,
        ]);

        $this->artisan('analytics:sync --from=2026-07-10 --to=2026-07-10')->assertSuccessful();
        $this->artisan('analytics:sync --from=2026-07-10 --to=2026-07-10')->assertSuccessful();

        $this->assertSame(1, DailyAnalytics::count());
        $row = DailyAnalytics::firstOrFail();
        $this->assertSame(1, $row->attendance_present);
        $this->assertSame(420, $row->worked_minutes);
        $this->assertSame(30, $row->overtime_minutes);
    }

    public function test_identical_punches_are_zero_hours_and_reversed_instants_are_invalid(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2026-07-01']);
        $row = Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-07-11', 'status' => 'present', 'logged_at' => '22:00',
            'checked_in_at' => Carbon::parse('2026-07-11 17:30', 'UTC'),
            'checked_out_at' => Carbon::parse('2026-07-11 17:30', 'UTC'),
        ]);
        $calculator = app(AttendanceCalculationService::class);
        $calculator->calculate($row);
        $this->assertSame(0, $row->net_worked_minutes);
        $this->assertSame(0, $row->gross_minutes);
        $row->update(['checked_out_at' => Carbon::parse('2026-07-11 17:00', 'UTC')]);
        $calculator->calculate($row);
        $this->assertSame('invalid', $row->missing_punch_state);
        $this->assertNull($row->net_worked_minutes);
    }

    public function test_short_workday_preserves_default_break_and_schedule_requirement(): void
    {
        $employee = Employee::factory()->create(['join_date' => '2026-07-01']);
        $row = Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-07-11', 'status' => 'present', 'logged_at' => '22:00',
            'checked_in_at' => Carbon::parse('2026-07-11 17:30', 'UTC'),
            'checked_out_at' => Carbon::parse('2026-07-11 17:40', 'UTC'),
        ]);
        $calculator = app(AttendanceCalculationService::class);
        $calculator->calculate($row);
        $this->assertNull($row->break_minutes);
        $this->assertSame(0, $row->net_worked_minutes);
        $this->assertSame(450, $row->required_minutes);
        $this->assertFalse($calculator->calculate($row));
        $row->update(['checked_out_at' => Carbon::parse('2026-07-12 01:30', 'UTC')]);
        $calculator->calculate($row);
        $this->assertSame(450, $row->net_worked_minutes);
        Setting::query()->update(['default_break_minutes' => 45]);
        $this->assertTrue($calculator->calculate($row));
        $this->assertSame(435, $row->net_worked_minutes);
    }

    public function test_finalization_never_marks_today_future_days_or_inactive_employees_absent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30', 'UTC'));
        $active = Employee::factory()->create(['join_date' => '2026-08-12']);
        $inactive = Employee::factory()->create(['join_date' => '2026-08-12', 'status' => 'inactive']);
        $this->artisan('attendance:finalize --from=2026-08-12 --to=2026-08-15')->assertSuccessful();
        $this->assertDatabaseHas('attendances', ['employee_id' => $active->id, 'date' => '2026-08-12', 'status' => 'absent']);
        $this->assertDatabaseMissing('attendances', ['employee_id' => $inactive->id]);
        $this->assertDatabaseCount('attendances', 1);
    }
}
