<?php

namespace App\Support;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;

/** Centralizes the relationship checks behind task-management policies. */
class TaskAccess
{
    public static function canViewBoard(User $user, Board $board): bool
    {
        if ($user->hasPermission('view_all_boards')) {
            return true;
        }

        return $user->hasPermission('view_assigned_boards')
            && $board->users()->whereKey($user->id)->exists();
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
            && $task->users()->whereKey($user->id)->exists();
    }

    public static function isBoardMember(User $user, Board $board): bool
    {
        return $board->users()->whereKey($user->id)->exists();
    }

    public static function isTaskAssignee(User $user, Task $task): bool
    {
        return $task->users()->whereKey($user->id)->exists();
    }
}
