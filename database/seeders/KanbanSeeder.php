<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\User;
use App\Support\ServiceBoard;
use Illuminate\Database\Seeder;

class KanbanSeeder extends Seeder
{
    /**
     * Populate board membership from the real workforce.
     *
     * The working boards themselves are owned by {@see BoardSeeder} and each
     * task's assignees are attached to its board by {@see TaskUserSeeder}.
     * This seeder only guarantees the organisational baseline: the operations
     * manager is a member of every shared board, and every staff account owns
     * its own private Personal board.
     */
    public function run(): void
    {
        $managerIds = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['manager', 'team_lead']))
            ->pluck('id')
            ->all();

        foreach (Board::query()->whereNull('user_id')->get() as $board) {
            if ($managerIds !== []) {
                $board->users()->syncWithoutDetaching($managerIds);
            }
        }

        // Personal boards are private per user; ownership, not membership, is
        // the access grant, so there is no shared Personal board to join.
        User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['applicant']))
            ->get()
            ->each(fn (User $user) => ServiceBoard::personalFor($user));
    }
}
