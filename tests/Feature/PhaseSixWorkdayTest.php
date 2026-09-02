<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\DailyReport;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseSixWorkdayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        Setting::create([
            'navigation_clr' => '#111111',
            'cards_clr' => '#ffffff',
            'footer_clr' => '#111111',
            'paid_off_days_per_year' => 0,
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

    public function test_employee_starts_and_ends_a_timezone_aware_workday_with_one_report(): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
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
            'summary' => 'Finished the customer onboarding workflow.',
            'achievements' => 'Released the final review.',
            'challenges' => 'Waiting for customer data.',
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
            'summary' => 'Finished the customer onboarding workflow.',
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
        Employee::factory()->create(['user_id' => $user->id]);
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
        $employee = Employee::factory()->create(['user_id' => $user->id]);
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
}
