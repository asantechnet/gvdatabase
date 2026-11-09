<?php

namespace App\Support;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;

/**
 * Centralizes the relationship checks behind task-management policies.
 *
 * Personal boards are private. Department boards are readable by their
 * members and writable by a manager holding `manage_department_tasks`.
 * Service boards keep their contract-category permission.
 */
class TaskAccess
{
    public static function canViewBoard(User $user, Board $board): bool
    {
        if ($board->isPersonal()) {
            return (int) $board->user_id === (int) $user->id;
        }

        if ($board->department_id !== null) {
            if ($user->hasPermission('view_all_boards')) {
                return true;
            }

            $employeeId = app(EmployeeScope::class)->ownEmployeeId($user);
            if ($employeeId === null) {
                return false;
            }

            return $board->department()
                ->where(function ($query) use ($employeeId): void {
                    $query->whereHas('employees', fn ($members) => $members->whereKey($employeeId))
                        ->orWhereHas('managers', fn ($managers) => $managers->whereKey($employeeId));
                })
                ->exists();
        }

        $service = ServiceBoard::serviceForTitle($board->title);
        if ($service !== null) {
            return $user->hasPermission(BuiltInService::contractPermission($service));
        }

        if ($user->hasPermission('view_all_boards')) {
            return true;
        }

        if (! $user->hasPermission('view_assigned_boards')) {
            return false;
        }

        return $board->users()
            ->whereIn('users.id', app(EmployeeScope::class)->visibleUserIds($user))
            ->exists();
    }

    public static function canManageDepartmentBoard(User $user, ?Board $board): bool
    {
        if (! $board || $board->department_id === null || ! $user->hasPermission('manage_department_tasks')) {
            return false;
        }

        $employeeId = app(EmployeeScope::class)->ownEmployeeId($user);
        if ($employeeId === null) {
            return false;
        }

        return $board->department()
            ->where(function ($query) use ($employeeId): void {
                $query->where('manager_employee_id', $employeeId)
                    ->orWhereHas('managers', fn ($managers) => $managers->whereKey($employeeId));
            })
            ->exists();
    }

    public static function canManagePersonalBoard(User $user, Board $board): bool
    {
        return $board->isPersonal()
            && (int) $board->user_id === (int) $user->id
            && $user->hasPermission('create_personal_tasks');
    }

    public static function canManagePersonalTask(User $user, Task $task): bool
    {
        return self::canManagePersonalBoard($user, $task->board);
    }

    public static function canViewTask(User $user, Task $task): bool
    {
        if (! self::canViewBoard($user, $task->board)) {
            return false;
        }

        if (self::canManagePersonalTask($user, $task)) {
            return true;
        }

        if (self::canManageDepartmentBoard($user, $task->board)) {
            return true;
        }

        if ($user->hasPermission('view_all_tasks')) {
            return true;
        }

        return $user->hasPermission('view_assigned_tasks')
            && $task->users()
                ->whereIn('users.id', app(EmployeeScope::class)->visibleUserIds($user))
                ->exists();
    }

    public static function isBoardMember(User $user, Board $board): bool
    {
        return $board->users()->whereKey($user->id)->exists();
    }

    public static function isTaskAssignee(User $user, Task $task): bool
    {
        return $task->users()->whereKey($user->id)->exists();
    }

    public static function isTeamBoardMember(User $user, Board $board): bool
    {
        if (! $user->hasPermission('manage_subordinate_records')) {
            return false;
        }

        $teamUserIds = app(EmployeeScope::class)->teamUserIds($user);

        return $teamUserIds !== [] && $board->users()->whereIn('users.id', $teamUserIds)->exists();
    }

    public static function isTeamTaskAssignee(User $user, Task $task): bool
    {
        if (! $user->hasPermission('manage_subordinate_records')) {
            return false;
        }

        $teamUserIds = app(EmployeeScope::class)->teamUserIds($user);

        return $teamUserIds !== [] && $task->users()->whereIn('users.id', $teamUserIds)->exists();
    }

    public static function isDepartmentTask(User $user, Task $task): bool
    {
        return self::canManageDepartmentBoard($user, $task->board);
    }
}
