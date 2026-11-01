<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Support\ApiMessage;
use App\Support\RoleHierarchy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Departments are the firm's groups. Each one names the employee who manages
 * it; that employee (and anyone above them in the supervisor chain) sees the
 * group's records through the employee scope.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $departments = Department::query()
            ->with(['manager:id,name'])
            ->withCount('employees')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => $this->present($department));

        return response()->json(['success' => true, 'data' => $departments]);
    }

    /**
     * Employees who may actually be named as a department manager: active,
     * linked to an active account, and holding a role that grants supervisory
     * visibility. An existing department limits the list to candidates who
     * outrank every current member, so the picker and the save guard agree.
     */
    public function managerOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
        ]);

        $hierarchy = app(RoleHierarchy::class);
        $memberRanks = collect();

        if (! empty($data['department_id'])) {
            $department = Department::query()->findOrFail($data['department_id']);
            $memberRanks = $department->employees()
                ->with('user.roles')
                ->get()
                ->map(fn (Employee $member) => $this->rankForEmployee($member, $hierarchy));
        }

        $options = $this->eligibleManagerQuery()
            ->with(['user.roles', 'departmentRecord:id,name'])
            ->orderBy('name')
            ->get()
            ->filter(function (Employee $employee) use ($memberRanks, $hierarchy): bool {
                if ($memberRanks->isEmpty()) {
                    return true;
                }

                $rank = $this->rankForEmployee($employee, $hierarchy);

                return $memberRanks->every(fn (int $memberRank) => $rank > $memberRank);
            })
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employeeId' => $employee->id,
                'rank' => $this->rankForEmployee($employee, $hierarchy),
                'departmentId' => $employee->department_id,
                'departmentName' => $employee->departmentRecord?->name,
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $options]);
    }

    public function store(Request $request): JsonResponse
    {
        $department = Department::create($this->validated($request));

        return response()->json([
            'success' => true,
            'data' => $this->present($department->load(['manager:id,name'])->loadCount('employees')),
        ], 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $department->update($this->validated($request, $department));

        return response()->json([
            'success' => true,
            'data' => $this->present($department->fresh()->load(['manager:id,name'])->loadCount('employees')),
        ]);
    }

    public function destroy(Department $department): JsonResponse
    {
        $blocking = $department->employees()->orderBy('name')->get(['id', 'name']);

        if ($blocking->isNotEmpty()) {
            // The count and names let the dialog explain the refusal instead
            // of repeating a generic message. The code/localized message keep
            // the one-toast contract and the audit trail intact.
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
        $data = $request->validate([
            'name' => [$department ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department?->id)],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('departments', 'code')->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('manager_employee_id', $data) && $data['manager_employee_id']) {
            // Reuse the picker's query so the UI and the guard can never drift.
            $manager = $this->eligibleManagerQuery()
                ->with('user.roles')
                ->find($data['manager_employee_id']);

            if (! $manager) {
                throw ValidationException::withMessages([
                    'manager_id' => ['business.department_manager_not_supervisory'],
                ]);
            }

            // Changing a department head must not retroactively make a peer
            // or junior employee the supervisor of existing members.
            if ($department) {
                $hierarchy = app(RoleHierarchy::class);
                $managerRank = $this->rankForEmployee($manager, $hierarchy);

                $members = $department->employees()
                    ->with('user.roles')
                    ->where('id', '!=', $manager->id)
                    ->get();

                foreach ($members as $member) {
                    if ($managerRank <= $this->rankForEmployee($member, $hierarchy)) {
                        throw ValidationException::withMessages([
                            'manager_id' => ['business.department_manager_rank_invalid'],
                        ]);
                    }
                }
            }
        }

        return $data;
    }

    /** The single eligibility rule shared by the picker and the save guard. */
    private function eligibleManagerQuery(): Builder
    {
        return Employee::query()
            ->active()
            ->whereNotNull('user_id')
            ->whereHas('user', function (Builder $query) {
                $query->where('acc_status', 'active')
                    // Super admins hold every permission at runtime but may not
                    // have an explicit role_permissions row, exactly like
                    // User::hasPermission().
                    ->where(function (Builder $query) {
                        $query->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('name', 'view_subordinate_records'))
                            ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', 'super_admin'));
                    });
            });
    }

    /**
     * Rank from eager-loaded roles so a long candidate list never triggers a
     * per-row role query. Falls back to RoleHierarchy for unloaded users.
     */
    private function rankForEmployee(Employee $employee, RoleHierarchy $hierarchy): int
    {
        $user = $employee->user;

        if (! $user) {
            return RoleHierarchy::LEVELS['staff'];
        }

        if (! $user->relationLoaded('roles')) {
            return $hierarchy->rank($user);
        }

        return (int) $user->roles->max(
            fn ($role) => (int) ($role->level ?: $hierarchy->defaultLevel($role->name)),
        );
    }

    /** @return array<string, mixed> */
    private function present(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'description' => $department->description,
            'is_active' => (bool) $department->is_active,
            'manager_employee_id' => $department->manager_employee_id,
            'manager_name' => $department->manager?->name,
            'employees_count' => (int) ($department->employees_count ?? $department->employees()->count()),
        ];
    }
}
