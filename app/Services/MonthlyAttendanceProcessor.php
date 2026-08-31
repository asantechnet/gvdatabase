<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\MonthlyAttendanceSummary;
use App\Models\PaidTimeOff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyAttendanceProcessor
{
    public function __construct(private readonly AttendanceCalculationService $calculator) {}

    /** @return array{created_missing: int, recalculated: int, summaries: int} */
    public function process(Carbon $start, Carbon $end, bool $createMissing = true, bool $force = false, bool $alwaysSummarize = true): array
    {
        $createdMissing = $createMissing ? $this->materializeMissingDays($start, $end) : 0;
        $recalculated = 0;
        Attendance::query()->with('staff')->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('id')->chunkById(200, function ($rows) use (&$recalculated, $force): void {
                foreach ($rows as $attendance) {
                    if ($this->calculator->calculate($attendance, $force)) $recalculated++;
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
        $employees = Employee::selectable()->whereDate('join_date', '<=', $end)->get(['id', 'office_id', 'join_date']);
        foreach ($employees as $employee) {
            $employeeStart = $start->copy()->max(Carbon::parse($employee->join_date));
            for ($day = $employeeStart->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
                // Afghanistan's regular weekly day off is Friday. Saturday and
                // Sunday remain normal working days unless a future holiday rule says otherwise.
                if ($day->isFriday()) continue;
                $date = $day->toDateString();
                if (Attendance::withTrashed()->where('employee_id', $employee->id)->where('date', $date)->exists()) continue;
                $leave = PaidTimeOff::query()->where('employee_id', $employee->id)->whereDate('date', $date)
                    ->where('is_granted', true)->first();
                Attendance::create([
                    'employee_id' => $employee->id,
                    'office_id' => $employee->office_id,
                    'date' => $date,
                    'status' => $leave ? ($leave->is_paid ? 'paid-off-day' : 'unpaid-off-day') : 'absent',
                    'logged_at' => '00:00:00',
                ]);
                $created++;
            }
        }

        return $created;
    }

    private function summarizeMonth(int $year, int $month, Carbon $start, Carbon $end): int
    {
        $employeeIds = Employee::selectable()->whereDate('join_date', '<=', $end)->pluck('id');
        foreach ($employeeIds as $employeeId) {
            $query = Attendance::query()->where('employee_id', $employeeId)->whereBetween('date', [$start, $end]);
            MonthlyAttendanceSummary::updateOrCreate(
                ['employee_id' => $employeeId, 'year' => $year, 'month' => $month],
                [
                    'present_days' => (clone $query)->where('status', 'present')->count(),
                    'absent_days' => (clone $query)->whereIn('status', ['absent', 'unpaid-off-day'])->count(),
                    'paid_leave_days' => (clone $query)->where('status', 'paid-off-day')->count(),
                    'incomplete_days' => (clone $query)->where('status', 'present')->where('missing_punch_state', '!=', 'none')->count(),
                    'gross_minutes' => (int) (clone $query)->sum('gross_minutes'),
                    'break_minutes' => (int) (clone $query)->sum('break_minutes'),
                    'net_worked_minutes' => (int) (clone $query)->sum('net_worked_minutes'),
                    'required_minutes' => (int) (clone $query)->sum('required_minutes'),
                    'late_arrival_minutes' => (int) (clone $query)->sum('late_arrival_minutes'),
                    'early_departure_minutes' => (int) (clone $query)->sum('early_departure_minutes'),
                    'overtime_minutes' => (int) (clone $query)->sum('overtime_minutes'),
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
