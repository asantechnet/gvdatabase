<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Department;
use App\Models\Employee;
use App\Support\ApiMessage;
use App\Support\DepartmentMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Departments are the firm's groups. Each department may have several senior
 * managers; membership is flat and is what defines the team.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $withMembers = $request->boolean('with_members');

        $departments = Department::query()
            ->with(['manager:id,name', 'managers:id,name', 'board:id,department_id,title'])
            ->when($withMembers, fn (Builder $query) => $query->with([
                'employees' => fn ($members) => $members
                    ->with(['position:id,title', 'user.roles:id,name,is_senior'])
                    ->orderBy('employees.name'),
            ]))
            ->withCount('employees')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => $this->present($department));

        return response()->json(['success' => true, 'data' => $departments]);
    }

    /**
     * Active employees whose account holds a senior role. There is no rank
     * comparison: several seniors may manage the same department.
     */
    public function managerOptions(Request $request): JsonResponse
    {
        $request->validate([
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
        ]);

        $options = $this->eligibleManagerQuery()
            ->with(['user.roles:id,name,is_senior', 'departments:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employeeId' => $employee->id,
                'isSenior' => true,
                'departmentId' => $employee->departments->first()?->id,
                'departmentName' => $employee->departments->first()?->name,
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $options]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $managerIds = $this->managerIds($data);

        $department = DB::transaction(function () use ($data, $managerIds): Department {
            $department = Department::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $membership = app(DepartmentMembership::class);
            $membership->syncManagers($department, $managerIds);

            if (array_key_exists('employee_ids', $data) || $managerIds !== []) {
                $membership->sync($department, array_values(array_unique([
                    ...($data['employee_ids'] ?? []),
                    ...$managerIds,
                ])));
            }

            if ($data['create_task_board'] ?? true) {
                $this->createDepartmentBoard($department);
            }

            return $department;
        });

        return response()->json([
            'success' => true,
            'data' => $this->present($this->loadMembers($department)),
        ], 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $data = $this->validated($request, $department);

        DB::transaction(function () use ($request, $department, $data): void {
            $department->update([
                'name' => $data['name'] ?? $department->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $department->description,
                'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $department->is_active,
            ]);

            $membership = app(DepartmentMembership::class);

            if ($request->has('manager_employee_ids') || $request->has('manager_employee_id')) {
                $membership->syncManagers($department, $this->managerIds($data));
            }

            if ($request->has('employee_ids')) {
                $managerIds = $department->managers()->pluck('employees.id')
                    ->merge($department->manager_employee_id ? [(int) $department->manager_employee_id] : [])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $membership->sync($department, array_values(array_unique([
                    ...$data['employee_ids'],
                    ...$managerIds,
                ])));
            }

            if (($data['create_task_board'] ?? false) && ! $department->board()->exists()) {
                $this->createDepartmentBoard($department);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $this->present($this->loadMembers($department->fresh())),
        ]);
    }

    public function destroy(Department $department): JsonResponse
    {
        $blocking = $department->employees()
            ->orderBy('employees.name')
            ->get(['employees.id', 'employees.name']);

        if ($blocking->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'code' => 'business.department_has_employees',
                'message' => ApiMessage::forCode('business.department_has_employees'),
                'blocking_employee_count' => $blocking->count(),
                'blocking_employees' => $blocking->take(5)->pluck('name')->values(),
            ], 422);
        }

        $department->delete();

        return response()->json(['success' => true, 'message' => 'Department deleted successfully.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => [$department ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'manager_employee_ids' => ['sometimes', 'array', 'max:20'],
            'manager_employee_ids.*' => ['integer', 'distinct', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
            'create_task_board' => ['sometimes', 'boolean'],
            'employee_ids' => ['sometimes', 'array', 'max:500'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('employees', 'id')->whereNull('deleted_at')],
        ]);
    }

    /** @param array<string, mixed> $data @return array<int, int> */
    private function managerIds(array $data): array
    {
        return collect([
            ...($data['manager_employee_ids'] ?? []),
            $data['manager_employee_id'] ?? null,
        ])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function eligibleManagerQuery(): Builder
    {
        return Employee::query()
            ->active()
            ->whereNotNull('user_id')
            ->whereHas('user', function (Builder $query) {
                $query->where('acc_status', 'active')
                    ->where(function (Builder $query) {
                        $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'super_admin'))
                            ->orWhereHas('roles', fn (Builder $roles) => $roles->where('is_senior', true));
                    });
            });
    }

    private function createDepartmentBoard(Department $department): Board
    {
        return Board::query()->firstOrCreate(
            ['department_id' => $department->id],
            [
                'title' => "{$department->name} Tasks",
                'order' => (Board::query()->max('order') ?? -1) + 1,
            ],
        );
    }

    private function loadMembers(Department $department): Department
    {
        return $department
            ->load([
                'manager:id,name',
                'managers:id,name',
                'board:id,department_id,title',
                'employees' => fn ($members) => $members
                    ->with(['position:id,title', 'user.roles:id,name,is_senior'])
                    ->orderBy('employees.name'),
            ])
            ->loadCount('employees');
    }

    /** @return array<string, mixed> */
    private function present(Department $department): array
    {
        $managerIds = $department->relationLoaded('managers')
            ? $department->managers->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $department->managers()->pluck('employees.id')->map(fn ($id) => (int) $id)->all();

        if ($department->manager_employee_id && ! in_array((int) $department->manager_employee_id, $managerIds, true)) {
            array_unshift($managerIds, (int) $department->manager_employee_id);
        }

        $payload = [
            'id' => $department->id,
            'name' => $department->name,
            'description' => $department->description,
            'is_active' => (bool) $department->is_active,
            'manager_employee_id' => $department->manager_employee_id,
            'manager_name' => $department->manager?->name,
            'manager_employee_ids' => array_values(array_unique($managerIds)),
            'managers' => $department->relationLoaded('managers')
                ? $department->managers->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->name])->values()
                : [],
            'board_id' => $department->board?->id,
            'board_title' => $department->board?->title,
            'employees_count' => (int) ($department->employees_count ?? $department->employees()->count()),
        ];

        if ($department->relationLoaded('employees')) {
            $payload['members'] = $department->employees
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'position' => $employee->position?->title,
                    'role' => $employee->relationLoaded('user')
                        ? $employee->user?->roles->firstWhere('is_senior', true)?->name
                            ?? $employee->user?->roles->pluck('name')->first()
                        : null,
                    'status' => $employee->status,
                    'is_manager' => in_array((int) $employee->id, $managerIds, true),
                ])
                ->values();
        }

        return $payload;
    }
}
