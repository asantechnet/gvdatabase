<?php

namespace Database\Seeders;

use App\Models\Task;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'Kicked off this task and confirmed the next step with the applicant.',
            'Waiting on the applicant to upload the remaining documents.',
            'Reviewed the payment state with finance; no blockers.',
            'Followed up and waiting for a response.',
        ];

        Task::query()->with('users:id')->get()->each(function (Task $task) use ($templates): void {
            $userIds = $task->users->pluck('id')->values();
            if ($userIds->isEmpty()) {
                return;
            }

            $authorId = $userIds->first();
            $count = (int) $task->progress >= 100 ? 2 : 1;

            for ($i = 0; $i < $count; $i++) {
                $content = $templates[($task->id + $i) % count($templates)];
                $task->comments()->updateOrCreate(
                    ['user_id' => $authorId, 'content' => $content],
                    [],
                );
            }
        });
    }
}
