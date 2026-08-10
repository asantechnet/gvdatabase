<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

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
        ]);

        $startDate = $request->input('start_date', now()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $records = Attendance::with('staff')
            ->betweenDates($startDate, $endDate)
            ->orderBy('date', 'desc')
            ->orderBy('logged_at', 'desc')
            ->get();

        return AttendanceResource::collection($records);
    }

    /**
     * Log attendance for an employee.
     */
    public function store(Request $request): JsonResponse|AttendanceResource
    {
        $validated = $request->validate([
            'staff_id' => 'required|integer|exists:employees,id',
            'date' => 'required|date',
            'status' => ['required', Rule::in(['present', 'absent'])],
        ]);

        // Check if attendance already exists for this staff member on this date
        $exists = Attendance::where('employee_id', $validated['staff_id'])
            ->where('date', $validated['date'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Attendance for this staff member has already been recorded for this date.'
            ], 422);
        }

        $attendance = Attendance::create([
            'employee_id' => $validated['staff_id'],
            'date' => $validated['date'],
            'status' => $validated['status'],
            'logged_at' => now()->format('H:i:s'),
        ]);

        $attendance->load('staff');

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

        $unmarkedStaff = Employee::whereDoesntHave('attendances', function ($query) use ($date) {
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
