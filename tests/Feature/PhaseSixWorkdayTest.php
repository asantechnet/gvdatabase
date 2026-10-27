<?php

namespace Tests\Feature;

use App\Http\Middleware\AttendanceBrowser;
use App\Models\Attendance;
use App\Models\DailyReport;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseSixWorkdayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials()->withUnencryptedCookie(AttendanceBrowser::COOKIE,
            Crypt::encryptString((string) Str::uuid()));
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        Setting::create([
            'navigation_clr' => '#111111',
            'cards_clr' => '#ffffff',
            'footer_clr' => '#111111',
            'off_day_allowance_days_per_year' => 0,
            'automatic_attendance_enabled' => true,
            'website_title' => 'Test',
            'website_address' => 'https://example.test',
            'company_email' => 'office@example.test',
            'timezone' => 'Asia/Kabul',
            'phone' => '123',
            'physical_address' => 'Kabul',
            'short_company_name' => 'Test',
            'original_company_name' => 'Test Company',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_browser_cannot_record_attendance_for_a_second_account_on_the_same_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));
        $browser = Crypt::encryptString((string) Str::uuid());
        $this->withCredentials()->withUnencryptedCookie(AttendanceBrowser::COOKIE, $browser);
        $first = User::factory()->create(['roles' => ['staff']]);
        $second = User::factory()->create(['roles' => ['staff']]);
        $firstEmployee = Employee::factory()->create(['user_id' => $first->id, 'join_date' => '2026-01-01']);
        $secondEmployee = Employee::factory()->create(['user_id' => $second->id, 'join_date' => '2026-01-01']);

        Sanctum::actingAs($first, ['access']);
        $this->postJson('/api/workday/start')->assertOk();
        $this->postJson('/api/workday/start')->assertOk();
        Sanctum::actingAs($second, ['access']);
        $this->postJson('/api/workday/start')->assertForbidden()
            ->assertJsonPath('code', 'business.attendance_browser_account_mismatch');
        $this->assertDatabaseMissing('attendances', ['employee_id' => $secondEmployee->id]);
        $this->assertDatabaseHas('attendances', ['employee_id' => $firstEmployee->id]);
        $this->assertDatabaseCount('attendance_browser_days', 1);

        // A different employee may use their own browser without a shared-IP restriction.
        $this->withCredentials()->withUnencryptedCookie(AttendanceBrowser::COOKIE,
            Crypt::encryptString((string) Str::uuid()));
        $this->postJson('/api/workday/start')->assertOk();
    }

    public function test_employee_starts_and_ends_a_timezone_aware_workday_with_one_report(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));

        $this->getJson('/api/workday/status')
            ->assertOk()
            ->assertJsonPath('data.state', 'not_started')
            ->assertJsonPath('data.timezone', 'Asia/Kabul');

        $started = $this->postJson('/api/workday/start')
            ->assertOk()
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.attendance.date', '2026-08-13')
            ->assertJsonPath('data.attendance.checkInTime', '08:00')
            ->assertJsonPath('data.attendance.checkedInAt', '2026-08-13T03:30:00.000000Z');

        $attendanceId = $started->json('data.attendance.id');
        $this->postJson('/api/workday/end', ['summary' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.summary.0', 'validation.required');

        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00', 'UTC'));
        $ended = $this->postJson('/api/workday/end', [
            'summary' => 'Finished the applicant onboarding workflow.',
            'achievements' => 'Released the final review.',
            'challenges' => 'Waiting for applicant data.',
            'nextDayPlan' => 'Monitor adoption.',
            'supportNeeded' => 'None',
        ])->assertOk()
            ->assertJsonPath('data.state', 'completed')
            ->assertJsonPath('data.attendance.checkOutTime', '16:30')
            ->assertJsonPath('data.attendance.checkedOutAt', '2026-08-13T12:00:00.000000Z')
            ->assertJsonPath('data.attendance.workedMinutes', 510)
            ->assertJsonPath('data.report.attendanceId', $attendanceId)
            ->assertJsonPath('data.report.reportDate', '2026-08-13');

        $this->assertDatabaseHas('daily_reports', [
            'user_id' => $user->id,
            'attendance_id' => $attendanceId,
            'report_date' => '2026-08-13',
            'summary' => 'Finished the applicant onboarding workflow.',
        ]);
        $this->assertSame($user->id, DailyReport::firstOrFail()->user->id);
        $this->assertSame($employee->id, DailyReport::firstOrFail()->attendance->employee_id);

        $this->postJson('/api/workday/end', ['summary' => 'Duplicate'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'business.workday_not_started');
        $this->assertSame(1, DailyReport::count());
    }

    public function test_disabled_automatic_attendance_blocks_self_service_workday_actions(): void
    {
        Setting::query()->latest('id')->firstOrFail()->update(['automatic_attendance_enabled' => false]);
        $user = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);

        $this->getJson('/api/workday/status')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.state', 'disabled');
        $this->postJson('/api/workday/start')->assertStatus(422);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_non_present_attendance_blocks_self_service_start(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));
        Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-08-13',
            'status' => 'absent',
            'logged_at' => '08:00:00',
        ]);
        Sanctum::actingAs($user, ['access']);

        $this->getJson('/api/workday/status')->assertOk()->assertJsonPath('data.state', 'blocked');
        $this->postJson('/api/workday/start')
            ->assertStatus(422)
            ->assertJsonPath('errors.workday.0', 'business.workday_attendance_conflict');
    }

    public function test_account_without_employee_profile_does_not_receive_start_prompt(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        Sanctum::actingAs($user, ['access']);

        $this->getJson('/api/workday/status')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.state', 'unavailable');
    }

    public function test_missing_browser_cookie_cannot_create_a_fresh_identity_on_each_start(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));
        $user = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);
        $this->withUnencryptedCookie(AttendanceBrowser::COOKIE, 'invalid');
        $this->postJson('/api/workday/start')->assertUnprocessable()
            ->assertJsonPath('code', 'business.attendance_browser_required');
        $this->getJson('/api/workday/status')->assertOk()->assertCookie(AttendanceBrowser::COOKIE);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_overnight_thursday_workday_can_end_on_friday_without_creating_a_new_day(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-13 17:30:00', 'UTC'));
        $this->postJson('/api/workday/start')->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-08-14 01:30:00', 'UTC'));
        $this->getJson('/api/workday/status')->assertOk()->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.attendance.date', '2026-08-13');
        $this->postJson('/api/workday/end', ['summary' => 'Overnight work'])->assertOk()
            ->assertJsonPath('data.attendance.workedMinutes', 480)
            ->assertJsonPath('data.report.reportDate', '2026-08-13');
        $this->assertDatabaseCount('attendances', 1);
        $this->assertTrue(Attendance::firstOrFail()->is_overnight_shift);
    }

    public function test_disabling_automatic_attendance_does_not_strand_an_open_workday(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));
        $this->postJson('/api/workday/start')->assertOk();
        Setting::query()->update(['automatic_attendance_enabled' => false]);
        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00', 'UTC'));
        $this->getJson('/api/workday/status')->assertOk()->assertJsonPath('data.state', 'active');
        $this->postJson('/api/workday/end', ['summary' => 'Work completed'])->assertOk();
    }

    public function test_old_missing_report_remains_accessible_and_its_checkout_is_preserved(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        $row = Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-08-12', 'status' => 'present', 'logged_at' => '08:00',
            'checked_in_at' => Carbon::parse('2026-08-12 03:30:00', 'UTC'),
            'checked_out_at' => Carbon::parse('2026-08-12 12:00:00', 'UTC'),
            'check_in_time' => '08:00', 'check_out_time' => '16:30',
        ]);
        Sanctum::actingAs($user, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-15 03:30:00', 'UTC'));
        $this->getJson('/api/workday/status')->assertOk()->assertJsonPath('data.state', 'awaiting_report')
            ->assertJsonPath('data.attendance.id', $row->id);
        $this->postJson('/api/workday/end', ['summary' => 'Late report', 'attendanceId' => $row->id])->assertOk();
        $this->assertSame('2026-08-12T12:00:00.000000Z', $row->fresh()->checked_out_at->toISOString());
    }

    public function test_forgotten_checkout_cannot_invent_multiple_days_of_work(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-12 03:30:00', 'UTC'));
        $this->postJson('/api/workday/start')->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-08-13 04:00:00', 'UTC'));
        $this->postJson('/api/workday/start')->assertConflict()
            ->assertJsonPath('code', 'business.workday_already_started');
        $this->postJson('/api/workday/end', ['summary' => 'Forgot to end yesterday'])->assertConflict()
            ->assertJsonPath('code', 'business.workday_checkout_needs_correction');
        $this->assertNull(Attendance::firstOrFail()->checked_out_at);
        $this->assertNull(Attendance::firstOrFail()->net_worked_minutes);
        $this->assertDatabaseCount('daily_reports', 0);
    }

    public function test_inactive_and_not_yet_joined_employees_cannot_start(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'status' => 'inactive', 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));
        $this->postJson('/api/workday/start')->assertUnprocessable()
            ->assertJsonPath('code', 'business.attendance_employee_inactive');
        $employee->update(['status' => 'active', 'join_date' => '2026-08-20']);
        $user->unsetRelation('employee');
        $this->postJson('/api/workday/start')->assertUnprocessable()
            ->assertJsonPath('code', 'business.attendance_before_join_date');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_half_day_leave_can_start_and_stale_report_session_cannot_submit(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-13 08:30:00', 'UTC'));
        $row = Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-08-13', 'status' => 'off_day',
            'leave_days' => 0.5, 'off_day_days' => 0.5, 'logged_at' => '00:00',
        ]);
        $this->postJson('/api/workday/start')->assertOk()->assertJsonPath('data.state', 'active');
        $this->assertSame(0.5, $row->fresh()->leave_days);
        $this->assertSame(0.5, $row->fresh()->off_day_days);
        $this->postJson('/api/workday/end', ['summary' => 'Wrong session', 'attendanceId' => $row->id + 1])
            ->assertConflict()->assertJsonPath('code', 'business.workday_session_changed');
        $this->assertDatabaseCount('daily_reports', 0);
    }

    public function test_midnight_does_not_release_a_browser_with_another_employees_open_session(): void
    {
        $first = User::factory()->create(['roles' => ['staff']]);
        $second = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $first->id, 'join_date' => '2026-01-01']);
        $secondEmployee = Employee::factory()->create(['user_id' => $second->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($first, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-12 17:30', 'UTC'));
        $this->postJson('/api/workday/start')->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-08-13 01:30', 'UTC'));
        Sanctum::actingAs($second, ['access']);
        $this->postJson('/api/workday/start')->assertForbidden()
            ->assertJsonPath('code', 'business.attendance_browser_account_mismatch');
        $this->assertDatabaseMissing('attendances', ['employee_id' => $secondEmployee->id]);
    }

    public function test_hr_can_correct_a_missed_checkout_without_replacing_attendance_or_leave(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-12 03:30', 'UTC'));
        $this->postJson('/api/workday/start')->assertOk();
        $row = Attendance::firstOrFail();
        $row->update(['leave_days' => 0.5, 'off_day_days' => 0.5]);
        Carbon::setTestNow(Carbon::parse('2026-08-13 04:00', 'UTC'));
        $this->getJson('/api/workday/status')->assertOk()->assertJsonPath('data.needsCheckoutCorrection', true);
        $path = '/api/attendances/'.$row->id.'/checkout-correction';
        $this->postJson($path, ['date' => '2026-08-12', 'time' => '12:00'])->assertForbidden();
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($admin, ['access']);
        $this->postJson($path, ['date' => '2026-08-12', 'time' => '07:59'])->assertUnprocessable();
        $this->postJson($path, ['date' => '2026-08-13', 'time' => '12:00'])->assertUnprocessable();
        $this->postJson($path, ['date' => '2026-08-12', 'time' => '12:00'])->assertOk()
            ->assertJsonPath('data.id', $row->id)->assertJsonPath('data.leave_days', 0.5);
        $this->assertSame('hr_correction', $row->fresh()->check_out_source);
        $this->assertSame('2026-08-12T07:30:00.000000Z', $row->fresh()->checked_out_at->toISOString());
        $this->assertSame('2026-08-12T03:30:00.000000Z', $row->fresh()->checked_in_at->toISOString());
        $this->assertDatabaseHas('audit_logs', ['table_name' => 'attendances', 'auditable_id' => $row->id, 'user_id' => $admin->id]);
        Sanctum::actingAs($user, ['access']);
        $this->getJson('/api/workday/status')->assertOk()->assertJsonPath('data.state', 'awaiting_report');
        $this->postJson('/api/workday/end', ['summary' => 'Yesterday completed', 'attendanceId' => $row->id])->assertOk();
        $this->assertSame('2026-08-12T07:30:00.000000Z', $row->fresh()->checked_out_at->toISOString());
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_employee_history_exposes_report_links_and_own_report_without_manager_permissions(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $user->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30', 'UTC'));
        $this->postJson('/api/workday/start')->assertOk();
        $row = Attendance::firstOrFail();
        $this->postJson('/api/workday/end', ['summary' => 'Private daily report'])->assertOk();
        $this->getJson('/api/my-work-history')->assertOk()->assertJsonPath('data.0.daily_report_exists', true);
        $path = '/api/my-work-history/'.$row->id.'/daily-report';
        $this->getJson($path)->assertOk()->assertJsonPath('data.summary', 'Private daily report');
        $other = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $other->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($other, ['access']);
        $this->getJson($path)->assertNotFound();
    }
}
