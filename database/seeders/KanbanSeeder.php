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
     * manager is a member of every board and every staff account can reach the
     * Personal board, so the Kanban surface is never empty after a fresh seed.
     */
    public function run(): void
    {
        $managerIds = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['manager', 'team_lead']))
            ->pluck('id')
            ->all();

        foreach (Board::query()->get() as $board) {
            if ($managerIds !== []) {
                $board->users()->syncWithoutDetaching($managerIds);
            }
        }

        $personal = ServiceBoard::firstOrCreateFor(ServiceBoard::PERSONAL);
        $staffIds = User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['applicant']))
            ->pluck('id')
            ->all();
        if ($staffIds !== []) {
            $personal->users()->syncWithoutDetaching($staffIds);
        }
    }
}
