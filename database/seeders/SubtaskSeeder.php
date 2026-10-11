<?php

namespace Database\Seeders;

use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Database\Seeder;

class SubtaskSeeder extends Seeder
{
    public function run(): void
    {
        $steps = [
            'Collect supporting documents',
            'Verify applicant details',
            'Confirm the payment state',
            'Hand over to the next stage',
        ];

        Task::query()->with('users:id')->get()->each(function (Task $task) use ($steps): void {
            $userIds = $task->users->pluck('id')->values();
            if ($userIds->isEmpty()) {
                return;
            }

            $ownerId = $userIds->first();
            // A completed parent has all its steps ticked; open work has the
            // first steps done so the checklist reads like real progress.
            $completedCount = (int) $task->progress >= 100 ? count($steps) : 1;

            foreach ($steps as $index => $title) {
                Subtask::updateOrCreate(
                    ['task_id' => $task->id, 'title' => $title],
                    [
                        'user_id' => $ownerId,
                        'category' => 'General',
                        'priority' => $task->priority ?? 'medium',
                        'is_completed' => $index < $completedCount,
                    ],
                );
            }
        });
    }
}
