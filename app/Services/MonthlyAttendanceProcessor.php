<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MonthlyAttendanceSummary;
use App\Models\OffDay;
use App\Models\Setting;
use App\Support\CompanyProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recalculates attendance sessions, materializes elapsed workdays that have no
 * row, and keeps monthly summaries aligned with the three-status ledger:
 * `present`, `absent` and `off_day`.
 *
 * A row carries two fractions:
 *  - `leave_days`: the part of the day the employee was away (0, 0.5 or 1);
 *  - `off_day_days`: the part of that absence covered by an allowance or a
 *    company holiday. The uncovered remainder is an absence.
 */
class MonthlyAttendanceProcessor
{
    public const MISSING_STATUSES = ['present', 'absent', 'off_day'];

    public function __construct(
        private readonly AttendanceCalculationService $calculator,
        private readonly CompanyProfile $company,
    ) {}

    /** @return array{created_missing: int, recalculated: int, summaries: int} */
    public function process(Carbon $start, Carbon $end, bool $createMissing = true, bool $force = false, bool $alwaysSummarize = true): array
    {
        $createdMissing = $createMissing ? $this->materializeMissingDays($start, $end) : 0;
        $recalculated = 0;
        Attendance::query()->with('staff')->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('id')->chunkById(200, function ($rows) use (&$recalculated, $force): void {
                foreach ($rows as $attendance) {
                    if ($this->calculator->calculate($attendance, $force)) {
                        $recalculated++;
                    }
                }
            });

        $summaryCount = 0;
        if (! $alwaysSummarize && $createdMissing === 0 && $recalculated === 0) {
            return ['created_missing' => 0, 'recalculated' => 0, 'summaries' => 0];
        }

        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            // A range backfill may touch only part of a month, but the persisted
            // row always represents the complete calendar month.
            $periodStart = $cursor->copy()->startOfMonth();
            $periodEnd = $cursor->copy()->endOfMonth();
            $summaryCount += $this->summarizeMonth($cursor->year, $cursor->month, $periodStart, $periodEnd);
            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        $this->refreshEmployeeTotals();

        return ['created_missing' => $createdMissing, 'recalculated' => $recalculated, 'summaries' => $summaryCount];
    }

    /**
     * Create an attendance row for every elapsed non-Friday workday that has
     * none. The row's status is the configured missing-day policy, unless an
     * approved company holiday or an approved off-day request owns that date.
     */
    public function materializeMissingDays(Carbon $start, Carbon $end): int
    {
        $created = 0;
        // Never mark a still-running local day, or a future day, by policy.
        $end = $end->copy()->min(now($this->company->timezone())->subDay()->endOfDay());
        if ($start->toDateString() > $end->toDateString()) {
            return 0;
        }

        $policy = $this->missingStatus();
        $employees = Employee::selectable()->active()->whereDate('join_date', '<=', $end)->get(['id', 'office_id', 'join_date']);
        $offDays = OffDay::query()->approved()
            ->overlapping($start->toDateString(), $end->toDateString())
            ->get(['id', 'start_date', 'end_date']);
        $offDayFor = function (string $date) use ($offDays): ?OffDay {
            return $offDays->first(fn (OffDay $offDay) => $offDay->start_date->format('Y-m-d') <= $date
                && $offDay->end_date->format('Y-m-d') >= $date);
        };

        foreach ($employees as $employee) {
            $employeeStart = $start->copy()->max(Carbon::parse($employee->join_date));
            for ($day = $employeeStart->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
                // Friday is the weekly day off and is never materialized.
                if ($day->isFriday()) {
                    continue;
                }
                $date = $day->toDateString();
                if (Attendance::withTrashed()->where('employee_id', $employee->id)->where('date', $date)->exists()) {
                    continue;
                }

                $offDay = $offDayFor($date);
                $leave = ! $offDay
                    ? LeaveRequest::query()
                        ->where('employee_id', $employee->id)
                        ->whereDate('date', $date)
                        ->where('is_granted', true)
                        ->first()
                    : null;

                $attributes = [
                    'employee_id' => $employee->id,
                    'office_id' => $employee->office_id,
                    'date' => $date,
                    'logged_at' => '00:00:00',
                ];

                if ($offDay) {
                    $attributes += [
                        'status' => 'off_day',
                        'off_day_id' => $offDay->id,
                        'leave_request_id' => null,
                        'leave_days' => 0,
                        'off_day_days' => 1,
                        'source' => 'holiday',
                    ];
                } elseif ($leave) {
                    $duration = min(1, max(0, (float) $leave->duration_days));
                    $covered = min($duration, max(0, (float) $leave->off_day_days));
                    $attributes += [
                        'status' => $covered >= $duration ? 'off_day' : 'absent',
                        'off_day_id' => null,
                        'leave_request_id' => $leave->id,
                        'leave_days' => $duration,
                        'off_day_days' => $covered,
                        'source' => 'leave',
                    ];
                } else {
                    $attributes += [
                        'status' => $policy,
                        'off_day_id' => null,
                        'leave_request_id' => null,
                        'leave_days' => 0,
                        'off_day_days' => $policy === 'off_day' ? 1 : 0,
                        'source' => 'missing_policy',
                    ];
                }

                $row = Attendance::withTrashed()->createOrFirst(
                    ['employee_id' => $employee->id, 'date' => $date],
                    $attributes,
                );
                $created += (int) $row->wasRecentlyCreated;
            }
        }

        return $created;
    }

    public function missingStatus(): string
    {
        $status = Setting::latest('id')->value('missing_attendance_status');

        return in_array($status, self::MISSING_STATUSES, true) ? $status : 'absent';
    }

    /**
     * Working days in a period for one employee: every day except Friday,
     * clipped to the employment start.
     */
    public function workingDays(Employee $employee, Carbon $start, Carbon $end): int
    {
        $from = $start->copy()->startOfDay();
        if ($employee->join_date && $employee->join_date->gt($from)) {
            $from = $employee->join_date->copy()->startOfDay();
        }
        $working = 0;
        for ($day = $from; $day->lte($end); $day->addDay()) {
            if (! $day->isFriday()) {
                $working++;
            }
        }

        return $working;
    }

    private function summarizeMonth(int $year, int $month, Carbon $start, Carbon $end): int
    {
        $employees = Employee::selectable()->whereDate('join_date', '<=', $end)->get(['id', 'join_date']);
        foreach ($employees as $employee) {
            $rows = Attendance::query()->where('employee_id', $employee->id)->whereBetween('date', [$start, $end])->get();
            $present = $rows->sum(fn (Attendance $row) => $row->status === 'present'
                ? max(0, 1 - (float) $row->leave_days) : 0);
            $offDays = $rows->sum(fn (Attendance $row) => (float) $row->off_day_days);
            // Today is still in progress and future days have not happened, so
            // a partial-month summary must not count them as absences.
            $elapsedEnd = $end->copy()->min(now($this->company->timezone())->subDay()->endOfDay());
            $total = $elapsedEnd->lt($start) ? 0 : $this->workingDays($employee, $start, $elapsedEnd);
            $absent = max(0, $total - $present - $offDays);

            MonthlyAttendanceSummary::updateOrCreate(
                ['employee_id' => $employee->id, 'year' => $year, 'month' => $month],
                [
                    'present_days' => round($present, 1),
                    'absent_days' => round($absent, 1),
                    'off_days' => round($offDays, 1),
                    'incomplete_days' => $rows->where('status', 'present')->where('missing_punch_state', '!=', 'none')->count(),
                    'gross_minutes' => (int) $rows->sum('gross_minutes'),
                    'break_minutes' => (int) $rows->sum(fn (Attendance $row) => max(0, (int) $row->gross_minutes - (int) $row->net_worked_minutes)),
                    'net_worked_minutes' => (int) $rows->sum('net_worked_minutes'),
                    'required_minutes' => (int) $rows->sum('required_minutes'),
                    'late_arrival_minutes' => (int) $rows->sum('late_arrival_minutes'),
                    'early_departure_minutes' => (int) $rows->sum('early_departure_minutes'),
                    'overtime_minutes' => (int) $rows->sum('overtime_minutes'),
                    'calculated_at' => now('UTC'),
                ],
            );
        }

        return $employees->count();
    }

    private function refreshEmployeeTotals(): void
    {
        Employee::selectable()->select('id')->chunkById(200, function ($employees): void {
            foreach ($employees as $employee) {
                $minutes = (int) MonthlyAttendanceSummary::where('employee_id', $employee->id)->sum('net_worked_minutes');
                DB::table('employees')->where('id', $employee->id)->update([
                    'total_worked_hours' => round($minutes / 60, 2),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
