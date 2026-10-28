<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Services\LeaveRequestService;
use App\Support\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    use PaginatesLists;
    public function __construct(
        private readonly CompanyProfile $company,
        private readonly LeaveRequestService $ledger,
    ) {}

    public function all(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,3000'],
            // The same treatment the ledger shows in its status column.
            'status' => ['nullable', Rule::in(['pending', 'off_day', 'partial', 'absent'])],
        ]);
        $year = $data['year'] ?? now()->year;
        $query = LeaveRequest::with(['employee.position', 'employee.office', 'grantor'])
            ->where('year', $year)->latest('date')->latest('id');
        $this->applyTreatmentFilter($query, $data['status'] ?? null);

        // The allowance is per employee, so the ledger ships the resolved map,
        // and the approved usage of the whole year so an approval is checked
        // against the employee's real remaining allowance, not just this page.
        $shared = [
            'allowance' => $this->defaultAllowance(),
            'usage' => $this->usedOffDays($year),
            'year' => $year,
        ];

        if ($this->requestedPerPage($request)) {
            $paginator = $query->paginate($this->requestedPerPage($request))->withQueryString();
            $rows = collect($paginator->items());

            return response()->json([
                'success' => true,
                'data' => LeaveRequestResource::collection($rows)->resolve(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'allowances' => $this->allowancesFor($rows),
            ] + $shared);
        }

        $rows = $query->get();

        return response()->json([
            'success' => true,
            'data' => LeaveRequestResource::collection($rows)->resolve(),
            'allowances' => $this->allowancesFor($rows),
        ] + $shared);
    }

    /** Narrow the ledger to the treatment its status column shows. */
    private function applyTreatmentFilter($query, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        if ($status === 'pending') {
            $query->where('is_granted', false);

            return;
        }

        $query->where('is_granted', true);
        match ($status) {
            'off_day' => $query->whereColumn('off_day_days', '>=', 'duration_days'),
            'partial' => $query->where('off_day_days', '>', 0)->whereColumn('off_day_days', '<', 'duration_days'),
            'absent' => $query->where(fn ($q) => $q->whereNull('off_day_days')->orWhere('off_day_days', '<=', 0)),
            default => null,
        };
    }

    /** Approved off-day days per employee for the year. */
    private function usedOffDays(int $year): array
    {
        return LeaveRequest::query()
            ->where('year', $year)
            ->where('is_granted', true)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(off_day_days) as used')
            ->pluck('used', 'employee_id')
            ->map(fn ($used) => (float) $used)
            ->all();
    }

    /**
     * The resolved per-employee allowance for the rows the caller is looking
     * at. A paged ledger only ships the employees on that page.
     */
    private function allowancesFor(\Illuminate\Support\Collection $rows): array
    {
        return $rows->pluck('employee')->filter()->unique('id')
            ->mapWithKeys(fn (Employee $employee) => [$employee->id => $this->allowanceFor($employee)])
            ->all();
    }

    public function mine(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'business.employee_has_no_account');
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2000,3000']]);

        return response()->json($this->ledgerPayload($employee, $data['year'] ?? now()->year));
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
        abort_if($start->lt(now($this->company->timezone())->startOfDay()), 422, 'business.retroactive_leave_request_not_allowed');
        abort_if($employee->join_date && $start->lt($employee->join_date->copy()->startOfDay()), 422, 'business.leave_before_employment');

        $dates = collect($this->ledger->rangeDates($start->toDateString(), $end->toDateString()));
        abort_if($dates->isEmpty(), 422, 'business.leave_range_contains_no_workdays');

        $created = DB::transaction(function () use ($employee, $request, $dates, $data, $start) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $existing = $employee->leaveRequests()->whereIn('date', $dates->map->toDateString())->pluck('date');
            if ($existing->isNotEmpty()) {
                throw ValidationException::withMessages(['start_date' => ['business.leave_request_date_exists']]);
            }

            return $dates->map(fn (Carbon $date) => $employee->leaveRequests()->create([
                'date' => $date->toDateString(),
                'year' => $start->year,
                'reason' => $data['reason'],
                'duration_days' => $dates->count() === 1 ? ($data['duration_days'] ?? 1) : 1,
                'requested_by' => $request->user()->id,
                'is_granted' => false,
            ]));
        });

        return response()->json([
            'success' => true,
            'data' => LeaveRequestResource::collection($created)->resolve(),
        ], 201);
    }

    /**
     * Approve one or more requests. The employee's per-employee allowance
     * decides how much of each day is an off day; the surplus is absent.
     * `force_off_day` preserves the existing explicit exception (with a
     * written justification) for an over-allowance day.
     */
    public function grant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:leaves,id'],
            'force_off_day' => ['sometimes', 'boolean'],
            'approval_note' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->boolean('force_off_day'))],
        ]);

        $exceeded = 0;
        $rows = DB::transaction(function () use ($data, $request, &$exceeded) {
            $employeeIds = LeaveRequest::whereIn('id', $data['ids'])
                ->pluck('employee_id')->unique()->sort()->values();
            Employee::whereIn('id', $employeeIds)->orderBy('id')->lockForUpdate()->get();
            $lockedRows = LeaveRequest::with(['employee'])
                ->whereIn('employee_id', $employeeIds)
                ->orderBy('employee_id')->orderBy('year')->orderBy('date')->orderBy('id')
                ->lockForUpdate()->get();
            $rows = $lockedRows->whereIn('id', $data['ids'])->sortBy('date')->values();
            if ($rows->count() !== count($data['ids'])) {
                throw ValidationException::withMessages(['ids' => ['business.request_rejected']]);
            }

            $usage = $lockedRows
                ->where('is_granted', true)
                ->groupBy(fn (LeaveRequest $row) => "{$row->employee_id}:{$row->year}")
                ->map(fn ($group) => (float) $group->sum('off_day_days'));

            $forceOffDay = $request->boolean('force_off_day');

            foreach ($rows as $row) {
                if ($row->is_granted) {
                    continue;
                }
                if ($row->date->isFriday()) {
                    throw ValidationException::withMessages(['ids' => ['business.leave_date_is_weekly_day_off']]);
                }
                if ($row->employee?->join_date && $row->date->lt($row->employee->join_date)) {
                    throw ValidationException::withMessages(['ids' => ['business.leave_before_employment']]);
                }

                $usageKey = "{$row->employee_id}:{$row->year}";
                $used = (float) $usage->get($usageKey, 0);
                $allowance = $this->allowanceFor($row->employee);
                $duration = min(1, max(0, (float) $row->duration_days));
                $available = max(0, $allowance - $used);
                $offDays = $forceOffDay ? $duration : min($duration, $available);
                if ($offDays < $duration) {
                    $exceeded++;
                }
                $usage->put($usageKey, $used + $offDays);

                $row->update([
                    'is_granted' => true,
                    'off_day_days' => $offDays,
                    'off_day_override' => $forceOffDay && $offDays >= $duration && $used + $duration > $allowance,
                    'approval_note' => $data['approval_note'] ?? null,
                    'granted_by' => $request->user()->id,
                    'granted_at' => now(),
                ]);
                $this->ledger->apply($row);
            }

            return LeaveRequest::with(['employee.position', 'grantor'])
                ->whereIn('id', $rows->pluck('id'))->orderBy('date')->get();
        });

        return response()->json([
            'success' => true,
            'data' => LeaveRequestResource::collection($rows)->resolve(),
            'exceeded' => $exceeded,
        ]);
    }

    public function index(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureSelectableEmployee($employee);
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2000,3000']]);

        return response()->json($this->ledgerPayload($employee, $data['year'] ?? now()->year));
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureSelectableEmployee($employee);

        // The staff ledger mirrors the self-service request dialog: a range of
        // dates can be recorded in one call. Fridays are skipped, not rejected.
        if ($request->filled('start_date') || $request->filled('end_date')) {
            return $this->storeRange($request, $employee);
        }

        $data = $this->validated($request, $employee);
        $year = (int) substr($data['date'], 0, 4);

        $leave = DB::transaction(function () use ($employee, $data, $year, $request) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $leave = LeaveRequest::withTrashed()
                ->where('employee_id', $employee->id)
                ->where('date', $data['date'])
                ->lockForUpdate()
                ->first();

            if ($leave) {
                if (! $leave->trashed()) {
                    throw ValidationException::withMessages(['date' => ['business.leave_request_date_exists']]);
                }
                $leave->fill([
                    'year' => $year,
                    'duration_days' => $data['duration_days'],
                    'reason' => $data['reason'],
                    'is_granted' => false,
                    'off_day_days' => 0,
                    'off_day_override' => false,
                    'approval_note' => null,
                    'requested_by' => $request->user()->id,
                    'granted_by' => null,
                    'granted_at' => null,
                ]);
                $leave->restore();
            } else {
                $leave = LeaveRequest::create([
                    'employee_id' => $employee->id,
                    'date' => $data['date'],
                    'year' => $year,
                    'duration_days' => $data['duration_days'],
                    'reason' => $data['reason'],
                    'requested_by' => $request->user()->id,
                    'is_granted' => false,
                ]);
            }

            return $leave;
        });

        return response()->json([
            'success' => true,
            'data' => new LeaveRequestResource($leave->fresh(['employee.position'])),
        ], 201);
    }

    public function update(Request $request, Employee $employee, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->ensureSelectableEmployee($employee);
        abort_unless((int) $leaveRequest->employee_id === (int) $employee->id, 404);
        $data = $this->validated($request, $employee, $leaveRequest);
        $year = (int) substr($data['date'], 0, 4);

        DB::transaction(function () use ($employee, $leaveRequest, $data, $year) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            $previousDate = $locked->date->format('Y-m-d');
            $locked->update([
                'date' => $data['date'],
                'year' => $year,
                'duration_days' => $data['duration_days'],
                'reason' => $data['reason'],
                'is_granted' => false,
                'off_day_days' => 0,
                'off_day_override' => false,
                'approval_note' => null,
                'granted_by' => null,
                'granted_at' => null,
            ]);
            $this->ledger->release($locked, $previousDate);
            $this->ledger->recalculate($locked);
        });

        return response()->json([
            'success' => true,
            'data' => new LeaveRequestResource($leaveRequest->fresh(['employee.position'])),
        ]);
    }

    public function destroy(Employee $employee, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->ensureSelectableEmployee($employee);
        abort_unless((int) $leaveRequest->employee_id === (int) $employee->id, 404);

        DB::transaction(function () use ($employee, $leaveRequest) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            $this->ledger->release($locked);
            $locked->delete();
            $this->ledger->recalculate($locked);
        });

        return response()->json(['success' => true]);
    }

    private function storeRange(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'duration_days' => ['nullable', Rule::in([0.5, 1])],
        ]);
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        abort_if($start->year !== $end->year, 422, 'business.request_rejected');
        abort_if($employee->join_date && $start->lt($employee->join_date->copy()->startOfDay()), 422, 'business.leave_before_employment');

        $dates = collect($this->ledger->rangeDates($start->toDateString(), $end->toDateString()));
        abort_if($dates->isEmpty(), 422, 'business.leave_range_contains_no_workdays');

        $created = DB::transaction(function () use ($employee, $dates, $data, $start) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $existing = $employee->leaveRequests()->whereIn('date', $dates->map->toDateString())->pluck('date');
            if ($existing->isNotEmpty()) {
                throw ValidationException::withMessages(['start_date' => ['business.leave_request_date_exists']]);
            }

            return $dates->map(fn (Carbon $date) => $employee->leaveRequests()->create([
                'date' => $date->toDateString(),
                'year' => $start->year,
                'reason' => $data['reason'],
                'duration_days' => $dates->count() === 1 ? ($data['duration_days'] ?? 1) : 1,
                'is_granted' => false,
            ]));
        });

        return response()->json([
            'success' => true,
            'data' => LeaveRequestResource::collection($created)->resolve(),
        ], 201);
    }

    /** @return array{date: string, duration_days: float, reason: string} */
    private function validated(Request $request, Employee $employee, ?LeaveRequest $ignore = null): array
    {
        return $request->validate([
            'date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Carbon::parse($value)->isFriday()) {
                        $fail('business.leave_date_is_weekly_day_off');
                    }
                },
                function (string $attribute, mixed $value, \Closure $fail) use ($employee): void {
                    if ($employee->join_date && Carbon::parse($value)->lt($employee->join_date)) {
                        $fail('business.leave_before_employment');
                    }
                },
                Rule::unique('leaves', 'date')
                    ->where('employee_id', $employee->id)
                    ->withoutTrashed()
                    ->ignore($ignore?->id),
            ],
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

    /** @return array{success: bool, data: mixed, allowance: float, used: float, remaining: float, pending: int, year: int} */
    private function ledgerPayload(Employee $employee, int $year): array
    {
        $rows = $employee->leaveRequests()->where('year', $year)->latest('date')->get();
        $allowance = $this->allowanceFor($employee);
        $used = (float) $rows->where('is_granted', true)->sum('off_day_days');

        return [
            'success' => true,
            'data' => LeaveRequestResource::collection($rows)->resolve(),
            'allowance' => $allowance,
            'used' => $used,
            'remaining' => max(0, $allowance - $used),
            'pending' => $rows->where('is_granted', false)->count(),
            'year' => $year,
        ];
    }

    /**
     * The employee's own allowance is the accountable figure. The company-wide
     * setting is only the catalogue value copied into a new employee profile.
     */
    private function allowanceFor(?Employee $employee): float
    {
        // Only the employee's own value governs approvals. The company-wide
        // setting is a creation-time default, not a runtime fallback.
        return (float) ($employee?->off_day_allowance_days ?? 0);
    }

    private function defaultAllowance(): float
    {
        return (float) (Setting::latest('id')->value('off_day_allowance_days_per_year') ?? 0);
    }
}
