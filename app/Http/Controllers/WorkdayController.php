<?php

namespace App\Http\Controllers;

use App\Http\Resources\DailyReportResource;
use App\Models\Attendance;
use App\Models\DailyReport;
use App\Support\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\AttendanceCalculationService;

class WorkdayController extends Controller
{
    public function status(Request $request, CompanyProfile $company): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['data' => [
                'eligible' => false,
                'state' => 'unavailable',
                'timezone' => $company->timezone(),
                'attendance' => null,
                'report' => null,
            ]]);
        }

        $localNow = now($company->timezone());
        if ($localNow->isFriday()) {
            return response()->json(['data' => [
                'eligible' => false,
                'state' => 'weekend',
                'timezone' => $company->timezone(),
                'attendance' => null,
                'report' => null,
            ]]);
        }
        $attendance = Attendance::query()
            ->with('dailyReport')
            ->where('employee_id', $employee->id)
            ->where('date', $localNow->toDateString())
            ->first();
        if (! $attendance) {
            $localDayStartUtc = $localNow->copy()->startOfDay()->utc();
            $attendance = Attendance::query()
                ->with('dailyReport')
                ->where('employee_id', $employee->id)
                ->where('status', 'present')
                ->whereDoesntHave('dailyReport')
                ->where(function ($query) use ($localDayStartUtc) {
                    $query->where(function ($open) {
                        $open->whereNull('checked_out_at')->whereNull('check_out_time');
                    })->orWhere('checked_out_at', '>=', $localDayStartUtc);
                })
                ->latest('date')
                ->first();
        }

        return response()->json(['data' => $this->statusPayload($attendance, $company->timezone())]);
    }

    public function start(Request $request, CompanyProfile $company, AttendanceCalculationService $calculator): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'business.employee_has_no_account');
        $localNow = now($company->timezone());
        abort_if($localNow->isFriday(), 422, 'business.friday_weekend_no_attendance');
        $utcNow = now('UTC');

        $attendance = DB::transaction(function () use ($employee, $localNow, $utcNow) {
            $openAttendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'present')
                ->whereNull('checked_out_at')
                ->whereNull('check_out_time')
                ->latest('date')
                ->lockForUpdate()
                ->first();
            abort_if(
                $openAttendance && $openAttendance->date->format('Y-m-d') !== $localNow->toDateString(),
                409,
                'business.workday_already_started',
            );

            $attendance = Attendance::withTrashed()
                ->where('employee_id', $employee->id)
                ->where('date', $localNow->toDateString())
                ->lockForUpdate()
                ->first();

            if ($attendance && ! $attendance->trashed() && $attendance->status !== 'present') {
                throw ValidationException::withMessages([
                    'workday' => ['business.workday_attendance_conflict'],
                ]);
            }
            if ($attendance?->checked_out_at || $attendance?->check_out_time) {
                abort(409, 'business.workday_already_ended');
            }

            $values = [
                'status' => 'present',
                'logged_at' => $localNow->format('H:i:s'),
                'check_in_time' => $attendance?->check_in_time ?: $localNow->format('H:i:s'),
                'checked_in_at' => $attendance?->checked_in_at ?: $utcNow,
                'office_id' => $employee->office_id,
            ];

            if ($attendance) {
                $attendance->fill($values);
                if ($attendance->trashed()) $attendance->restore();
                $attendance->save();

                return $attendance;
            }

            return Attendance::create([
                'employee_id' => $employee->id,
                'date' => $localNow->toDateString(),
                ...$values,
            ]);
        });

        $calculator->calculate($attendance, true);

        return response()->json(['data' => $this->statusPayload($attendance->load('dailyReport'), $company->timezone())]);
    }

    public function end(Request $request, CompanyProfile $company, AttendanceCalculationService $calculator): JsonResponse
    {
        $validated = $request->validate([
            'summary' => ['required', 'string', 'max:5000'],
            'achievements' => ['nullable', 'string', 'max:5000'],
            'challenges' => ['nullable', 'string', 'max:5000'],
            'nextDayPlan' => ['nullable', 'string', 'max:5000'],
            'supportNeeded' => ['nullable', 'string', 'max:5000'],
        ]);
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'business.employee_has_no_account');
        $localNow = now($company->timezone());
        abort_if($localNow->isFriday(), 422, 'business.friday_weekend_no_attendance');
        $utcNow = now('UTC');
        $localDayStartUtc = $localNow->copy()->startOfDay()->utc();

        [$attendance, $report] = DB::transaction(function () use ($request, $validated, $employee, $localNow, $utcNow, $localDayStartUtc) {
            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'present')
                ->whereDoesntHave('dailyReport')
                ->where(function ($query) use ($localDayStartUtc) {
                    $query->where(function ($open) {
                        $open->whereNull('checked_out_at')->whereNull('check_out_time');
                    })->orWhere('checked_out_at', '>=', $localDayStartUtc);
                })
                ->latest('date')
                ->lockForUpdate()
                ->first();

            abort_unless($attendance, 409, 'business.workday_not_started');
            abort_if(DailyReport::where('attendance_id', $attendance->id)->exists(), 409, 'business.daily_report_already_submitted');

            $report = DailyReport::create([
                'user_id' => $request->user()->id,
                'attendance_id' => $attendance->id,
                'report_date' => $attendance->date->format('Y-m-d'),
                'summary' => trim($validated['summary']),
                'achievements' => $this->nullableTrim($validated['achievements'] ?? null),
                'challenges' => $this->nullableTrim($validated['challenges'] ?? null),
                'next_day_plan' => $this->nullableTrim($validated['nextDayPlan'] ?? null),
                'support_needed' => $this->nullableTrim($validated['supportNeeded'] ?? null),
                'submitted_at' => $utcNow,
            ]);

            if (! $attendance->checked_out_at && ! $attendance->check_out_time) {
                $attendance->update([
                    'check_out_time' => $localNow->format('H:i:s'),
                    'checked_out_at' => $utcNow,
                ]);
            }

            return [$attendance, $report];
        });

        $calculator->calculate($attendance, true);

        return response()->json(['data' => [
            ...$this->statusPayload($attendance->setRelation('dailyReport', $report), $company->timezone()),
            'report' => (new DailyReportResource($report))->resolve($request),
        ]]);
    }

    private function statusPayload(?Attendance $attendance, string $timezone): array
    {
        $state = ! $attendance
            ? 'not_started'
            : ($attendance->status !== 'present'
                ? 'blocked'
                : (($attendance->checked_out_at || $attendance->check_out_time)
                    ? ($attendance->dailyReport ? 'completed' : 'awaiting_report')
                    : 'active'));

        return [
            'eligible' => true,
            'state' => $state,
            'timezone' => $timezone,
            'attendance' => $attendance ? [
                'id' => $attendance->id,
                'date' => $attendance->date->format('Y-m-d'),
                'checkedInAt' => $attendance->checked_in_at?->utc()->toISOString(),
                'checkedOutAt' => $attendance->checked_out_at?->utc()->toISOString(),
                'checkInTime' => $attendance->check_in_time ? substr($attendance->check_in_time, 0, 5) : null,
                'checkOutTime' => $attendance->check_out_time ? substr($attendance->check_out_time, 0, 5) : null,
                'workedMinutes' => $attendance->checked_in_at && $attendance->checked_out_at
                    ? (int) $attendance->checked_in_at->diffInMinutes($attendance->checked_out_at)
                    : null,
            ] : null,
            'report' => $attendance?->dailyReport
                ? (new DailyReportResource($attendance->dailyReport))->resolve(request())
                : null,
        ];
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
