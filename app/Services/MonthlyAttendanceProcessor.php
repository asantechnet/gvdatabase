<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\MonthlyAttendanceSummary;
use App\Models\OffDay;
use App\Models\PaidTimeOff;
use App\Support\CompanyProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyAttendanceProcessor
{
    public function __construct(
        private readonly AttendanceCalculationService $calculator,
        private readonly CompanyProfile $company,
    ) {}

    /** @return array{created_missing: int, recalculated: int, summaries: int} */
    public function process(Carbon $start, Carbon $end, bool $createMissing = true, bool $force = false, bool $alwaysSummarize = true): array
    {
        // Disabling automatic attendance prevents the system from inventing
        // absent rows. Existing manual attendance still needs deterministic
        // calculations and monthly summaries for payroll and reporting.
        $createMissing = $createMissing && $this->company->automaticAttendanceEnabled();
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
            // A range backfill may touch only part of a month, but the persisted row
            // always represents the complete calendar month.
            $periodStart = $cursor->copy()->startOfMonth();
            $periodEnd = $cursor->copy()->endOfMonth();
            $summaryCount += $this->summarizeMonth($cursor->year, $cursor->month, $periodStart, $periodEnd);
            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        $this->refreshEmployeeTotals();

        return ['created_missing' => $createdMissing, 'recalculated' => $recalculated, 'summaries' => $summaryCount];
    }

    private function materializeMissingDays(Carbon $start, Carbon $end): int
    {
        $created = 0;
        // Never mark a still-running local day, or a future day, absent.
        $end = $end->copy()->min(now($this->company->timezone())->subDay()->endOfDay());
        if ($start->toDateString() > $end->toDateString()) {
            return 0;
        }
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
                // Afghanistan's regular weekly day off is Friday. Saturday and
                // Sunday remain normal working days unless a future holiday rule says otherwise.
                if ($day->isFriday()) {
                    continue;
                }
                $date = $day->toDateString();
                if (Attendance::withTrashed()->where('employee_id', $employee->id)->where('date', $date)->exists()) {
                    continue;
                }
                $offDay = $offDayFor($date);
                if ($offDay) {
                    $row = Attendance::withTrashed()->createOrFirst(['employee_id' => $employee->id, 'date' => $date], [
                        'employee_id' => $employee->id,
                        'office_id' => $employee->office_id,
                        'date' => $date,
                        // An off day counts as present for payroll without
                        // pretending the employee worked. The holiday id lets
                        // the payslip report it separately.
                        'status' => 'present',
                        'off_day_id' => $offDay->id,
                        'leave_duration_days' => 0,
                        'paid_leave_days' => 0,
                        'unpaid_leave_days' => 0,
                        'logged_at' => '00:00:00',
                    ]);
                    $created += (int) $row->wasRecentlyCreated;

                    continue;
                }

                $leave = PaidTimeOff::query()->where('employee_id', $employee->id)->whereDate('date', $date)
                    ->where('is_granted', true)->first();
                $paidDays = (float) ($leave?->paid_days ?? 0);
                $isPaidLeave = $leave && ((bool) $leave->is_paid || $paidDays > 0);
                $row = Attendance::withTrashed()->createOrFirst(['employee_id' => $employee->id, 'date' => $date], [
                    'employee_id' => $employee->id,
                    'office_id' => $employee->office_id,
                    'date' => $date,
                    'status' => $leave ? ($isPaidLeave ? 'paid-leave' : 'unpaid-leave') : 'absent',
                    'leave_duration_days' => (float) ($leave?->duration_days ?? 0),
                    'paid_leave_days' => $paidDays,
                    'unpaid_leave_days' => $leave ? max(0, (float) $leave->duration_days - $paidDays) : 0,
                    'logged_at' => '00:00:00',
                ]);
                $created += (int) $row->wasRecentlyCreated;
            }
        }

        return $created;
    }

    private function summarizeMonth(int $year, int $month, Carbon $start, Carbon $end): int
    {
        $employeeIds = Employee::selectable()->whereDate('join_date', '<=', $end)->pluck('id');
        foreach ($employeeIds as $employeeId) {
            $rows = Attendance::query()->where('employee_id', $employeeId)->whereBetween('date', [$start, $end])->get();
            $present = $rows->sum(fn (Attendance $row) => $row->status === 'present'
                ? max(0, 1 - (float) $row->leave_duration_days) : 0);
            $paid = $rows->sum(fn (Attendance $row) => (float) $row->paid_leave_days);
            $offDays = $rows->whereNotNull('off_day_id')->count();
            $unpaid = $rows->sum(fn (Attendance $row) => (float) $row->unpaid_leave_days);
            $unworked = $rows->sum(fn (Attendance $row) => $row->status !== 'present'
                ? max(0, 1 - (float) $row->leave_duration_days) : 0);
            MonthlyAttendanceSummary::updateOrCreate(
                ['employee_id' => $employeeId, 'year' => $year, 'month' => $month],
                [
                    'present_days' => round($present, 1),
                    'absent_days' => round($unworked + $unpaid, 1),
                    'paid_leave_days' => round($paid, 1),
                    'off_days' => round((float) $offDays, 1),
                    'unpaid_leave_days' => round($unpaid, 1),
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

        return $employeeIds->count();
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
