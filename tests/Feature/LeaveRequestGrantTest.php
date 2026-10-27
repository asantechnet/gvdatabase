<?php

namespace Tests\Feature;

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceCalculationService;
use App\Support\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LeaveRequestGrantTest extends TestCase
{
    use RefreshDatabase;

    private function employee(float $allowance): Employee
    {
        return Employee::factory()->create(['off_day_allowance_days' => $allowance]);
    }

    /** @param array<int, LeaveRequest> $leaves */
    private function grant(array $leaves, User $approver, bool $force = false, ?string $note = null): array
    {
        $request = Request::create('/api/leave-requests/grant', 'PATCH', [
            'ids' => collect($leaves)->pluck('id')->all(),
            'force_off_day' => $force,
            'approval_note' => $note,
        ]);
        $request->setUserResolver(fn () => $approver);

        return app(LeaveRequestController::class)->grant($request)->getData(true);
    }

    public function test_allowance_covered_requests_become_off_days(): void
    {
        $employee = $this->employee(30);
        $approver = User::factory()->create();
        $leaves = collect(['2026-10-01', '2026-10-03'])->map(fn (string $date) => LeaveRequest::create([
            'employee_id' => $employee->id,
            'date' => $date,
            'year' => 2026,
            'duration_days' => 0.5,
            'reason' => 'Personal appointment',
            'requested_by' => $employee->user_id,
        ]))->all();

        $payload = $this->grant($leaves, $approver);

        $this->assertTrue($payload['success']);
        $this->assertCount(2, $payload['data']);
        $this->assertSame($approver->id, $payload['data'][0]['approved_by']);
        $this->assertNotEmpty($payload['data'][0]['approvedBy']['name']);
        $this->assertSame('off_day', $payload['data'][0]['treatment']);
        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'leave_days' => 0.5,
            'off_day_days' => 0.5,
            'status' => 'off_day',
            'source' => 'leave',
        ]);
    }

    public function test_half_leave_can_be_combined_with_attendance_without_losing_its_fraction(): void
    {
        $employee = $this->employee(10);
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id, 'date' => '2026-08-31', 'year' => 2026,
            'duration_days' => 0.5, 'reason' => 'Morning appointment',
        ]);
        $this->grant([$leave], User::factory()->create());

        $markPresent = Request::create('/api/attendances', 'POST', [
            'staff_id' => $employee->id, 'date' => '2026-08-31', 'status' => 'present',
            'check_in_time' => '13:00', 'check_out_time' => '17:00', 'break_minutes' => 0,
        ]);
        app(AttendanceController::class)->store(
            $markPresent, app(AttendanceCalculationService::class), app(CompanyProfile::class),
        );

        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', '2026-08-31')->firstOrFail();
        $this->assertSame('present', $attendance->status);
        $this->assertSame(0.5, $attendance->leave_days);
        $this->assertSame(0.5, $attendance->off_day_days);
    }

    public function test_a_request_beyond_the_allowance_is_an_absence(): void
    {
        $employee = $this->employee(0);
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id, 'date' => '2026-09-07', 'year' => 2026,
            'duration_days' => 1, 'reason' => 'Family event',
        ]);

        $payload = $this->grant([$leave], User::factory()->create());

        $this->assertSame(1, $payload['exceeded']);
        $this->assertSame('absent', $payload['data'][0]['treatment']);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'date' => '2026-09-07',
            'status' => 'absent',
            'leave_days' => 1,
            'off_day_days' => 0,
        ]);
    }

    public function test_an_authority_can_force_an_over_allowance_day_to_remain_an_off_day(): void
    {
        $employee = $this->employee(0);
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id, 'date' => '2026-09-08', 'year' => 2026,
            'duration_days' => 1, 'reason' => 'Exceptional leave',
        ]);

        $payload = $this->grant([$leave], User::factory()->create(), true, 'Approved by the director.');

        $this->assertSame('off_day', $payload['data'][0]['treatment']);
        $this->assertTrue($payload['data'][0]['off_day_override']);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'date' => '2026-09-08',
            'status' => 'off_day',
            'off_day_days' => 1,
        ]);
    }
}
