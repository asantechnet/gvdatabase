<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;

/**
 * Resolves who a user may see and change through the org hierarchy.
 *
 * A user's group is made of three sets, unioned:
 *  1. employees beneath them in the supervisor chain (`employees.supervisor_id`);
 *  2. everyone in a department they manage (`departments.manager_employee_id`);
 *  3. everyone beneath those department members.
 *
 * Reading the group needs `view_subordinate_records`; changing it needs
 * `manage_subordinate_records`. A user can always act on records whose owner
 * is themselves, exactly as before. Organisation-wide access is expressed by
 * the existing `*_all_*` permissions and is checked by callers before this
 * class is consulted.
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

        $root = $this->ownEmployeeId($user);
        if ($root === null) {
            return $this->teamCache[$user->id] = [];
        }

        $ids = $this->descendants($root);
        foreach ($this->managedDepartmentMembers($user, $root) as $memberId) {
            $ids[] = $memberId;
            $ids = [...$ids, ...$this->descendants($memberId)];
        }

        return $this->teamCache[$user->id] = array_values(array_unique($ids));
    }

    /**
     * Every employee id the user may reach with their scope permissions.
     *
     * @return array<int, int>
     */
    public function visibleEmployeeIds(User $user): array
    {
        $own = $this->ownEmployeeId($user);
        $ids = $own !== null ? [$own] : [];

        if ($user->hasPermission('view_subordinate_records')) {
            $ids = [...$ids, ...$this->teamEmployeeIds($user)];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Every employee whose employee-owned records the user may change.
     * This is intentionally separate from `visibleEmployeeIds`: a senior
     * counselor may inspect a counselor's work without being able to alter
     * it.
     *
     * @return array<int, int>
     */
    public function manageableEmployeeIds(User $user): array
    {
        $own = $this->ownEmployeeId($user);
        $ids = $own !== null ? [$own] : [];

        if ($user->hasPermission('manage_subordinate_records')) {
            $ids = [...$ids, ...$this->teamEmployeeIds($user)];
        }

        return array_values(array_unique($ids));
    }

    /**
     * User ids linked to employees in visible scope, plus the actor.
     *
     * @return array<int, int>
     */
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

    /**
     * User ids linked to employees the user supervises (never the actor).
     *
     * @return array<int, int>
     */
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

    /** @return array<int, int> */
    private function descendants(int $rootId): array
    {
        $collected = [];
        $frontier = [$rootId];

        while ($frontier !== []) {
            $children = Employee::query()
                ->whereIn('supervisor_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $rootId || in_array($id, $collected, true))
                ->unique()
                ->values()
                ->all();

            if ($children === []) {
                break;
            }

            $collected = [...$collected, ...$children];
            $frontier = $children;
        }

        return $collected;
    }

    /** @return array<int, int> */
    private function managedDepartmentMembers(User $user, int $rootId): array
    {
        $departmentIds = Department::query()
            ->where('manager_employee_id', $rootId)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($departmentIds === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('department_id', $departmentIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
