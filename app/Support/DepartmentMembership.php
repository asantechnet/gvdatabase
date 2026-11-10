<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single writer for "who belongs to this department" and "who manages it".
 *
 * Memberships are a flat set. Managers are senior employees and must remain
 * members of the department they manage.
 */
class DepartmentMembership
{
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

        $current = $department->employees()->pluck('employees.id')->map(fn ($id) => (int) $id)->all();
        $added = array_values(array_diff($submitted, $current));
        $removed = array_values(array_diff($current, $submitted));

        $managerIds = $department->managers()->pluck('employees.id')
            ->merge($department->manager_employee_id ? [(int) $department->manager_employee_id] : [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (array_intersect($managerIds, $removed) !== []) {
            throw ValidationException::withMessages([
                'employee_ids' => ['business.department_manager_cannot_be_removed'],
            ]);
        }

        if ($added !== []) {
            $employees = Employee::query()->whereIn('id', $added)->get()->keyBy('id');

            foreach ($added as $id) {
                $index = array_search($id, $submitted, true);
                $employee = $employees->get($id);

                if ($employee && $employee->status !== Employee::STATUS_ACTIVE) {
                    throw ValidationException::withMessages([
                        "employee_ids.{$index}" => ['business.employee_inactive'],
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

                $employee->syncDepartments(
                    $employee->departments->pluck('id')
                        ->map(fn ($departmentId) => (int) $departmentId)
                        ->reject(fn (int $departmentId) => $departmentId === (int) $department->id)
                        ->values()
                        ->all(),
                );
                $detached++;
            }

            foreach ($added as $id) {
                $employee = Employee::query()->with('departments')->find($id);

                if (! $employee) {
                    continue;
                }

                $employee->syncDepartments(
                    $employee->departments->pluck('id')
                        ->map(fn ($departmentId) => (int) $departmentId)
                        ->push((int) $department->id)
                        ->unique()
                        ->values()
                        ->all(),
                );
                $attached++;
            }
        });

        return ['attached' => $attached, 'detached' => $detached];
    }

    /**
     * Replace the department's managers. Every manager must be a senior,
     * active employee; managers are always attached as members.
     *
     * @param  array<int, int|string>  $employeeIds
     * @return array<int, int>
     */
    public function syncManagers(Department $department, array $employeeIds): array
    {
        $ids = collect($employeeIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $employees = Employee::query()
            ->with(['user.roles'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $index => $id) {
            $employee = $employees->get($id);

            if (! $employee || $employee->status !== Employee::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    "manager_employee_ids.{$index}" => ['business.employee_inactive'],
                ]);
            }

            $isSenior = $employee->user?->isSuperAdmin()
                || $employee->user?->roles->contains(fn ($role) => $role->name === 'super_admin' || (bool) $role->is_senior);

            if (! $isSenior) {
                throw ValidationException::withMessages([
                    "manager_employee_ids.{$index}" => ['business.department_manager_not_supervisory'],
                ]);
            }
        }

        DB::transaction(function () use ($department, $ids, $employees): void {
            $department->managers()->sync($ids);
            $department->update(['manager_employee_id' => $ids[0] ?? null]);

            foreach ($employees as $employee) {
                $current = $employee->departments()->pluck('departments.id')->map(fn ($id) => (int) $id)->all();
                if (! in_array((int) $department->id, $current, true)) {
                    $employee->syncDepartments([...$current, (int) $department->id]);
                }
            }
        });

        return $ids;
    }
}
