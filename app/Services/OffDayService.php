<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OffDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies an approved company off day (a holiday) to the attendance ledger and
 * reverses it.
 *
 * A holiday is a company-wide `off_day`: it is never absent and never deducted.
 * Days that already contain real work or an approved off-day request are never
 * overwritten.
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

                    if ($attendance && ($this->hasWork($attendance) || (float) $attendance->leave_days > 0)) {
                        $counts['skipped']++;

                        continue;
                    }

                    $attributes = [
                        'employee_id' => $employee->id,
                        'office_id' => $employee->office_id,
                        'date' => $date->toDateString(),
                        'status' => 'off_day',
                        'source' => 'holiday',
                        'off_day_id' => $offDay->id,
                        'leave_request_id' => null,
                        'leave_days' => 0,
                        'off_day_days' => 1,
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
                    $attendance->fill(['status' => 'present', 'off_day_days' => 0]);
                    if ($attendance->trashed()) {
                        $attendance->restore();
                    } else {
                        $attendance->save();
                    }

                    continue;
                }

                // Releasing the claim leaves no evidence of work; deleting the
                // synthesized row lets the attendance processor rebuild the
                // day from the real ledger (leave request, missing policy).
                $attendance->forceDelete();
            }
        });

        $this->recalculate($offDay);
    }

    /**
     * The pending or approved company off day that shares at least one date
     * with the requested range. Rejected ranges never block a new one, and
     * the row being edited is excluded so it cannot conflict with itself.
     */
    public function findOverlap(string $start, string $end, ?int $ignoreId = null): ?OffDay
    {
        return OffDay::query()
            ->whereIn('status', [OffDay::STATUS_PENDING, OffDay::STATUS_APPROVED])
            ->overlapping($start, $end)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->orderBy('start_date')
            ->first();
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
