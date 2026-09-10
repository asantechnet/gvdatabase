<?php

namespace Tests\Feature;

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PaidTimeOffController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PaidTimeOff;
use App\Models\User;
use App\Services\AttendanceCalculationService;
use App\Support\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaidLeaveGrantTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_grant_returns_loaded_leave_records_without_collection_errors(): void
    {
        $employee = Employee::factory()->create();
        $approver = User::factory()->create();
        $leaves = collect(['2026-10-01', '2026-10-03'])->map(fn (string $date) => PaidTimeOff::create([
            'employee_id' => $employee->id,
            'date' => $date,
            'year' => 2026,
            'duration_days' => 0.5,
            'reason' => 'Personal appointment',
            'requested_by' => $employee->user_id,
        ]));
        $request = Request::create('/api/leaves/grant', 'PATCH', [
            'ids' => $leaves->pluck('id')->all(),
            'force_paid' => true,
            'approval_note' => 'Authorized paid half-day leave for test coverage.',
        ]);
        $request->setUserResolver(fn () => $approver);

        $response = app(PaidTimeOffController::class)->grant($request);
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertCount(2, $payload['data']);
        $this->assertSame($approver->id, $payload['data'][0]['approved_by']);
        $this->assertNotEmpty($payload['data'][0]['approvedBy']['name']);
        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'leave_duration_days' => 0.5,
            'paid_leave_days' => 0.5,
            'unpaid_leave_days' => 0,
        ]);
    }

    public function test_half_leave_can_be_combined_with_attendance_without_losing_its_fraction(): void
    {
        $employee = Employee::factory()->create();
        $leave = PaidTimeOff::create([
            'employee_id' => $employee->id, 'date' => '2026-08-31', 'year' => 2026,
            'duration_days' => 0.5, 'reason' => 'Morning appointment',
        ]);
        $approver = User::factory()->create();
        $grant = Request::create('/api/leaves/grant', 'PATCH', [
            'ids' => [$leave->id], 'force_paid' => true,
            'approval_note' => 'Authorized paid half-day leave for test coverage.',
        ]);
        $grant->setUserResolver(fn () => $approver);
        app(PaidTimeOffController::class)->grant($grant);

        $markPresent = Request::create('/api/attendances', 'POST', [
            'staff_id' => $employee->id, 'date' => '2026-08-31', 'status' => 'present',
            'check_in_time' => '13:00', 'check_out_time' => '17:00', 'break_minutes' => 0,
        ]);
        app(AttendanceController::class)->store(
            $markPresent, app(AttendanceCalculationService::class), app(CompanyProfile::class),
        );

        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', '2026-08-31')->firstOrFail();
        $this->assertSame('present', $attendance->status);
        $this->assertSame(0.5, $attendance->leave_duration_days);
        $this->assertSame(0.5, $attendance->paid_leave_days);
        $this->assertSame(0.0, $attendance->unpaid_leave_days);
    }
}
