<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OffDay;
use App\Models\PaidTimeOff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies approved off days to the attendance ledger and reverses them.
 *
 * An off day is a paid, worked-free day: it is written to attendance as a
 * `present` row carrying the `off_day_id`, so payroll counts it as present
 * while the payslip can still report it separately as an off day. Days that
 * already contain real work (punches or a daily report) and half/full leave
 * rows are never overwritten.
 */
class OffDayService
{
    public function __construct(private readonly MonthlyAttendanceProcessor $attendance) {}

    /** @return array{created: int, updated: int, skipped: int} */
    public function apply(OffDay $offDay): array
    {
        if (! $offDay->isApproved()) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $counts = DB::transaction(function () use ($offDay): array {
            $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];
            $dates = $this->dates($offDay);
            $employees = Employee::selectable()->active()
                ->whereDate('join_date', '<=', $offDay->end_date->format('Y-m-d'))
                ->get(['id', 'office_id', 'join_date']);

            foreach ($employees as $employee) {
                foreach ($dates as $date) {
                    if ($employee->join_date && $date->lt($employee->join_date->copy()->startOfDay())) {
                        continue;
                    }

                    $attendance = Attendance::withTrashed()
                        ->where('employee_id', $employee->id)
                        ->where('date', $date->toDateString())
                        ->lockForUpdate()
                        ->first();

                    if ($attendance && ($this->hasWork($attendance) || (float) $attendance->leave_duration_days > 0)) {
                        $counts['skipped']++;

                        continue;
                    }

                    $attributes = [
                        'employee_id' => $employee->id,
                        'office_id' => $employee->office_id,
                        'date' => $date->toDateString(),
                        'status' => 'present',
                        'off_day_id' => $offDay->id,
                        'leave_duration_days' => 0,
                        'paid_leave_days' => 0,
                        'unpaid_leave_days' => 0,
                        'logged_at' => '00:00:00',
                        'check_in_time' => null,
                        'check_out_time' => null,
                        'checked_in_at' => null,
                        'checked_out_at' => null,
                        'missing_punch_state' => 'none',
                        'calculation_hash' => null,
                        'calculated_at' => null,
                    ];

                    if ($attendance) {
                        // A stale absent row is claimed by the holiday.
                        $attendance->fill($attributes);
                        if ($attendance->trashed()) {
                            $attendance->restore();
                        } else {
                            $attendance->save();
                        }
                        $counts['updated']++;
                    } else {
                        Attendance::create($attributes);
                        $counts['created']++;
                    }
                }
            }

            return $counts;
        });

        $this->recalculate($offDay);

        return $counts;
    }

    /** Reverts every attendance row that this holiday claimed. */
    public function revert(OffDay $offDay): void
    {
        DB::transaction(function () use ($offDay): void {
            $rows = Attendance::withTrashed()->where('off_day_id', $offDay->id)->lockForUpdate()->get();

            foreach ($rows as $attendance) {
                $attendance->forceFill(['off_day_id' => null, 'calculation_hash' => null, 'calculated_at' => null]);

                if ($this->hasWork($attendance) || $attendance->dailyReport()->exists()) {
                    if ($attendance->trashed()) {
                        $attendance->restore();
                    } else {
                        $attendance->save();
                    }

                    continue;
                }

                $leave = PaidTimeOff::query()
                    ->where('employee_id', $attendance->employee_id)
                    ->whereDate('date', $attendance->date)
                    ->where('is_granted', true)
                    ->first();

                if ($leave) {
                    $duration = min(1, max(0, (float) $leave->duration_days));
                    $paid = min($duration, max(0, (float) $leave->paid_days));
                    $attendance->fill([
                        'status' => $paid >= $duration ? 'paid-leave' : 'unpaid-leave',
                        'leave_duration_days' => $duration,
                        'paid_leave_days' => $paid,
                        'unpaid_leave_days' => max(0, $duration - $paid),
                        'logged_at' => '00:00:00',
                    ]);
                    if ($attendance->trashed()) {
                        $attendance->restore();
                    } else {
                        $attendance->save();
                    }

                    continue;
                }

                // Releasing the claim leaves no evidence of work; deleting the
                // synthesized row lets the attendance processor rebuild the
                // day from the real ledger (absent, leave or Friday).
                $attendance->forceDelete();
            }
        });

        $this->recalculate($offDay);
    }

    /** @return array<int, Carbon> */
    private function dates(OffDay $offDay): array
    {
        $start = $offDay->start_date->copy()->startOfDay();
        $end = $offDay->end_date->copy()->startOfDay();
        $dates = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            // Friday is the weekly day off and is never materialized.
            if (! $day->isFriday()) {
                $dates[] = $day->copy();
            }
        }

        return $dates;
    }

    private function hasWork(Attendance $attendance): bool
    {
        return $attendance->check_in_time !== null
            || $attendance->check_out_time !== null
            || $attendance->checked_in_at !== null
            || $attendance->checked_out_at !== null;
    }

    private function recalculate(OffDay $offDay): void
    {
        $this->attendance->process(
            $offDay->start_date->copy()->startOfMonth(),
            $offDay->end_date->copy()->endOfMonth(),
            createMissing: false,
            force: true,
            alwaysSummarize: true,
        );
    }
}
