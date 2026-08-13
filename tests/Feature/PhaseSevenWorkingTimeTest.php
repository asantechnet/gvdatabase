<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\DailyAnalytics;
use App\Models\Employee;
use App\Models\MonthlyAttendanceSummary;
use App\Models\PaidTimeOff;
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
            'paid_off_days_per_year' => 20, 'website_title' => 'Test', 'website_address' => 'https://example.test',
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

    public function test_monthly_command_is_idempotent_handles_paid_leave_and_refreshes_employee_total(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00', 'UTC'));
        $employee = Employee::factory()->create(['join_date' => '2026-07-30']);
        PaidTimeOff::create(['employee_id' => $employee->id, 'date' => '2026-07-31', 'year' => 2026, 'reason' => 'Approved']);
        Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-07-30', 'status' => 'present', 'logged_at' => '22:00:00',
            'check_in_time' => '22:00', 'check_out_time' => '06:00',
            'checked_in_at' => Carbon::parse('2026-07-30 17:30:00', 'UTC'),
            'checked_out_at' => Carbon::parse('2026-07-31 01:30:00', 'UTC'), 'break_minutes' => 30,
        ]);

        $this->artisan('attendance:finalize --from=2026-07-30 --to=2026-07-31')->assertSuccessful();
        $this->artisan('attendance:finalize --from=2026-07-30 --to=2026-07-31')->assertSuccessful();

        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseHas('attendances', ['employee_id' => $employee->id, 'date' => '2026-07-31', 'status' => 'paid-off-day']);
        $summary = MonthlyAttendanceSummary::where('employee_id', $employee->id)->where('year', 2026)->where('month', 7)->firstOrFail();
        $this->assertSame(450, $summary->net_worked_minutes);
        $this->assertSame(1, $summary->paid_leave_days);
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
}
