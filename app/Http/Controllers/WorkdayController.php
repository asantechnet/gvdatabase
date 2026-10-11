<?php

namespace App\Http\Controllers;

use App\Http\Resources\DailyReportResource;
use App\Models\Attendance;
use App\Models\DailyReport;
use App\Models\Employee;
use App\Models\Office;
use App\Services\AttendanceBrowserGuard;
use App\Services\AttendanceCalculationService;
use App\Services\OfficeGeofenceService;
use App\Support\CompanyProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkdayController extends Controller
{
    public function status(Request $request, CompanyProfile $company): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return $this->unavailable('unavailable', $company);
        }
        $localNow = now($company->timezone());
        // Pending sessions take priority over weekends, settings and today's row.
        $attendance = $this->pendingAttendance($employee->id)->with('dailyReport')->first();
        if (! $attendance) {
            if (! $company->automaticAttendanceEnabled()) {
                return $this->unavailable('disabled', $company);
            }
            if ($employee->status !== Employee::STATUS_ACTIVE || $employee->join_date->toDateString() > $localNow->toDateString()) {
                return $this->unavailable('blocked', $company);
            }
            if ($localNow->isFriday()) {
                return $this->unavailable('weekend', $company);
            }
            $attendance = Attendance::query()->with('dailyReport')
                ->where('employee_id', $employee->id)->where('date', $localNow->toDateString())->first();
        }

        return response()->json(['data' => $this->statusPayload($attendance, $company->timezone())]);
    }

    private function unavailable(string $state, CompanyProfile $company): JsonResponse
    {
        return response()->json(['data' => [
            'eligible' => false, 'state' => $state, 'timezone' => $company->timezone(),
            'attendance' => null, 'report' => null, 'needsCheckoutCorrection' => false,
        ]]);
    }

    private function pendingAttendance(int $employeeId): Builder
    {
        return Attendance::query()->where('employee_id', $employeeId)->where('status', 'present')
            ->whereDoesntHave('dailyReport')
            ->where(function ($query) {
                $query->whereNotNull('checked_in_at')->orWhereNotNull('check_in_time');
            })
            ->orderByRaw('CASE WHEN checked_out_at IS NULL AND check_out_time IS NULL THEN 0 ELSE 1 END')
            ->orderBy('date')->orderBy('id');
    }

    public function start(Request $request, CompanyProfile $company, AttendanceCalculationService $calculator, OfficeGeofenceService $geofence): JsonResponse
    {
        abort_if(! $company->automaticAttendanceEnabled(), 422, 'business.automatic_attendance_disabled');
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'business.employee_has_no_account');
        $localNow = now($company->timezone());
        abort_unless($employee->status === Employee::STATUS_ACTIVE, 422, 'business.attendance_employee_inactive');
        abort_if($employee->join_date->toDateString() > $localNow->toDateString(), 422, 'business.attendance_before_join_date');
        abort_if($localNow->isFriday(), 422, 'business.friday_weekend_no_attendance');
        $utcNow = now('UTC');

        $attendance = DB::transaction(function () use ($employee, $localNow, $utcNow, $request, $geofence, $calculator) {
            // Lock the employee even before any attendance exists.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $openAttendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'present')
                ->whereNull('checked_out_at')
                ->whereNull('check_out_time')
                ->where(function ($query) {
                    $query->whereNotNull('checked_in_at')->orWhereNotNull('check_in_time');
                })
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

            $isOpenHalfLeave = $attendance && ! $attendance->trashed()
                && (float) $attendance->leave_duration_days > 0
                && (float) $attendance->leave_duration_days < 1
                && in_array($attendance->status, ['paid-leave', 'unpaid-leave'], true);
            if ($attendance && ! $attendance->trashed() && $attendance->status !== 'present' && ! $isOpenHalfLeave) {
                throw ValidationException::withMessages([
                    'workday' => ['business.workday_attendance_conflict'],
                ]);
            }
            if ($attendance?->checked_out_at || $attendance?->check_out_time) {
                abort(409, 'business.workday_already_ended');
            }

            app(AttendanceBrowserGuard::class)->claim($employee->id, $localNow->toDateString());
            if ($attendance && ! $attendance->trashed() && ($attendance->checked_in_at || $attendance->check_in_time)) {
                return $attendance; // Repeated Start clicks must preserve the original punch.
            }
            $employee->loadMissing('office');
            // The firm's enabled fence must not be bypassed by an unassigned profile.
            abort_if(! $employee->office && Office::query()->where('geofence_enabled', true)->exists(),
                422, 'business.office_geofence_unavailable');
            $location = [];
            if ($employee->office?->geofence_enabled) {
                abort_unless($employee->office->hasActiveGeofence(), 422, 'business.office_geofence_unavailable');
                $position = $request->validate([
                    'latitude' => ['required', 'numeric', 'between:-90,90'],
                    'longitude' => ['required', 'numeric', 'between:-180,180'],
                    'accuracy_meters' => ['required', 'numeric', 'between:0,5000'],
                    'observed_at' => ['required', 'numeric'],
                ]);
                abort_if(abs((float) $position['observed_at'] - now('UTC')->getTimestampMs()) > 60000,
                    422, 'business.attendance_location_stale');
                $observation = $geofence->observe($employee, (float) $position['latitude'],
                    (float) $position['longitude'], (int) ceil((float) $position['accuracy_meters']));
                abort_unless($observation['action'] === 'inside', 422, match ($observation['action']) {
                    'inaccurate', 'boundary' => 'business.attendance_location_uncertain',
                    default => 'business.attendance_outside_office',
                });
                $location = [
                    'check_in_latitude' => $position['latitude'], 'check_in_longitude' => $position['longitude'],
                    'check_in_accuracy_meters' => $observation['accuracy_meters'],
                    'check_in_distance_meters' => $observation['distance_meters'],
                ];
            }

            $values = [
                'status' => 'present',
                ...$location,
                'check_in_source' => 'start_my_day',
                'geofence_exit_detected_at' => null,
                'logged_at' => $localNow->format('H:i:s'),
                'check_in_time' => $attendance?->check_in_time ?: $localNow->format('H:i:s'),
                'checked_in_at' => $attendance?->checked_in_at ?: $utcNow,
                'office_id' => $employee->office_id,
            ];

            if ($attendance) {
                $attendance->fill($values);
                if ($attendance->trashed()) {
                    $attendance->restore();
                }
                $attendance->save();
                $calculator->calculate($attendance);

                return $attendance;
            }

            $attendance = Attendance::create([
                'employee_id' => $employee->id,
                'date' => $localNow->toDateString(),
                ...$values,
            ]);
            $calculator->calculate($attendance);

            return $attendance;
        });

        return response()->json(['data' => $this->statusPayload($attendance->load('dailyReport'), $company->timezone())]);
    }

    public function end(Request $request, CompanyProfile $company, AttendanceCalculationService $calculator): JsonResponse
    {
        $validated = $request->validate([
            'attendanceId' => ['sometimes', 'integer', 'min:1'],
            'summary' => ['required', 'string', 'max:5000'],
            'achievements' => ['nullable', 'string', 'max:5000'],
            'challenges' => ['nullable', 'string', 'max:5000'],
            'nextDayPlan' => ['nullable', 'string', 'max:5000'],
            'supportNeeded' => ['nullable', 'string', 'max:5000'],
        ]);
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'business.employee_has_no_account');
        $localNow = now($company->timezone());
        $utcNow = now('UTC');

        [$attendance, $report] = DB::transaction(function () use ($request, $validated, $employee, $localNow, $utcNow, $calculator) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $attendance = $this->pendingAttendance($employee->id)->lockForUpdate()->first();

            abort_unless($attendance, 409, 'business.workday_not_started');
            abort_if(isset($validated['attendanceId']) && (int) $validated['attendanceId'] !== $attendance->id,
                409, 'business.workday_session_changed');
            $open = ! $attendance->checked_out_at && ! $attendance->check_out_time;
            $started = $attendance->checked_in_at ?: Carbon::parse(
                $attendance->date->toDateString().' '.$attendance->check_in_time, app(CompanyProfile::class)->timezone())->utc();
            abort_if($open && $started->diffInHours($utcNow, false) > 24,
                409, 'business.workday_checkout_needs_correction');
            app(AttendanceBrowserGuard::class)->claim($employee->id, $attendance->date->toDateString());
            // Also protect the browser's current local day when ending an overnight session.
            app(AttendanceBrowserGuard::class)->claim($employee->id, $localNow->toDateString());
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
                    'check_out_source' => 'end_my_day',
                    'geofence_exit_detected_at' => null,
                ]);
            }

            $calculator->calculate($attendance);

            return [$attendance, $report];
        });

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
                ? ((float) $attendance->leave_duration_days > 0 && (float) $attendance->leave_duration_days < 1 ? 'not_started' : 'blocked')
                : (($attendance->checked_out_at || $attendance->check_out_time)
                    ? ($attendance->dailyReport ? 'completed' : 'awaiting_report')
                    : 'active'));

        $started = $attendance?->checked_in_at ?: ($attendance?->check_in_time
            ? Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in_time, $timezone)->utc() : null);
        $needsCorrection = $state === 'active' && $started && $started->diffInHours(now('UTC'), false) > 24;

        return [
            'eligible' => true,
            'state' => $state,
            'needsCheckoutCorrection' => (bool) $needsCorrection,
            'timezone' => $timezone,
            'attendance' => $attendance ? [
                'id' => $attendance->id,
                'date' => $attendance->date->format('Y-m-d'),
                'leaveDurationDays' => (float) $attendance->leave_duration_days,
                'paidLeaveDays' => (float) $attendance->paid_leave_days,
                'unpaidLeaveDays' => (float) $attendance->unpaid_leave_days,
                'checkedInAt' => $attendance->checked_in_at?->utc()->toISOString(),
                'checkedOutAt' => $attendance->checked_out_at?->utc()->toISOString(),
                'checkInTime' => $attendance->check_in_time ? substr($attendance->check_in_time, 0, 5) : null,
                'checkOutTime' => $attendance->check_out_time ? substr($attendance->check_out_time, 0, 5) : null,
                'workedMinutes' => $attendance->net_worked_minutes,
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
