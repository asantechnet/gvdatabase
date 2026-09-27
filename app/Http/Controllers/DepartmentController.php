<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Support\RoleHierarchy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        abort_if($department->employees()->exists(), 422, 'business.department_has_employees');

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
            $manager = Employee::query()->with('user.roles.permissions')->findOrFail($data['manager_employee_id']);
            // The manager must have a supervisory role. Individual members
            // are checked again when they are assigned to this department.
            abort_unless($manager->user && $manager->user->hasPermission('view_subordinate_records'), 422, 'business.department_manager_not_supervisory');

            // Changing a department head must not retroactively make a peer
            // or junior employee the supervisor of existing members.
            if ($department) {
                $hierarchy = app(RoleHierarchy::class);
                $department->employees()
                    ->with('user.roles')
                    ->where('id', '!=', $manager->id)
                    ->each(fn (Employee $member) => abort_unless(
                        $hierarchy->canSupervise($manager, $member),
                        422,
                        'business.department_manager_rank_invalid',
                    ));
            }
        }

        return $data;
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
