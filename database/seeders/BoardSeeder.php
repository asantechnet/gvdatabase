<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Board;

class BoardSeeder extends Seeder
{
    public function run(): void
    {
        Board::factory()
            ->count(5)
            ->sequence(
                ['title' => 'Backlog', 'order' => 0],
                ['title' => 'To Do', 'order' => 1],
                ['title' => 'In Progress', 'order' => 2],
                ['title' => 'Review', 'order' => 3],
                ['title' => 'Done', 'order' => 4]
            )
            ->create();
    }
}
