<?php

namespace Database\Seeders;

use App\Models\Board;
use Illuminate\Database\Seeder;

class TaskDependencySeeder extends Seeder
{
    /**
     * Dependencies only make sense between tasks on the same board, so each
     * task depends on the task that precedes it in board order.
     */
    public function run(): void
    {
        Board::query()->with(['tasks' => fn ($query) => $query->orderBy('order')->orderBy('id')])->get()
            ->each(function (Board $board): void {
                $previous = null;
                foreach ($board->tasks as $task) {
                    if ($previous !== null) {
                        $task->dependencies()->syncWithoutDetaching([$previous->id]);
                    }
                    $previous = $task;
                }
            });
    }
}
