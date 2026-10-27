<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Writes an approved off-day request into the attendance ledger.
 *
 * A request is a day (or half day) away. Its allowance-covered part becomes an
 * `off_day`; the uncovered remainder becomes an `absent`. When the employee
 * already worked that day (a half-day request), the row stays `present` and
 * carries the two fractions, so the worked part is still paid.
 */
class LeaveRequestService
{
    public function __construct(private readonly MonthlyAttendanceProcessor $attendance) {}

    public function apply(LeaveRequest $leave): void
    {
        $employee = $leave->employee;
        abort_unless($employee, 422, 'business.employee_not_selectable');

        $date = $leave->date->format('Y-m-d');
        $attendance = Attendance::withTrashed()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->lockForUpdate()
            ->first();

        $duration = min(1, max(0, (float) $leave->duration_days));
        $covered = min($duration, max(0, (float) $leave->off_day_days));

        $hasWork = $attendance && (
            $attendance->check_in_time !== null
            || $attendance->check_out_time !== null
            || $attendance->checked_in_at !== null
            || $attendance->checked_out_at !== null
            || $attendance->dailyReport()->exists()
        );

        if ($hasWork && $duration >= 1) {
            throw ValidationException::withMessages([
                'date' => ['business.leave_request_conflicts_with_attendance'],
            ]);
        }

        if ($hasWork) {
            $attendance->forceFill([
                'leave_days' => $duration,
                'off_day_days' => $covered,
                'leave_request_id' => $leave->id,
                'source' => 'leave',
                'calculation_hash' => null,
                'calculated_at' => null,
            ])->save();

            return;
        }

        // A company holiday already owns the date; the request stays a ledger
        // record but never overrides the holiday's pay treatment.
        if ($attendance && $attendance->off_day_id !== null) {
            return;
        }

        $attributes = [
            'status' => $covered >= $duration ? 'off_day' : 'absent',
            'leave_days' => $duration,
            'off_day_days' => $covered,
            'leave_request_id' => $leave->id,
            'source' => 'leave',
            'office_id' => $employee->office_id,
            'logged_at' => '00:00:00',
            'check_in_time' => null,
            'check_out_time' => null,
            'checked_in_at' => null,
            'checked_out_at' => null,
            'break_minutes' => 0,
            'gross_minutes' => null,
            'net_worked_minutes' => null,
            'required_minutes' => null,
            'late_arrival_minutes' => 0,
            'early_departure_minutes' => 0,
            'overtime_minutes' => 0,
            'missing_punch_state' => 'none',
            'is_overnight_shift' => false,
            'calculation_hash' => null,
            'calculated_at' => null,
        ];

        if ($attendance) {
            $attendance->fill($attributes);
            if ($attendance->trashed()) {
                $attendance->restore();
            }
            $attendance->save();

            return;
        }

        Attendance::create($attributes + [
            'employee_id' => $employee->id,
            'date' => $date,
        ]);
    }

    /**
     * Remove a request's claim from the ledger. The day is then rebuilt by the
     * missing-day policy (or by the next company-holiday pass).
     */
    public function release(LeaveRequest $leave, ?string $date = null): void
    {
        $employee = $leave->employee;
        if (! $employee) {
            return;
        }

        $date ??= $leave->date->format('Y-m-d');
        $attendance = Attendance::withTrashed()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->lockForUpdate()
            ->first();

        if (! $attendance) {
            return;
        }

        $hasWork = $attendance->check_in_time !== null
            || $attendance->check_out_time !== null
            || $attendance->checked_in_at !== null
            || $attendance->checked_out_at !== null
            || $attendance->dailyReport()->exists();

        if ($hasWork) {
            $attendance->forceFill([
                'leave_days' => 0,
                'off_day_days' => 0,
                'leave_request_id' => null,
                'calculation_hash' => null,
                'calculated_at' => null,
            ])->save();

            return;
        }

        $attendance->forceFill(['leave_request_id' => null]);

        if ($attendance->off_day_id !== null) {
            // The company holiday keeps the day; only the request claim goes.
            $attendance->save();

            return;
        }

        $attendance->forceDelete();
    }

    public function recalculate(LeaveRequest $leave): void
    {
        $this->attendance->process(
            $leave->date->copy()->startOfMonth(),
            $leave->date->copy()->endOfMonth(),
            createMissing: true,
            force: true,
            alwaysSummarize: true,
        );
    }

    /** Allowance already consumed by granted requests in one year. */
    public function usedAllowance(int $employeeId, int $year, ?int $ignoreId = null): float
    {
        return (float) LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('is_granted', true)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->sum('off_day_days');
    }

    /** @return array<int, Carbon> */
    public function rangeDates(string $start, string $end): array
    {
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        $dates = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            // Friday is the weekly day off and is skipped, never rejected.
            if (! $day->isFriday()) {
                $dates[] = $day->copy();
            }
        }

        return $dates;
    }
}
