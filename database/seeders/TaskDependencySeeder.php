<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;

class TaskDependencySeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::all();

        foreach ($tasks as $task) {
            // Create 0-2 dependencies per task
            $dependencies = $tasks->where('id', '!=', $task->id)
                ->random(rand(0, 2))
                ->pluck('id');

            $task->dependencies()->syncWithoutDetaching($dependencies);
        }
    }
}
