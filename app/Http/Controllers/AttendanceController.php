<?php

namespace App\Http\Controllers;

use App\Http\Resources\AttendanceResource;
use App\Http\Resources\DailyReportResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceCalculationService;
use App\Support\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    /**
     * Fetch attendance records within a given date range.
     */
    public function index(Request $request, AttendanceCalculationService $calculator): AnonymousResourceCollection
    {
        $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'employee_id' => 'sometimes|integer|exists:employees,id',
            'office_id' => 'sometimes|integer|exists:offices,id',
            'status' => ['sometimes', Rule::in(['present', 'absent', 'paid-leave', 'unpaid-leave', 'incomplete'])],
        ]);

        $startDate = $request->input('start_date', now()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        // Daily-report contents deliberately stay out of this list query. The
        // timesheet fetches an individual report only when it is opened.
        $records = Attendance::with(['staff.position', 'office'])
            ->withExists('dailyReport')
            ->betweenDates($startDate, $endDate)
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('office_id'), fn ($query) => $query->where('office_id', $request->integer('office_id')))
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->input('status') === 'incomplete') {
                    return $query->where('status', 'present')->where('missing_punch_state', '!=', 'none');
                }
                if ($request->input('status') === 'paid-leave') {
                    return $query->where('paid_leave_days', '>', 0);
                }
                if ($request->input('status') === 'unpaid-leave') {
                    return $query->where('unpaid_leave_days', '>', 0);
                }

                return $query->where('status', $request->input('status'));
            })
            ->orderBy('date', 'desc')
            ->orderBy('logged_at', 'desc')
            ->get();

        // Office hours can change after a row was recorded. Refresh stale
        // summaries before returning them so overtime always reflects the
        // current worked-time-minus-office-time calculation.
        $records->each(fn (Attendance $attendance) => $calculator->calculate($attendance));

        return AttendanceResource::collection($records);
    }

    /** Fetch the daily report for one attendance row, on demand. */
    public function dailyReport(Attendance $attendance): DailyReportResource
    {
        $report = $attendance->dailyReport()->firstOrFail();

        return new DailyReportResource($report);
    }

    /** Current users can only access their own attendance and submitted reports. */
    public function mine(Request $request, AttendanceCalculationService $calculator): AnonymousResourceCollection
    {
        $request->validate(['start_date' => ['sometimes', 'date'], 'end_date' => ['sometimes', 'date', 'after_or_equal:start_date']]);
        $employeeId = $request->user()->employee?->id;
        abort_unless($employeeId, 404, 'Employee profile not found.');
        $records = Attendance::with(['staff.position', 'office', 'dailyReport'])
            ->betweenDates($request->input('start_date', now()->startOfMonth()->toDateString()), $request->input('end_date', now()->toDateString()))
            ->where('employee_id', $employeeId)->latest('date')->get();
        $records->each(fn (Attendance $attendance) => $calculator->calculate($attendance));

        return AttendanceResource::collection($records);
    }

    /**
     * Log attendance for an employee.
     */
    public function store(Request $request, AttendanceCalculationService $calculator, CompanyProfile $company): JsonResponse|AttendanceResource
    {
        $validated = $request->validate([
            'staff_id' => 'required|integer|exists:employees,id',
            'date' => 'required|date',
            'status' => ['required', Rule::in(['present', 'absent'])],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
        ]);

        if (! Employee::selectable()->whereKey($validated['staff_id'])->exists()) {
            throw ValidationException::withMessages([
                'staff_id' => ['business.employee_not_selectable'],
            ]);
        }
        if (Carbon::parse($validated['date'])->isFriday()) {
            throw ValidationException::withMessages(['date' => ['business.friday_weekend_no_attendance']]);
        }

        $employee = Employee::findOrFail($validated['staff_id']);
        $timezone = $company->timezone();
        $attendanceDate = Carbon::parse($validated['date'], $timezone)->startOfDay();
        abort_if($attendanceDate->isAfter(now($timezone)->startOfDay()), 422, 'business.future_attendance_not_allowed');
        abort_if($employee->join_date && $attendanceDate->isBefore($employee->join_date->copy()->startOfDay()), 422, 'business.attendance_before_join_date');
        $checkedInAt = $validated['status'] === 'present' && ! empty($validated['check_in_time'])
            ? Carbon::parse($validated['date'].' '.$validated['check_in_time'], $timezone)->utc() : null;
        $checkedOutAt = $validated['status'] === 'present' && ! empty($validated['check_out_time'])
            ? Carbon::parse($validated['date'].' '.$validated['check_out_time'], $timezone)->utc() : null;
        if ($checkedInAt && $checkedOutAt && $checkedOutAt->lte($checkedInAt)) {
            $checkedOutAt->addDay();
        }

        $attendance = DB::transaction(function () use ($validated, $employee, $checkedInAt, $checkedOutAt) {
            // Attendance is soft-deleted for audit history, while the database
            // keeps a unique employee/date key. Reuse a deleted row instead of
            // colliding with that key when the user records it again.
            $attendance = Attendance::withTrashed()
                ->where('employee_id', $validated['staff_id'])
                ->where('date', $validated['date'])
                ->lockForUpdate()
                ->first();

            $isOpenHalfLeave = $attendance && ! $attendance->trashed()
                && (float) $attendance->leave_duration_days > 0
                && (float) $attendance->leave_duration_days < 1
                && in_array($attendance->status, ['paid-leave', 'unpaid-leave'], true);
            if ($attendance && ! $attendance->trashed() && ! $isOpenHalfLeave) {
                throw ValidationException::withMessages([
                    'staff_id' => ['business.attendance_already_recorded'],
                ]);
            }

            if ($attendance) {
                $attendance->fill([
                    'status' => $validated['status'],
                    'logged_at' => now()->format('H:i:s'),
                    'check_in_time' => $validated['check_in_time'] ?? null,
                    'check_out_time' => $validated['check_out_time'] ?? null,
                    'checked_in_at' => $checkedInAt,
                    'checked_out_at' => $checkedOutAt,
                    'office_id' => $employee->office_id,
                    'break_minutes' => $validated['break_minutes'] ?? null,
                ]);
                if ($attendance->trashed()) {
                    $attendance->restore();
                } else {
                    $attendance->save();
                }

                return $attendance;
            }

            return Attendance::create([
                'employee_id' => $validated['staff_id'],
                'date' => $validated['date'],
                'status' => $validated['status'],
                'logged_at' => now()->format('H:i:s'),
                'check_in_time' => $validated['check_in_time'] ?? null,
                'check_out_time' => $validated['check_out_time'] ?? null,
                'checked_in_at' => $checkedInAt,
                'checked_out_at' => $checkedOutAt,
                'office_id' => $employee->office_id,
                'break_minutes' => $validated['break_minutes'] ?? null,
            ]);
        });

        $calculator->calculate($attendance, true);

        $attendance->load(['staff.position', 'office']);

        return new AttendanceResource($attendance);
    }

    /**
     * Fetch staff who have NOT been marked for a specific start date.
     */
    public function unmarkedStaff(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->input('date');
        abort_if(Carbon::parse($date)->isFuture(), 422, 'business.future_attendance_not_allowed');
        if (Carbon::parse($date)->isFriday()) {
            return EmployeeResource::collection(collect());
        }

        $unmarkedStaff = Employee::selectable()->whereDoesntHave('attendances', function ($query) use ($date) {
            $query->where('date', $date)->where(function ($marked) {
                $marked->whereIn('status', ['present', 'absent'])
                    ->orWhere('leave_duration_days', '>=', 1);
            });
        })->get();

        return EmployeeResource::collection($unmarkedStaff);
    }

    /**
     * Delete an attendance entry.
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        if ((float) $attendance->leave_duration_days > 0) {
            $attendance->update([
                'status' => (float) $attendance->paid_leave_days >= (float) $attendance->leave_duration_days
                    ? 'paid-leave' : 'unpaid-leave',
                'check_in_time' => null, 'check_out_time' => null,
                'checked_in_at' => null, 'checked_out_at' => null,
                'break_minutes' => 0, 'gross_minutes' => null, 'net_worked_minutes' => null,
                'required_minutes' => null, 'late_arrival_minutes' => 0,
                'early_departure_minutes' => 0, 'overtime_minutes' => 0,
                'missing_punch_state' => 'none', 'calculation_hash' => null, 'calculated_at' => null,
            ]);

            return response()->json(['message' => 'Attendance work entry removed; approved leave was preserved.']);
        }
        $attendance->delete();

        return response()->json([
            'message' => 'Attendance record deleted successfully.',
        ]);
    }
}
