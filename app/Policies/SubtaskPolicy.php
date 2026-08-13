<?php

namespace App\Policies;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;

class SubtaskPolicy
{
    public function create(User $user, Task $task): bool
    {
        return $user->hasPermission('create_subtasks_on_assigned_tasks')
            && app(TaskPolicy::class)->view($user, $task);
    }

    public function update(User $user, Subtask $subtask): bool
    {
        return $user->hasPermission('edit_all_subtasks')
            || ($user->hasPermission('edit_own_subtasks') && $subtask->user_id === $user->id);
    }

    public function delete(User $user, Subtask $subtask): bool
    {
        return $user->hasPermission('delete_all_subtasks')
            || ($user->hasPermission('delete_own_subtasks') && $subtask->user_id === $user->id);
    }
}
