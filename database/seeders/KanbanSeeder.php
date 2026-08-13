<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Board;
use App\Models\Task;
use App\Models\Tag;
use App\Models\Comment;
use App\Models\Subtask;

class KanbanSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Users & Tags
        $users = User::factory(5)->create();
        $tags = collect(range(1, 8))->map(function (int $number) {
            return Tag::firstOrCreate(
                ['name' => "Kanban Tag {$number}"],
                ['color' => fake()->randomElement(['primary', 'secondary', 'success', 'info', 'warning', 'error'])]
            );
        });

        // 2. Create Standard Boards
        $boardTitles = ['To Do', 'In Progress', 'In Review', 'Done'];
        $boards = collect();

        foreach ($boardTitles as $index => $title) {
            $boards->push(Board::create([
                'title' => $title,
                'order' => $index,
            ]));
        }

        // 3. Create Tasks for each board and wire up relationships
        foreach ($boards as $board) {
            // Give each board 3 to 6 tasks
            $tasks = Task::factory(rand(3, 6))->create([
                'board_id' => $board->id,
            ]);

            $taskOrder = 0;

            foreach ($tasks as $task) {
                // Set vertical ordering
                $task->update(['order' => $taskOrder++]);

                // Attach random users (assignees)
                $assignedUsers = $users->random(rand(1, 3));
                $assignedUserIds = $assignedUsers->pluck('id')->toArray();
                $task->users()->attach($assignedUserIds);
                $board->users()->syncWithoutDetaching($assignedUserIds);

                // Attach random tags
                $task->tags()->attach(
                    $tags->random(rand(1, 3))->pluck('id')->toArray()
                );

                // Add 0 to 4 comments per task
                if (rand(0, 1)) {
                    Comment::factory(rand(1, 4))->create([
                        'task_id' => $task->id,
                        'user_id' => $users->random()->id,
                    ]);
                }

                // Add 0 to 3 subtasks
                if (rand(0, 1)) {
                    Subtask::factory(rand(1, 3))->create([
                        'task_id' => $task->id,
                        'user_id' => $assignedUsers->random()->id,
                    ]);
                }
            }
        }
    }
}
