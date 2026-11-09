<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;

/**
 * Resolves who a user may see and change through department membership.
 *
 * A manager's group is everyone in the departments they manage. Reading the
 * group needs `view_subordinate_records`; changing it needs
 * `manage_subordinate_records`. A user can always act on their own records.
 */
class EmployeeScope
{
    /** @var array<int, array<int, int>> */
    private array $teamCache = [];

    /** @var array<int, int|null> */
    private array $ownCache = [];

    public function ownEmployeeId(User $user): ?int
    {
        if (array_key_exists($user->id, $this->ownCache)) {
            return $this->ownCache[$user->id];
        }

        $id = Employee::query()->where('user_id', $user->id)->value('id');

        return $this->ownCache[$user->id] = $id !== null ? (int) $id : null;
    }

    /** @return array<int, int> */
    public function teamEmployeeIds(User $user): array
    {
        if (isset($this->teamCache[$user->id])) {
            return $this->teamCache[$user->id];
        }

        return $this->teamCache[$user->id] = $this->managedDepartmentMemberIds($user);
    }

    /** @return array<int, int> */
    public function visibleEmployeeIds(User $user): array
    {
        $own = $this->ownEmployeeId($user);
        $ids = $own !== null ? [$own] : [];

        if ($user->hasPermission('view_subordinate_records')) {
            $ids = [...$ids, ...$this->teamEmployeeIds($user)];
        }

        return array_values(array_unique($ids));
    }

    /** @return array<int, int> */
    public function manageableEmployeeIds(User $user): array
    {
        $own = $this->ownEmployeeId($user);
        $ids = $own !== null ? [$own] : [];

        if ($user->hasPermission('manage_subordinate_records')) {
            $ids = [...$ids, ...$this->teamEmployeeIds($user)];
        }

        return array_values(array_unique($ids));
    }

    /** @return array<int, int> */
    public function visibleUserIds(User $user): array
    {
        $ids = Employee::query()
            ->whereIn('id', $this->visibleEmployeeIds($user))
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        $ids[] = $user->id;

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** @return array<int, int> */
    public function teamUserIds(User $user): array
    {
        $team = $this->teamEmployeeIds($user);
        if ($team === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('id', $team)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<int, int> */
    public function managedDepartmentIds(User $user): array
    {
        $root = $this->ownEmployeeId($user);
        if ($root === null) {
            return [];
        }

        return Department::query()
            ->where('is_active', true)
            ->where(function ($query) use ($root): void {
                $query->where('manager_employee_id', $root)
                    ->orWhereHas('managers', fn ($managers) => $managers->whereKey($root));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<int, int> */
    public function managedDepartmentMemberIds(User $user): array
    {
        $departmentIds = $this->managedDepartmentIds($user);
        if ($departmentIds === []) {
            return [];
        }

        return Employee::query()
            ->whereHas('departments', fn ($query) => $query->whereIn('departments.id', $departmentIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<int, int> */
    public function managedDepartmentUserIds(User $user): array
    {
        $memberIds = $this->managedDepartmentMemberIds($user);
        if ($memberIds === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('id', $memberIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isDepartmentManager(User $user): bool
    {
        return $this->managedDepartmentIds($user) !== [];
    }

    public function canViewOwnedBy(User $user, ?int $ownerEmployeeId): bool
    {
        if (! $ownerEmployeeId) {
            return false;
        }

        $own = $this->ownEmployeeId($user);
        if ($own !== null && $own === (int) $ownerEmployeeId) {
            return true;
        }

        return $user->hasPermission('view_subordinate_records')
            && in_array((int) $ownerEmployeeId, $this->teamEmployeeIds($user), true);
    }

    public function canManageOwnedBy(User $user, ?int $ownerEmployeeId): bool
    {
        if (! $ownerEmployeeId) {
            return false;
        }

        $own = $this->ownEmployeeId($user);
        if ($own !== null && $own === (int) $ownerEmployeeId) {
            return true;
        }

        return $user->hasPermission('manage_subordinate_records')
            && in_array((int) $ownerEmployeeId, $this->teamEmployeeIds($user), true);
    }

    public function managesEmployee(User $user, Employee|int $employee): bool
    {
        $id = $employee instanceof Employee ? (int) $employee->id : (int) $employee;

        return in_array($id, $this->teamEmployeeIds($user), true);
    }
}
