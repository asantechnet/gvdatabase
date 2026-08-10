<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\Board;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $boards = Board::all();

        foreach ($boards as $board) {
            Task::factory()
                ->count(rand(3, 8))
                ->create([
                    'board_id' => $board->id,
                ]);
        }
    }
}
