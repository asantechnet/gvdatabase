<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Support\EmployeeScope;
use App\Support\TaskAccess;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return TaskAccess::canManagePersonalTask($user, $task)
            || TaskAccess::canViewTask($user, $task);
    }

    public function create(User $user, Board $board): bool
    {
        if (TaskAccess::canManagePersonalBoard($user, $board)) {
            return true;
        }

        if (TaskAccess::canManageDepartmentBoard($user, $board)) {
            return $user->hasPermission('update_kanban');
        }

        return $user->hasPermission('create_tasks')
            && $user->hasPermission('update_kanban')
            && TaskAccess::canViewBoard($user, $board);
    }

    public function update(User $user, Task $task): bool
    {
        if (TaskAccess::canManagePersonalTask($user, $task)) {
            return true;
        }

        if (! $this->view($user, $task) || ! $user->hasPermission('update_kanban')) {
            return false;
        }

        return TaskAccess::canManageDepartmentBoard($user, $task->board)
            || $user->hasPermission('edit_all_tasks')
            || ($user->hasPermission('edit_assigned_tasks') && TaskAccess::isTaskAssignee($user, $task))
            || ($user->hasPermission('edit_assigned_tasks') && TaskAccess::isTeamTaskAssignee($user, $task));
    }

    public function delete(User $user, Task $task): bool
    {
        if (TaskAccess::canManagePersonalTask($user, $task)) {
            return true;
        }

        if (! $this->view($user, $task) || ! $user->hasPermission('update_kanban')) {
            return false;
        }

        return TaskAccess::canManageDepartmentBoard($user, $task->board)
            || $user->hasPermission('delete_all_tasks')
            || ($user->hasPermission('delete_assigned_tasks') && TaskAccess::isTaskAssignee($user, $task))
            || ($user->hasPermission('delete_assigned_tasks') && TaskAccess::isTeamTaskAssignee($user, $task));
    }

    public function move(User $user, Task $task, Board $destination): bool
    {
        if (TaskAccess::canManagePersonalTask($user, $task) && TaskAccess::canManagePersonalBoard($user, $destination)) {
            return (int) $task->board_id === (int) $destination->id;
        }

        if (! $user->hasPermission('update_kanban')) {
            return false;
        }

        $canMove = TaskAccess::canManageDepartmentBoard($user, $task->board)
            || TaskAccess::canManageDepartmentBoard($user, $destination)
            || $user->hasPermission('move_all_tasks')
            || ($user->hasPermission('move_assigned_tasks') && TaskAccess::isTaskAssignee($user, $task))
            || ($user->hasPermission('move_assigned_tasks') && TaskAccess::isTeamTaskAssignee($user, $task));

        return $canMove && $this->view($user, $task) && TaskAccess::canViewBoard($user, $destination);
    }

    public function assignMembers(User $user, Task $task): bool
    {
        return ($user->hasPermission('assign_task_members')
            || TaskAccess::canManageDepartmentBoard($user, $task->board))
            && $this->update($user, $task);
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
