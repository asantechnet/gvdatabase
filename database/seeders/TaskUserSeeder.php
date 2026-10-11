<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskUserSeeder extends Seeder
{
    public function run(): void
    {
        $operationsUserIds = Employee::query()
            ->whereHas('position', fn ($query) => $query->whereIn('system_key', ['document_officer', 'manager']))
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        Task::query()
            ->with(['applicant.responsibleEmployee', 'applicant.admissionEmployee', 'board'])
            ->get()
            ->each(function (Task $task) use ($operationsUserIds): void {
                $assignees = [];
                if ($task->applicant) {
                    $assignees = array_filter([
                        $task->applicant->responsibleEmployee?->user_id,
                        $task->applicant->admissionEmployee?->user_id,
                    ]);
                }
                if ($assignees === []) {
                    $assignees = $operationsUserIds;
                }

                $assignees = array_values(array_unique(array_filter($assignees)));
                if ($assignees === []) {
                    return;
                }

                $task->users()->syncWithoutDetaching($assignees);
                $task->board?->users()->syncWithoutDetaching($assignees);
            });
    }
}
