<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;

class CommentPolicy
{
    public function create(User $user, Task $task): bool
    {
        return $user->hasPermission('create_comments_on_assigned_tasks')
            && app(TaskPolicy::class)->view($user, $task);
    }

    public function update(User $user, Comment $comment): bool
    {
        // Editing is personal: no role or blanket permission can edit someone
        // else's comment, even when that role can delete it.
        return $user->hasPermission('edit_own_comments') && $comment->user_id === $user->id;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->hasPermission('delete_all_comments')
            || ($user->hasPermission('delete_own_comments') && $comment->user_id === $user->id);
    }
}
