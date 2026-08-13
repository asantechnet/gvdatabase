<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\User;

class TaskUserSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::all();
        $users = User::all();

        // Only proceed if we have both tasks and users
        if ($tasks->isNotEmpty() && $users->isNotEmpty()) {
            $maxUsersToAttach = min(3, $users->count());

            foreach ($tasks as $task) {
                $numUsers = rand(1, $maxUsersToAttach);
                $selectedUsers = $users->random(min($numUsers, $users->count()))->pluck('id')->toArray();
                $task->users()->attach($selectedUsers);
                $task->board->users()->syncWithoutDetaching($selectedUsers);
            }
        }
    }
}
