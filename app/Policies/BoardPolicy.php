<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;
use App\Support\TaskAccess;

class BoardPolicy
{
    public function view(User $user, Board $board): bool
    {
        return TaskAccess::canViewBoard($user, $board);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_boards');
    }

    public function update(User $user, Board $board): bool
    {
        return $this->view($user, $board) && (
            $user->hasPermission('edit_all_boards')
            || ($user->hasPermission('edit_assigned_boards') && TaskAccess::isBoardMember($user, $board))
        );
    }

    public function delete(User $user, Board $board): bool
    {
        return $this->view($user, $board) && (
            $user->hasPermission('delete_all_boards')
            || ($user->hasPermission('delete_assigned_boards') && TaskAccess::isBoardMember($user, $board))
        );
    }

    public function reorder(User $user): bool
    {
        return $user->hasPermission('reorder_boards');
    }

    public function assignMembers(User $user, Board $board): bool
    {
        return $user->hasPermission('assign_board_members') && $this->update($user, $board);
    }
}
