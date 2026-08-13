<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\AttendanceCalculationService;
use App\Support\CompanyProfile;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    /**
     * Fetch attendance records within a given date range.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'employee_id' => 'sometimes|integer|exists:employees,id',
            'office_id' => 'sometimes|integer|exists:offices,id',
            'status' => ['sometimes', Rule::in(['present', 'absent', 'paid-off-day', 'unpaid-off-day', 'incomplete'])],
        ]);

        $startDate = $request->input('start_date', now()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $records = Attendance::with(['staff.position', 'office'])
            ->betweenDates($startDate, $endDate)
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('office_id'), fn ($query) => $query->where('office_id', $request->integer('office_id')))
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->input('status') === 'incomplete') return $query->where('status', 'present')->where('missing_punch_state', '!=', 'none');
                return $query->where('status', $request->input('status'));
            })
            ->orderBy('date', 'desc')
            ->orderBy('logged_at', 'desc')
            ->get();

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

        $employee = Employee::findOrFail($validated['staff_id']);
        $timezone = $company->timezone();
        $checkedInAt = $validated['status'] === 'present' && ! empty($validated['check_in_time'])
            ? Carbon::parse($validated['date'].' '.$validated['check_in_time'], $timezone)->utc() : null;
        $checkedOutAt = $validated['status'] === 'present' && ! empty($validated['check_out_time'])
            ? Carbon::parse($validated['date'].' '.$validated['check_out_time'], $timezone)->utc() : null;
        if ($checkedInAt && $checkedOutAt && $checkedOutAt->lte($checkedInAt)) $checkedOutAt->addDay();

        $attendance = DB::transaction(function () use ($validated, $employee, $checkedInAt, $checkedOutAt) {
            // Attendance is soft-deleted for audit history, while the database
            // keeps a unique employee/date key. Reuse a deleted row instead of
            // colliding with that key when the user records it again.
            $attendance = Attendance::withTrashed()
                ->where('employee_id', $validated['staff_id'])
                ->where('date', $validated['date'])
                ->lockForUpdate()
                ->first();

            if ($attendance && ! $attendance->trashed()) {
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
                $attendance->restore();

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

        $unmarkedStaff = Employee::selectable()->whereDoesntHave('attendances', function ($query) use ($date) {
            $query->where('date', $date);
        })->get();

        return EmployeeResource::collection($unmarkedStaff);
    }

    /**
     * Delete an attendance entry.
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $attendance->delete();

        return response()->json([
            'message' => 'Attendance record deleted successfully.'
        ]);
    }
}
