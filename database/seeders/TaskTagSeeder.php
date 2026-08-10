<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\Tag;

class TaskTagSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::all();
        $tags = Tag::all();

        foreach ($tasks as $task) {
            $task->tags()->attach(
                $tags->random(rand(0, 3))->pluck('id')->toArray()
            );
        }
    }
}
