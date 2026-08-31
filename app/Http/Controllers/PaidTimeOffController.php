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

        return response()->json(['success' => true, 'data' => PaidTimeOffResource::collection($rows)->resolve(), 'year' => $year]);
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
        ]);
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        abort_if($start->year !== $end->year || $start->diffInDays($end) > 365, 422, 'business.request_rejected');
        $dates = collect(range(0, $start->diffInDays($end)))->map(fn (int $offset) => $start->copy()->addDays($offset)->toDateString());
        $allowance = $this->allowance();
        $reserved = $employee->paidTimeOffs()->where('year', $start->year)->count();
        if ($dates->count() > max(0, $allowance - $reserved)) {
            throw ValidationException::withMessages(['end_date' => ['business.paid_time_off_limit_reached']]);
        }

        $created = DB::transaction(function () use ($employee, $request, $dates, $data, $start) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $existing = $employee->paidTimeOffs()->whereIn('date', $dates)->pluck('date');
            if ($existing->isNotEmpty()) {
                throw ValidationException::withMessages(['start_date' => ['business.paid_time_off_date_exists']]);
            }

            return $dates->map(fn (string $date) => $employee->paidTimeOffs()->create([
                'date' => $date, 'year' => $start->year, 'reason' => $data['reason'],
                'requested_by' => $request->user()->id, 'is_granted' => false,
            ]));
        });

        return response()->json(['success' => true, 'data' => PaidTimeOffResource::collection($created)->resolve()], 201);
    }

    public function grant(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'distinct', 'exists:paid_time_offs,id']]);
        $exceeded = 0;
        $rows = DB::transaction(function () use ($data, $request, &$exceeded) {
            $rows = PaidTimeOff::whereIn('id', $data['ids'])->orderBy('date')->lockForUpdate()->get();
            foreach ($rows as $row) {
                if ($row->is_granted) {
                    continue;
                }
                $paidUsed = PaidTimeOff::where('employee_id', $row->employee_id)->where('year', $row->year)
                    ->where('is_granted', true)->where('is_paid', true)->count();
                $isPaid = $paidUsed < $this->allowance();
                if (! $isPaid) {
                    $exceeded++;
                }
                $row->update(['is_granted' => true, 'is_paid' => $isPaid, 'granted_by' => $request->user()->id, 'granted_at' => now()]);
                $this->markAttendanceAsTimeOff($row->employee, $row->date->format('Y-m-d'), $isPaid);
            }

            return $rows->load(['employee.position', 'grantor']);
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
                $leave->fill(['year' => $year, 'reason' => $data['reason'], 'is_granted' => false, 'is_paid' => null, 'requested_by' => request()->user()->id]);
                $leave->restore();
            } else {
                $leave = $employee->paidTimeOffs()->create([
                    'date' => $data['date'],
                    'year' => $year,
                    'reason' => $data['reason'],
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
            $previousDate = $paidTimeOff->date->format('Y-m-d');
            $paidTimeOff->update(['date' => $data['date'], 'year' => $year, 'reason' => $data['reason'], 'is_granted' => false, 'is_paid' => null, 'granted_by' => null, 'granted_at' => null]);
            $this->removePaidTimeOffAttendance($employee, $previousDate);
        });

        return response()->json(['success' => true, 'data' => new PaidTimeOffResource($paidTimeOff->fresh())]);
    }

    public function destroy(Employee $employee, PaidTimeOff $paidTimeOff): JsonResponse
    {
        $this->ensureSelectableEmployee($employee);
        abort_unless($paidTimeOff->employee_id === $employee->id, 404);
        DB::transaction(function () use ($employee, $paidTimeOff) {
            $date = $paidTimeOff->date->format('Y-m-d');
            $paidTimeOff->delete();
            $this->removePaidTimeOffAttendance($employee, $date);
        });

        return response()->json(['success' => true]);
    }

    /** @return array{date: string, reason: string} */
    private function validated(Request $request, Employee $employee, ?PaidTimeOff $ignore = null): array
    {
        return $request->validate([
            'date' => ['required', 'date', Rule::unique('paid_time_offs', 'date')
                ->where('employee_id', $employee->id)
                ->withoutTrashed()
                ->ignore($ignore?->id)],
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

    private function markAttendanceAsTimeOff(Employee $employee, string $date, bool $isPaid): void
    {
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
                'status' => $isPaid ? 'paid-off-day' : 'unpaid-off-day',
                'logged_at' => now()->format('H:i:s'),
                'check_in_time' => null,
                'check_out_time' => null,
                'checked_in_at' => null,
                'checked_out_at' => null,
            ]);

            return;
        }

        $attendance->fill([
            'status' => $isPaid ? 'paid-off-day' : 'unpaid-off-day',
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
        Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->whereIn('status', ['paid-off-day', 'unpaid-off-day'])
            ->first()?->delete();
    }

    /** @return array{success: bool, data: mixed, allowance: int, used: int, remaining: int, year: int} */
    private function ledger(Employee $employee, int $year): array
    {
        $rows = $employee->paidTimeOffs()->where('year', $year)->latest('date')->get();
        $allowance = (int) (Setting::latest('id')->value('paid_off_days_per_year') ?? 0);
        $used = $rows->where('is_granted', true)->where('is_paid', true)->count();

        return [
            'success' => true,
            'data' => PaidTimeOffResource::collection($rows)->resolve(),
            'allowance' => $allowance,
            'used' => $used,
            'remaining' => max(0, $allowance - $rows->count()),
            'pending' => $rows->where('is_granted', false)->count(),
            'year' => $year,
        ];
    }

    private function allowance(): int
    {
        return (int) (Setting::latest('id')->value('paid_off_days_per_year') ?? 0);
    }
}
