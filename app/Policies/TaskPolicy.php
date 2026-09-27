<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskAccess;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return TaskAccess::canViewTask($user, $task);
    }

    public function create(User $user, Board $board): bool
    {
        return $user->hasPermission('create_tasks') && TaskAccess::canViewBoard($user, $board);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->view($user, $task) && (
            $user->hasPermission('edit_all_tasks')
            || ($user->hasPermission('edit_assigned_tasks') && TaskAccess::isTaskAssignee($user, $task))
            // Managing a subordinate's task needs the manage scope, so a
            // view-only supervisor cannot change it.
            || ($user->hasPermission('edit_assigned_tasks') && TaskAccess::isTeamTaskAssignee($user, $task))
        );
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->view($user, $task) && (
            $user->hasPermission('delete_all_tasks')
            || ($user->hasPermission('delete_assigned_tasks') && TaskAccess::isTaskAssignee($user, $task))
            || ($user->hasPermission('delete_assigned_tasks') && TaskAccess::isTeamTaskAssignee($user, $task))
        );
    }

    public function move(User $user, Task $task, Board $destination): bool
    {
        $canMove = $user->hasPermission('move_all_tasks')
            || ($user->hasPermission('move_assigned_tasks') && TaskAccess::isTaskAssignee($user, $task))
            || ($user->hasPermission('move_assigned_tasks') && TaskAccess::isTeamTaskAssignee($user, $task));

        return $canMove && $this->view($user, $task) && TaskAccess::canViewBoard($user, $destination);
    }

    public function assignMembers(User $user, Task $task): bool
    {
        return $user->hasPermission('assign_task_members') && $this->update($user, $task);
    }

    public function manageDependencies(User $user, Task $task): bool
    {
        return $user->hasPermission('manage_task_dependencies') && $this->update($user, $task);
    }

    public function manageTags(User $user, Task $task): bool
    {
        return $user->hasPermission('manage_task_tags') && $this->update($user, $task);
    }
}
