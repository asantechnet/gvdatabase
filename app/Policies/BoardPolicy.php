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
}
