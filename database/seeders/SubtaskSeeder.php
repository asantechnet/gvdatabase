<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;

class SubtaskSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::with('users:id')->get();
        $fallbackUserId = User::query()->orderBy('id')->value('id')
            ?? User::factory()->create()->id;

        foreach ($tasks as $task) {
            $ownerId = $task->users->isNotEmpty()
                ? $task->users->random()->id
                : $fallbackUserId;

            if (rand(0, 1)) {
                Subtask::factory()
                    ->count(rand(1, 4))
                    ->create([
                        'task_id' => $task->id,
                        'user_id' => $ownerId,
                        'category' => 'General',
                        'priority' => $task->priority ?? 'medium',
                    ]);
            }
        }
    }
}
