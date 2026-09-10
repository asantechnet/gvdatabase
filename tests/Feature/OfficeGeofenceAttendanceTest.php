<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficeGeofenceAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        Setting::create([
            'navigation_clr' => '#111111', 'cards_clr' => '#ffffff', 'footer_clr' => '#111111',
            'paid_leave_days_per_year' => 0, 'automatic_attendance_enabled' => true,
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

    public function test_geofence_checks_in_and_confirms_exit_before_checking_out(): void
    {
        $office = Office::create([
            'name' => 'Head Office', 'code' => 'HQ', 'status' => 'active',
            'latitude' => 34.5553, 'longitude' => 69.2075,
            'geofence_radius_meters' => 100, 'geofence_enabled' => true,
        ]);
        $user = User::factory()->create(['roles' => ['staff']]);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'office_id' => $office->id]);
        Sanctum::actingAs($user, ['access']);
        Carbon::setTestNow(Carbon::parse('2026-08-13 03:30:00', 'UTC'));

        $this->getJson('/api/workday/geofence')
            ->assertOk()->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.office.radius_meters', 100);

        $this->postJson('/api/workday/geofence', [
            'latitude' => 34.5553, 'longitude' => 69.2075, 'accuracy_meters' => 8,
        ])->assertOk()->assertJsonPath('data.action', 'checked_in')->assertJsonPath('data.inside', true);

        $attendance = Attendance::firstOrFail();
        $this->assertSame($employee->id, $attendance->employee_id);
        $this->assertSame('office_geofence', $attendance->check_in_source);
        $this->assertSame(0, $attendance->check_in_distance_meters);
        $this->assertNull($attendance->checked_out_at);

        Carbon::setTestNow(Carbon::parse('2026-08-13 03:35:00', 'UTC'));
        $this->postJson('/api/workday/geofence', [
            'latitude' => 34.5600, 'longitude' => 69.2075, 'accuracy_meters' => 8,
        ])->assertOk()->assertJsonPath('data.action', 'exit_pending')
            ->assertJsonPath('data.exit_confirmation_seconds_remaining', 120);
        $this->assertNull($attendance->fresh()->checked_out_at);

        Carbon::setTestNow(Carbon::parse('2026-08-13 03:37:01', 'UTC'));
        $this->postJson('/api/workday/geofence', [
            'latitude' => 34.5600, 'longitude' => 69.2075, 'accuracy_meters' => 8,
        ])->assertOk()->assertJsonPath('data.action', 'checked_out');

        $attendance->refresh();
        $this->assertSame('office_geofence', $attendance->check_out_source);
        $this->assertNotNull($attendance->checked_out_at);
        $this->getJson('/api/workday/status')->assertOk()->assertJsonPath('data.state', 'awaiting_report');

        $this->postJson('/api/workday/end', ['summary' => 'Completed planned work.'])
            ->assertOk()->assertJsonPath('data.state', 'completed');
        $this->assertSame('2026-08-13T03:37:01.000000Z', $attendance->fresh()->checked_out_at->toISOString());
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
        Employee::factory()->create(['user_id' => $user->id, 'office_id' => $office->id]);
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
        Employee::factory()->create(['user_id' => $user->id, 'office_id' => $office->id]);
        Sanctum::actingAs($user, ['access']);

        $this->postJson('/api/workday/geofence', [
            'latitude' => 34.5553, 'longitude' => 69.2075, 'accuracy_meters' => 250,
        ])->assertOk()->assertJsonPath('data.action', 'inaccurate');
        $this->assertDatabaseCount('attendances', 0);
    }
}
