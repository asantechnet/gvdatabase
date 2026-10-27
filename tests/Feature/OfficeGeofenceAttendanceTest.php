<?php

namespace Tests\Feature;

use App\Http\Middleware\AttendanceBrowser;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Office;
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

class OfficeGeofenceAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials()->withUnencryptedCookie(AttendanceBrowser::COOKIE,
            Crypt::encryptString((string) Str::uuid()));
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        Setting::create([
            'navigation_clr' => '#111111', 'cards_clr' => '#ffffff', 'footer_clr' => '#111111',
            'off_day_allowance_days_per_year' => 0, 'automatic_attendance_enabled' => true,
            'website_title' => 'Test', 'website_address' => 'https://example.test',
            'company_email' => 'office@example.test', 'timezone' => 'Asia/Kabul', 'phone' => '123',
            'physical_address' => 'Kabul', 'short_company_name' => 'Test', 'original_company_name' => 'Test Company',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_missing_geofence_coordinates_are_both_reported_with_field_names(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $response = $this->postJson('/api/offices', [
            'name' => 'New office', 'code' => 'NEW', 'status' => 'active', 'geofence_enabled' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->assertStringContainsString('Latitude', $response->json('messages.latitude.0'));
        $this->assertStringContainsString('Longitude', $response->json('messages.longitude.0'));
        $this->assertDatabaseCount('offices', 0);
    }

    private function position(float $latitude = 34.5553, int $accuracy = 8): array
    {
        return ['latitude' => $latitude, 'longitude' => 69.2075,
            'accuracy_meters' => $accuracy, 'observed_at' => now('UTC')->getTimestampMs()];
    }

    private function officeEmployee(): Employee
    {
        $office = Office::create([
            'name' => 'Head Office', 'code' => 'HQ', 'status' => 'active',
            'latitude' => 34.5553, 'longitude' => 69.2075,
            'geofence_radius_meters' => 100, 'geofence_enabled' => true,
        ]);
        $user = User::factory()->create(['roles' => ['staff']]);
        Sanctum::actingAs($user, ['access']);

        return Employee::factory()->create(['user_id' => $user->id, 'office_id' => $office->id, 'join_date' => '2026-01-01']);
    }

    public function test_location_aware_start_cannot_bypass_the_browser_account_guard(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));
        $firstEmployee = $this->officeEmployee();
        $this->postJson('/api/workday/start', $this->position())->assertOk();
        $second = User::factory()->create(['roles' => ['staff']]);
        $secondEmployee = Employee::factory()->create(['user_id' => $second->id, 'office_id' => $firstEmployee->office_id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($second, ['access']);
        $this->postJson('/api/workday/start', $this->position())->assertForbidden()
            ->assertJsonPath('code', 'business.attendance_browser_account_mismatch');
        $this->assertDatabaseMissing('attendances', ['employee_id' => $secondEmployee->id]);
    }

    public function test_office_geofence_is_stored_and_exposed_in_meters(): void
    {
        $user = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($user, ['access']);

        $this->postJson('/api/offices', [
            'name' => 'Head Office', 'code' => 'HQ', 'email' => null, 'phone' => null,
            'address' => 'Kabul', 'status' => 'active', 'latitude' => 34.5553,
            'longitude' => 69.2075, 'geofence_radius_meters' => 175, 'geofence_enabled' => true,
        ])->assertCreated()
            ->assertJsonPath('data.latitude', 34.5553)
            ->assertJsonPath('data.longitude', 69.2075)
            ->assertJsonPath('data.geofence_radius_meters', 175)
            ->assertJsonPath('data.geofence_enabled', true);
    }

    public function test_only_explicit_start_and_end_record_punches_even_when_location_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));
        $employee = $this->officeEmployee();
        $this->getJson('/api/workday/geofence')->assertOk()->assertJsonPath('data.eligible', true);
        $this->postJson('/api/workday/geofence', $this->position())->assertOk()
            ->assertJsonPath('data.action', 'inside')->assertJsonPath('data.attendance', null);
        $this->assertDatabaseCount('attendances', 0);

        Carbon::setTestNow(Carbon::parse('2026-08-13 04:00:00', 'UTC'));
        $this->postJson('/api/workday/start', $this->position())->assertOk();
        $attendance = Attendance::firstOrFail();
        $this->assertSame($employee->id, $attendance->employee_id);
        $this->assertSame('start_my_day', $attendance->check_in_source);
        $this->assertSame('2026-08-13T04:00:00.000000Z', $attendance->checked_in_at->toISOString());

        Carbon::setTestNow(Carbon::parse('2026-08-13 07:30:00', 'UTC'));
        $this->postJson('/api/workday/geofence', $this->position(34.5600))->assertOk()
            ->assertJsonPath('data.action', 'outside');
        Carbon::setTestNow(Carbon::parse('2026-08-13 08:00:00', 'UTC'));
        $this->postJson('/api/workday/geofence', $this->position(34.5600))->assertOk();
        $this->assertNull($attendance->fresh()->checked_out_at);
        $this->postJson('/api/workday/start')->assertOk(); // Repeat never changes the original check-in.
        $this->assertSame('2026-08-13T04:00:00.000000Z', $attendance->fresh()->checked_in_at->toISOString());
        $this->getJson('/api/workday/status')->assertOk()->assertJsonPath('data.state', 'active');

        Carbon::setTestNow(Carbon::parse('2026-08-13 12:30:00', 'UTC'));
        $this->postJson('/api/workday/end', ['summary' => 'Completed planned work.', 'attendanceId' => $attendance->id])
            ->assertOk()->assertJsonPath('data.state', 'completed');
        $this->assertSame('end_my_day', $attendance->fresh()->check_out_source);
        $this->assertSame('2026-08-13T12:30:00.000000Z', $attendance->fresh()->checked_out_at->toISOString());
    }

    public function test_start_rejects_missing_stale_outside_and_uncertain_location_without_claiming_browser(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));
        $this->officeEmployee();
        $this->postJson('/api/workday/start')->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $position = $this->position();
        $position['observed_at'] -= 61000;
        $this->postJson('/api/workday/start', $position)->assertUnprocessable()
            ->assertJsonPath('code', 'business.attendance_location_stale');
        $this->postJson('/api/workday/start', $this->position(34.5600))->assertUnprocessable()
            ->assertJsonPath('code', 'business.attendance_outside_office');
        $this->postJson('/api/workday/start', $this->position(34.5553, 250))->assertUnprocessable()
            ->assertJsonPath('code', 'business.attendance_location_uncertain');
        // The coordinate lies inside, but its uncertainty extends beyond the boundary.
        $this->postJson('/api/workday/start', $this->position(34.5561, 20))->assertUnprocessable()
            ->assertJsonPath('code', 'business.attendance_location_uncertain');
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('attendance_browser_days', 0);
        $this->postJson('/api/workday/start', $this->position())->assertOk();
    }

    public function test_enabled_office_cannot_lose_coordinates_through_a_partial_update(): void
    {
        $employee = $this->officeEmployee();
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($admin, ['access']);
        $this->putJson('/api/offices/'.$employee->office_id, [
            'name' => 'Head Office', 'code' => 'HQ', 'status' => 'active', 'latitude' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->putJson('/api/offices/'.$employee->office_id, [
            'name' => 'Renamed', 'code' => 'HQ', 'status' => 'active', 'geofence_enabled' => true,
        ])->assertOk();
    }

    public function test_disabled_automatic_attendance_makes_geofence_ineligible_and_ineffective(): void
    {
        Setting::query()->latest('id')->firstOrFail()->update(['automatic_attendance_enabled' => false]);
        $office = Office::create([
            'name' => 'Head Office', 'code' => 'HQ', 'status' => 'active',
            'latitude' => 34.5553, 'longitude' => 69.2075,
            'geofence_radius_meters' => 100, 'geofence_enabled' => true,
        ]);
        $user = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $user->id, 'office_id' => $office->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);

        $this->getJson('/api/workday/geofence')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.reason', 'automatic_attendance_disabled');
        $this->postJson('/api/workday/geofence', ['latitude' => 34.5553, 'longitude' => 69.2075, 'accuracy_meters' => 5])
            ->assertStatus(422);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_inaccurate_location_does_not_create_attendance(): void
    {
        $office = Office::create([
            'name' => 'Head Office', 'code' => 'HQ', 'status' => 'active',
            'latitude' => 34.5553, 'longitude' => 69.2075,
            'geofence_radius_meters' => 100, 'geofence_enabled' => true,
        ]);
        $user = User::factory()->create(['roles' => ['staff']]);
        Employee::factory()->create(['user_id' => $user->id, 'office_id' => $office->id, 'join_date' => '2026-01-01']);
        Sanctum::actingAs($user, ['access']);

        $this->postJson('/api/workday/geofence', [
            'latitude' => 34.5553, 'longitude' => 69.2075, 'accuracy_meters' => 250,
        ])->assertOk()->assertJsonPath('data.action', 'inaccurate');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_missing_office_assignment_cannot_bypass_the_firms_enabled_geofence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));
        $employee = $this->officeEmployee();
        $employee->update(['office_id' => null]);
        Sanctum::actingAs(User::findOrFail($employee->user_id), ['access']);
        $this->postJson('/api/workday/start', $this->position())->assertUnprocessable()
            ->assertJsonPath('code', 'business.office_geofence_unavailable');
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('attendance_browser_days', 0);
    }
}
