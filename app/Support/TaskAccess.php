<?php

namespace App\Support;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;

/**
 * Centralizes the relationship checks behind task-management policies.
 *
 * Viewing a board/task follows the user's employee scope (`view_subordinate_records`
 * reaches the team's work). Changing someone else's work additionally needs
 * `manage_subordinate_records`, which is what keeps a senior counselor's
 * supervision read-only.
 */
class TaskAccess
{
    public static function canViewBoard(User $user, Board $board): bool
    {
        // A personal board is private to its owner. Membership and even the
        // blanket `view_all_boards` grant never widens it, including for
        // super-admins.
        if ($board->isPersonal()) {
            return (int) $board->user_id === (int) $user->id;
        }

        if ($user->hasPermission('view_all_boards')) {
            return true;
        }

        if (! $user->hasPermission('view_assigned_boards')) {
            return false;
        }

        // visibleUserIds always contains the actor, so this also matches the
        // classic "member of the board" case for staff and viewers.
        return $board->users()
            ->whereIn('users.id', app(EmployeeScope::class)->visibleUserIds($user))
            ->exists();
    }

    public static function canViewTask(User $user, Task $task): bool
    {
        // A task is never visible outside a board the user can see. Task
        // assignment normally creates this board membership automatically;
        // this extra check also closes the gap if a manager later removes it.
        if (! self::canViewBoard($user, $task->board)) {
            return false;
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

    /** A supervisor with the manage scope over someone assigned to the board. */
    public static function isTeamBoardMember(User $user, Board $board): bool
    {
        if (! $user->hasPermission('manage_subordinate_records')) {
            return false;
        }

        $teamUserIds = app(EmployeeScope::class)->teamUserIds($user);

        return $teamUserIds !== [] && $board->users()->whereIn('users.id', $teamUserIds)->exists();
    }

    /** A supervisor with the manage scope over someone assigned to the task. */
    public static function isTeamTaskAssignee(User $user, Task $task): bool
    {
        if (! $user->hasPermission('manage_subordinate_records')) {
            return false;
        }

        $teamUserIds = app(EmployeeScope::class)->teamUserIds($user);

        return $teamUserIds !== [] && $task->users()->whereIn('users.id', $teamUserIds)->exists();
    }

    /**
     * A task that already involves a member of a department the user heads.
     * This is how a department manager reaches the work they are expected to
     * coordinate, without a supervisor-chain relationship.
     */
    public static function isDepartmentTask(User $user, Task $task): bool
    {
        $departmentUserIds = app(EmployeeScope::class)->managedDepartmentUserIds($user);

        return $departmentUserIds !== [] && $task->users()->whereIn('users.id', $departmentUserIds)->exists();
    }
}
