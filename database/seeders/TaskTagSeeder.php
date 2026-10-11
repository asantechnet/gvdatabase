<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskTagSeeder extends Seeder
{
    public function run(): void
    {
        $byBoard = [
            'Private Contracts' => 'Visa',
            'Scholarship' => 'Counseling',
            'Test Offer' => 'Follow-up',
            'Academy' => 'Academy',
            'Personal' => 'Operations',
        ];

        $tags = Tag::query()->pluck('id', 'name');

        Task::query()->with('board')->get()->each(function (Task $task) use ($byBoard, $tags): void {
            $names = [];
            $boardTag = $byBoard[$task->board?->title] ?? null;
            if ($boardTag && isset($tags[$boardTag])) {
                $names[] = $tags[$boardTag];
            }
            if ($task->priority === 'high' && isset($tags['Urgent'])) {
                $names[] = $tags['Urgent'];
            }

            if ($names !== []) {
                $task->tags()->syncWithoutDetaching($names);
            }
        });
    }
}
