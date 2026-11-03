<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single writer for "who belongs to this department".
 *
 * The department update endpoint sends the complete member set. This service
 * compares it with the current set, refuses the cases the UI must explain
 * (removing the manager, adding an inactive employee, adding someone the
 * manager does not outrank) before writing anything, then delegates every
 * membership write to `Employee::syncDepartments` so the primary-department
 * projection stays in one place.
 */
class DepartmentMembership
{
    public function __construct(private readonly RoleHierarchy $hierarchy) {}

    /**
     * @param  array<int, int|string>  $employeeIds  the complete member set
     * @return array{attached: int, detached: int}
     */
    public function sync(Department $department, array $employeeIds): array
    {
        $submitted = collect($employeeIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $current = $department->employees()
            ->pluck('employees.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $added = array_values(array_diff($submitted, $current));
        $removed = array_values(array_diff($current, $submitted));

        $managerId = $department->manager_employee_id !== null
            ? (int) $department->manager_employee_id
            : null;

        // The current manager cannot be removed from the member set in the
        // same save that would leave the department without a head; the UI
        // disables the action, this guard makes the API refuse it too.
        if ($managerId !== null && in_array($managerId, $removed, true)) {
            throw ValidationException::withMessages([
                'employee_ids' => ['business.department_manager_cannot_be_removed'],
            ]);
        }

        if ($added !== []) {
            $employees = Employee::query()
                ->whereIn('id', $added)
                ->with(['user.roles', 'departments'])
                ->get()
                ->keyBy('id');

            $manager = $managerId !== null
                ? Employee::query()->with(['user.roles'])->find($managerId)
                : null;

            foreach ($added as $id) {
                $employee = $employees->get($id);

                if (! $employee) {
                    continue;
                }

                $index = array_search($id, $submitted, true);

                if ($employee->status !== Employee::STATUS_ACTIVE) {
                    throw ValidationException::withMessages([
                        "employee_ids.{$index}" => ['business.employee_inactive'],
                    ]);
                }

                // A manager who is also a member does not have to outrank
                // themselves; every other addition must be below them.
                if ($manager && (int) $manager->id !== (int) $employee->id
                    && $this->rankFor($manager) <= $this->rankFor($employee)) {
                    throw ValidationException::withMessages([
                        "employee_ids.{$index}" => ['business.department_member_rank_invalid'],
                    ]);
                }
            }
        }

        $attached = 0;
        $detached = 0;

        DB::transaction(function () use ($department, $added, $removed, &$attached, &$detached): void {
            foreach ($removed as $id) {
                $employee = Employee::query()->with('departments')->find($id);

                if (! $employee) {
                    continue;
                }

                $ids = $employee->departments
                    ->pluck('id')
                    ->map(fn ($departmentId) => (int) $departmentId)
                    ->reject(fn (int $departmentId) => $departmentId === (int) $department->id)
                    ->values()
                    ->all();

                // Null primary lets the writer promote the oldest remaining
                // membership, or clear the projection when none remain.
                $employee->syncDepartments($ids, null);
                $detached++;
            }

            foreach ($added as $id) {
                $employee = Employee::query()->with('departments')->find($id);

                if (! $employee) {
                    continue;
                }

                $ids = $employee->departments
                    ->pluck('id')
                    ->map(fn ($departmentId) => (int) $departmentId)
                    ->push((int) $department->id)
                    ->unique()
                    ->values()
                    ->all();

                $primary = $employee->department_id !== null ? (int) $employee->department_id : null;

                if ($primary !== null && ! in_array($primary, $ids, true)) {
                    $primary = null;
                }

                $employee->syncDepartments($ids, $primary);
                $attached++;
            }
        });

        return ['attached' => $attached, 'detached' => $detached];
    }

    /**
     * Role seniority from eager-loaded roles so a large addition never runs a
     * per-employee query. Employees without an account use the staff baseline,
     * matching `RoleHierarchy::canSupervise`.
     */
    private function rankFor(Employee $employee): int
    {
        $user = $employee->user;

        if (! $user) {
            return RoleHierarchy::LEVELS['staff'];
        }

        if (! $user->relationLoaded('roles')) {
            return $this->hierarchy->rank($user);
        }

        $rank = $user->roles->max(
            fn ($role) => (int) ($role->level ?: $this->hierarchy->defaultLevel($role->name)),
        );

        return (int) $rank;
    }
}
