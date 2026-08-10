<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $tasks = Task::all();

        foreach ($tasks as $task) {
            if (rand(0, 1)) {
                Comment::factory()
                    ->count(rand(1, 5))
                    ->create([
                        'task_id' => $task->id,
                        'user_id' => $users->random()->id,
                    ]);
            }
        }
    }
}
