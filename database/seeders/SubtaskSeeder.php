<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subtask;
use App\Models\Task;

class SubtaskSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::all();

        foreach ($tasks as $task) {
            if (rand(0, 1)) {
                Subtask::factory()
                    ->count(rand(1, 4))
                    ->create([
                        'task_id' => $task->id,
                        'category' => 'General',
                        'priority' => $task->priority ?? 'medium',
                    ]);
            }
        }
    }
}
