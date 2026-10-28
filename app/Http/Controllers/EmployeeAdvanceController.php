<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\EmployeeAdvanceResource;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Services\EmployeeAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class EmployeeAdvanceController extends Controller
{
    use PaginatesLists;
    public function __construct(private readonly EmployeeAdvanceService $advances) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $query = EmployeeAdvance::with(['employee', 'currency', 'recorder']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search): void {
                $q->where('remarks', 'like', "%{$search}%")
                    ->orWhere('advance_number', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $dates = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', $request->filled('date_from') ? 'after_or_equal:date_from' : 'nullable'],
        ]);

        if (! empty($dates['date_from'])) {
            $query->whereDate('advance_date', '>=', $dates['date_from']);
        }
        if (! empty($dates['date_to'])) {
            $query->whereDate('advance_date', '<=', $dates['date_to']);
        }

        $query->orderByDesc('advance_date')->orderByDesc('id');

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true]
                + $this->paginatedPayload($request, $query, fn ($rows) => EmployeeAdvanceResource::collection($rows)->resolve()));
        }

        return EmployeeAdvanceResource::collection($query->get());
    }

    public function store(Request $request): EmployeeAdvanceResource
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureSelectableEmployee((int) $validated['employee_id']);

        $advance = $this->advances->record($validated, $request->user()?->id);

        return new EmployeeAdvanceResource($advance->load(['employee', 'currency', 'recorder']));
    }

    public function destroy(EmployeeAdvance $employeeAdvance): JsonResponse
    {
        $this->advances->remove($employeeAdvance);

        return response()->json(['message' => 'Salary advance deleted.']);
    }

    private function ensureSelectableEmployee(int $employeeId): void
    {
        if (! Employee::selectable()->find($employeeId)) {
            throw ValidationException::withMessages([
                'employee_id' => ['business.employee_not_selectable'],
            ]);
        }
    }
}
