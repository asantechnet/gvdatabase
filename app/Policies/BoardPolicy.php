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
        // The owner manages their own private personal board directly.
        if ($board->isPersonal()) {
            return $this->ownsPersonalBoard($user, $board);
        }

        return $this->view($user, $board) && (
            $user->hasPermission('edit_all_boards')
            || ($user->hasPermission('edit_assigned_boards') && TaskAccess::isBoardMember($user, $board))
            // A supervisor editing their team's board must hold the manage
            // scope; a read-only senior counselor does not.
            || ($user->hasPermission('edit_assigned_boards') && TaskAccess::isTeamBoardMember($user, $board))
        );
    }

    public function delete(User $user, Board $board): bool
    {
        if ($board->isPersonal()) {
            return $this->ownsPersonalBoard($user, $board);
        }

        return $this->view($user, $board) && (
            $user->hasPermission('delete_all_boards')
            || ($user->hasPermission('delete_assigned_boards') && TaskAccess::isBoardMember($user, $board))
            || ($user->hasPermission('delete_assigned_boards') && TaskAccess::isTeamBoardMember($user, $board))
        );
    }

    public function reorder(User $user): bool
    {
        return $user->hasPermission('reorder_boards');
    }

    public function assignMembers(User $user, Board $board): bool
    {
        // Personal boards have no membership to manage; ownership is the only
        // grant.
        if ($board->isPersonal()) {
            return false;
        }

        return $user->hasPermission('assign_board_members') && $this->update($user, $board);
    }

    private function ownsPersonalBoard(User $user, Board $board): bool
    {
        return (int) $board->user_id === (int) $user->id;
    }
}
