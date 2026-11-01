<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveRequestEdgeCaseTest extends TestCase
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
            'off_day_allowance_days' => 10,
        ], $overrides));
    }

    /**
     * Self-service needs the employee's own account. `staff` can request its
     * own leave and is not a super administrator, which would make the
     * employee ineligible for staff records. Roles are synced on user
     * creation only, so the account is created with its role.
     */
    private function actingAsStaff(Employee $employee): void
    {
        $user = User::factory()->create(['roles' => ['staff']]);
        $employee->forceFill(['user_id' => $user->id])->save();
        Sanctum::actingAs($user, ['access']);
    }

    public function test_a_range_skips_fridays_and_reports_every_stored_day(): void
    {
        $employee = $this->employee();
        $this->actingAsStaff($employee);

        $this->postJson('/api/my-leave-requests', [
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-09',
            'reason' => 'Family visit',
        ])->assertCreated()
            ->assertJsonPath('stored', 4)
            ->assertJsonCount(4, 'data');

        foreach (['2026-11-05', '2026-11-07', '2026-11-08', '2026-11-09'] as $date) {
            $this->assertDatabaseHas('leaves', ['employee_id' => $employee->id, 'date' => $date]);
        }

        $this->assertDatabaseMissing('leaves', [
            'employee_id' => $employee->id,
            'date' => '2026-11-06',
        ]);
        $this->assertDatabaseCount('leaves', 4);
    }

    public function test_a_range_containing_only_friday_is_rejected_without_rows(): void
    {
        $employee = $this->employee();
        $this->actingAsStaff($employee);

        $this->postJson('/api/my-leave-requests', [
            'start_date' => '2026-11-06',
            'end_date' => '2026-11-06',
            'reason' => 'Personal errand',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'business.leave_range_contains_no_workdays');

        $this->assertDatabaseCount('leaves', 0);
    }

    public function test_a_range_that_reuses_an_existing_date_rolls_back_every_new_row(): void
    {
        $employee = $this->employee();
        $this->actingAsStaff($employee);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-11-10',
            'year' => 2026,
            'duration_days' => 1,
            'reason' => 'Existing request',
            'requested_by' => $employee->user_id,
        ]);

        $this->postJson('/api/my-leave-requests', [
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'reason' => 'Overlapping range',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'business.leave_request_date_exists')
            ->assertJsonStructure(['errors' => ['start_date']]);

        $this->assertDatabaseCount('leaves', 1);
        $this->assertDatabaseMissing('leaves', ['employee_id' => $employee->id, 'date' => '2026-11-11']);
        $this->assertDatabaseMissing('leaves', ['employee_id' => $employee->id, 'date' => '2026-11-12']);
    }

    public function test_past_and_pre_employment_starts_return_specific_codes(): void
    {
        $employee = $this->employee();
        $this->actingAsStaff($employee);

        $this->postJson('/api/my-leave-requests', [
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-06',
            'reason' => 'Past request',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'business.retroactive_leave_request_not_allowed');

        $late = $this->employee(['join_date' => '2026-12-01']);
        $this->actingAsStaff($late);

        $this->postJson('/api/my-leave-requests', [
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-03',
            'reason' => 'Before employment',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'business.leave_before_employment');

        $this->assertDatabaseCount('leaves', 0);
    }

    public function test_approving_the_same_request_twice_deducts_allowance_once(): void
    {
        $user = $this->admin();
        $employee = $this->employee(['off_day_allowance_days' => 10]);
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-11-02',
            'year' => 2026,
            'duration_days' => 1,
            'reason' => 'Family event',
            'requested_by' => $user->id,
        ]);

        $this->patchJson('/api/leave-requests/grant', ['ids' => [$leave->id]])
            ->assertOk()
            ->assertJsonPath('data.0.treatment', 'off_day');

        $attendanceId = Attendance::firstOrFail()->id;

        $this->patchJson('/api/leave-requests/grant', ['ids' => [$leave->id]])
            ->assertOk()
            ->assertJsonPath('data.0.treatment', 'off_day');

        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame($attendanceId, Attendance::firstOrFail()->id);
        $this->assertSame(1.0, (float) $leave->fresh()->off_day_days);

        $this->getJson("/api/employees/{$employee->id}/leave-requests?year=2026")
            ->assertOk()
            ->assertJsonPath('used', 1)
            ->assertJsonPath('remaining', 9);
    }

    public function test_a_forced_off_day_requires_and_stores_a_justification(): void
    {
        $user = $this->admin();
        $employee = $this->employee(['off_day_allowance_days' => 0]);
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-11-03',
            'year' => 2026,
            'duration_days' => 1,
            'reason' => 'Exceptional leave',
            'requested_by' => $user->id,
        ]);

        $this->patchJson('/api/leave-requests/grant', [
            'ids' => [$leave->id],
            'force_off_day' => true,
        ])->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['approval_note']]);

        $this->assertFalse($leave->fresh()->is_granted);
        $this->assertDatabaseCount('attendances', 0);

        $this->patchJson('/api/leave-requests/grant', [
            'ids' => [$leave->id],
            'force_off_day' => true,
            'approval_note' => 'Approved by the director.',
        ])->assertOk()
            ->assertJsonPath('data.0.treatment', 'off_day')
            ->assertJsonPath('data.0.approval_note', 'Approved by the director.');

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'approval_note' => 'Approved by the director.',
            'off_day_override' => true,
        ]);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'date' => '2026-11-03',
            'status' => 'off_day',
            'off_day_days' => 1,
        ]);
    }
}
