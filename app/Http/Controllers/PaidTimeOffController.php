<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaidTimeOffResource;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PaidTimeOff;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaidTimeOffController extends Controller
{
    public function all(Request $request): JsonResponse
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2000,3000']]);
        $year = $data['year'] ?? now()->year;
        $rows = PaidTimeOff::with(['employee.position', 'grantor'])->where('year', $year)->latest('date')->get();

        return response()->json([
            'success' => true,
            'data' => PaidTimeOffResource::collection($rows)->resolve(),
            'allowance' => $this->allowance(),
            'year' => $year,
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'business.employee_has_no_account');
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2000,3000']]);

        return response()->json($this->ledger($employee, $data['year'] ?? now()->year));
    }

    public function requestRange(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'business.employee_has_no_account');
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'duration_days' => ['nullable', Rule::in([0.5, 1])],
        ]);
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        abort_if($start->year !== $end->year || $start->diffInDays($end) > 365, 422, 'business.request_rejected');
        $dates = collect(range(0, $start->diffInDays($end)))->map(fn (int $offset) => $start->copy()->addDays($offset)->toDateString());

        $created = DB::transaction(function () use ($employee, $request, $dates, $data, $start) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $existing = $employee->paidTimeOffs()->whereIn('date', $dates)->pluck('date');
            if ($existing->isNotEmpty()) {
                throw ValidationException::withMessages(['start_date' => ['business.paid_time_off_date_exists']]);
            }

            return $dates->map(fn (string $date) => $employee->paidTimeOffs()->create([
                'date' => $date, 'year' => $start->year, 'reason' => $data['reason'],
                'duration_days' => count($dates) === 1 ? ($data['duration_days'] ?? 1) : 1,
                'requested_by' => $request->user()->id, 'is_granted' => false,
            ]));
        });

        return response()->json(['success' => true, 'data' => PaidTimeOffResource::collection($created)->resolve()], 201);
    }

    public function grant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:leaves,id'],
            'force_paid' => ['sometimes', 'boolean'],
            'approval_note' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->boolean('force_paid'))],
        ]);
        $exceeded = 0;
        $rows = DB::transaction(function () use ($data, $request, &$exceeded) {
            $employeeIds = PaidTimeOff::whereIn('id', $data['ids'])
                ->pluck('employee_id')
                ->unique()
                ->sort()
                ->values();
            Employee::whereIn('id', $employeeIds)->orderBy('id')->lockForUpdate()->get();
            $lockedRows = PaidTimeOff::whereIn('employee_id', $employeeIds)
                ->orderBy('employee_id')
                ->orderBy('year')
                ->orderBy('date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $rows = $lockedRows->whereIn('id', $data['ids'])->sortBy('date')->values();
            if ($rows->count() !== count($data['ids'])) {
                throw ValidationException::withMessages(['ids' => ['business.request_rejected']]);
            }

            $allowance = $this->allowance();
            $paidUsage = $lockedRows
                ->where('is_granted', true)
                ->groupBy(fn (PaidTimeOff $row) => "{$row->employee_id}:{$row->year}")
                ->map(fn ($group) => (float) $group->sum('paid_days'));

            foreach ($rows as $row) {
                if ($row->is_granted) {
                    continue;
                }
                $usageKey = "{$row->employee_id}:{$row->year}";
                $paidUsed = (float) $paidUsage->get($usageKey, 0);
                $duration = (float) $row->duration_days;
                $forcePaid = $request->boolean('force_paid');
                $available = max(0, $allowance - $paidUsed);
                $paidDays = $forcePaid ? $duration : min($duration, $available);
                $isPaid = $paidDays >= $duration;
                if ($paidDays < $duration) $exceeded++;
                $paidUsage->put($usageKey, $paidUsed + $paidDays);
                $row->update([
                    'is_granted' => true, 'is_paid' => $isPaid, 'paid_days' => $paidDays,
                    'paid_override' => $forcePaid && $paidUsed + $duration > $allowance,
                    'approval_note' => $data['approval_note'] ?? null,
                    'granted_by' => $request->user()->id, 'granted_at' => now(),
                ]);
                $this->markAttendanceAsTimeOff($row->employee, $row->date->format('Y-m-d'), $row);
            }

            return PaidTimeOff::with(['employee.position', 'grantor'])
                ->whereIn('id', $rows->pluck('id'))
                ->orderBy('date')
                ->get();
        });

        return response()->json(['success' => true, 'data' => PaidTimeOffResource::collection($rows)->resolve(), 'exceeded' => $exceeded]);
    }

    public function index(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureSelectableEmployee($employee);
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2000,3000']]);
        $year = $data['year'] ?? now()->year;

        return response()->json($this->ledger($employee, $year));
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureSelectableEmployee($employee);
        $data = $this->validated($request, $employee);
        $year = (int) substr($data['date'], 0, 4);

        $leave = DB::transaction(function () use ($employee, $data, $year) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $leave = PaidTimeOff::withTrashed()
                ->where('employee_id', $employee->id)
                ->where('date', $data['date'])
                ->lockForUpdate()
                ->first();

            if ($leave) {
                $leave->fill(['year' => $year, 'duration_days' => $data['duration_days'], 'reason' => $data['reason'], 'is_granted' => false, 'is_paid' => null, 'paid_days' => null, 'paid_override' => false, 'requested_by' => request()->user()->id]);
                $leave->restore();
            } else {
                $leave = $employee->paidTimeOffs()->create([
                    'date' => $data['date'],
                    'year' => $year,
                    'duration_days' => $data['duration_days'], 'reason' => $data['reason'],
                    'requested_by' => request()->user()->id,
                    'is_granted' => false,
                ]);
            }

            return $leave;
        });

        return response()->json(['success' => true, 'data' => new PaidTimeOffResource($leave)], 201);
    }

    public function update(Request $request, Employee $employee, PaidTimeOff $paidTimeOff): JsonResponse
    {
        $this->ensureSelectableEmployee($employee);
        abort_unless($paidTimeOff->employee_id === $employee->id, 404);
        $data = $this->validated($request, $employee, $paidTimeOff);
        $year = (int) substr($data['date'], 0, 4);

        DB::transaction(function () use ($employee, $paidTimeOff, $data, $year) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $lockedLeave = PaidTimeOff::whereKey($paidTimeOff->id)->lockForUpdate()->firstOrFail();
            $previousDate = $lockedLeave->date->format('Y-m-d');
            $lockedLeave->update(['date' => $data['date'], 'year' => $year, 'duration_days' => $data['duration_days'], 'reason' => $data['reason'], 'is_granted' => false, 'is_paid' => null, 'paid_days' => null, 'paid_override' => false, 'approval_note' => null, 'granted_by' => null, 'granted_at' => null]);
            $this->removePaidTimeOffAttendance($employee, $previousDate);
        });

        return response()->json(['success' => true, 'data' => new PaidTimeOffResource($paidTimeOff->fresh())]);
    }

    public function destroy(Employee $employee, PaidTimeOff $paidTimeOff): JsonResponse
    {
        $this->ensureSelectableEmployee($employee);
        abort_unless($paidTimeOff->employee_id === $employee->id, 404);
        DB::transaction(function () use ($employee, $paidTimeOff) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $lockedLeave = PaidTimeOff::whereKey($paidTimeOff->id)->lockForUpdate()->firstOrFail();
            $date = $lockedLeave->date->format('Y-m-d');
            $lockedLeave->delete();
            $this->removePaidTimeOffAttendance($employee, $date);
        });

        return response()->json(['success' => true]);
    }

    /** @return array{date: string, duration_days: float, reason: string} */
    private function validated(Request $request, Employee $employee, ?PaidTimeOff $ignore = null): array
    {
        return $request->validate([
            'date' => ['required', 'date', Rule::unique('leaves', 'date')
                ->where('employee_id', $employee->id)
                ->withoutTrashed()
                ->ignore($ignore?->id)],
            'duration_days' => ['required', Rule::in([0.5, 1])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
    }

    private function ensureSelectableEmployee(Employee $employee): void
    {
        if (! Employee::selectable()->whereKey($employee->id)->exists()) {
            throw ValidationException::withMessages([
                'employee_id' => ['business.employee_not_selectable'],
            ]);
        }
    }

    private function markAttendanceAsTimeOff(Employee $employee, string $date, PaidTimeOff $leave): void
    {
        $duration = min(1, max(0, (float) $leave->duration_days));
        $paid = min($duration, max(0, (float) $leave->paid_days));
        $unpaid = max(0, $duration - $paid);
        $status = $paid >= $duration ? 'paid-leave' : 'unpaid-leave';
        $attendance = Attendance::withTrashed()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->lockForUpdate()
            ->first();

        if (! $attendance) {
            Attendance::create([
                'employee_id' => $employee->id,
                'office_id' => $employee->office_id,
                'date' => $date,
                'status' => $status,
                'leave_duration_days' => $duration,
                'paid_leave_days' => $paid,
                'unpaid_leave_days' => $unpaid,
                'logged_at' => now()->format('H:i:s'),
                'check_in_time' => null,
                'check_out_time' => null,
                'checked_in_at' => null,
                'checked_out_at' => null,
            ]);

            return;
        }

        $hasWorkedAttendance = $attendance->status === 'present'
            || $attendance->check_in_time !== null
            || $attendance->check_out_time !== null
            || $attendance->checked_in_at !== null
            || $attendance->checked_out_at !== null
            || $attendance->dailyReport()->exists();

        if ($hasWorkedAttendance && $duration >= 1) {
            throw ValidationException::withMessages([
                'date' => ['business.paid_time_off_conflicts_with_attendance'],
            ]);
        }

        if ($hasWorkedAttendance) {
            $attendance->forceFill([
                'leave_duration_days' => $duration,
                'paid_leave_days' => $paid,
                'unpaid_leave_days' => $unpaid,
                'calculation_hash' => null,
                'calculated_at' => null,
            ])->save();

            return;
        }

        $attendance->fill([
            'status' => $status,
            'leave_duration_days' => $duration,
            'paid_leave_days' => $paid,
            'unpaid_leave_days' => $unpaid,
            'office_id' => $employee->office_id,
            'logged_at' => now()->format('H:i:s'),
            'check_in_time' => null,
            'check_out_time' => null,
            'checked_in_at' => null,
            'checked_out_at' => null,
            'break_minutes' => 0,
            'gross_minutes' => null,
            'net_worked_minutes' => null,
            'required_minutes' => null,
            'late_arrival_minutes' => 0,
            'early_departure_minutes' => 0,
            'overtime_minutes' => 0,
            'missing_punch_state' => 'none',
            'is_overnight_shift' => false,
            'calculation_hash' => null,
            'calculated_at' => null,
        ]);

        if ($attendance->trashed()) {
            $attendance->restore();
        } else {
            $attendance->save();
        }
    }

    private function removePaidTimeOffAttendance(Employee $employee, string $date): void
    {
        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        if (! $attendance) return;
        if (in_array($attendance->status, ['paid-leave', 'unpaid-leave'], true)) {
            $attendance->delete();
            return;
        }
        $attendance->update([
            'leave_duration_days' => 0, 'paid_leave_days' => 0, 'unpaid_leave_days' => 0,
            'calculation_hash' => null, 'calculated_at' => null,
        ]);
    }

    /** @return array{success: bool, data: mixed, allowance: int, used: int, remaining: int, year: int} */
    private function ledger(Employee $employee, int $year): array
    {
        $rows = $employee->paidTimeOffs()->where('year', $year)->latest('date')->get();
        $allowance = (float) (Setting::latest('id')->value('paid_leave_days_per_year') ?? 0);
        $used = (float) $rows->where('is_granted', true)->sum('paid_days');

        return [
            'success' => true,
            'data' => PaidTimeOffResource::collection($rows)->resolve(),
            'allowance' => $allowance,
            'used' => $used,
            'remaining' => max(0, $allowance - $used),
            'pending' => $rows->where('is_granted', false)->count(),
            'year' => $year,
        ];
    }

    private function allowance(): float
    {
        return (float) (Setting::latest('id')->value('paid_leave_days_per_year') ?? 0);
    }
}
