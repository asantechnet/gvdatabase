<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;

/**
 * Creates operational work when an applicant case enters a stage that
 * requires staff action (Implementation Plan §26 — Tasks Integration).
 *
 * The stage-to-task map lives here — in one reusable domain service —
 * so controllers never duplicate workflow rules. Creation is idempotent:
 * an open task with the same applicant and title is never duplicated,
 * which keeps re-opened case screens from spawning repeat work.
 */
class ApplicantTaskService
{
    /**
     * @var array<string, list<array{title: string, priority: string, due_in_days: int}>>
     */
    public const STAGE_TASKS = [
        'documents_required' => [
            ['title' => 'Collect required applicant documents', 'priority' => 'high', 'due_in_days' => 7],
        ],
        'university_application' => [
            ['title' => 'Prepare and submit university application', 'priority' => 'high', 'due_in_days' => 14],
        ],
        'visa_preparation' => [
            ['title' => 'Prepare visa documentation', 'priority' => 'high', 'due_in_days' => 7],
        ],
    ];

    /**
     * @return list<Task>
     */
    public function ensureForStage(Applicant $applicant, ApplicantStage $stage, ?User $actor = null): array
    {
        $templates = self::STAGE_TASKS[$stage->key] ?? [];
        if ($templates === []) {
            return [];
        }

        $board = Board::query()->orderBy('order')->orderBy('id')->first();
        if (! $board) {
            return [];
        }

        $assigneeId = $applicant->responsibleEmployee?->user_id ?? $actor?->id;
        $created = [];

        foreach ($templates as $template) {
            $exists = Task::query()
                ->where('applicant_id', $applicant->id)
                ->where('title', $template['title'])
                ->where('progress', '<', 100)
                ->exists();
            if ($exists) {
                continue;
            }

            $task = Task::create([
                'board_id' => $board->id,
                'customer_id' => $applicant->customer_id,
                'applicant_id' => $applicant->id,
                'title' => $template['title'],
                'description' => $this->describe($applicant, $stage),
                'priority' => $template['priority'],
                'due_date' => now()->addDays($template['due_in_days'])->toDateString(),
                'order' => (Task::where('board_id', $board->id)->max('order') ?? -1) + 1,
            ]);

            $assignees = array_values(array_unique(array_filter([$assigneeId])));
            if ($assignees !== []) {
                $task->users()->sync($assignees);
                $board->users()->syncWithoutDetaching($assignees);
            }

            $created[] = $task;
        }

        return $created;
    }

    private function describe(Applicant $applicant, ApplicantStage $stage): string
    {
        $customer = $applicant->customer?->name ?? "customer #{$applicant->customer_id}";

        return "Applicant #{$applicant->id} ({$customer}) entered stage {$stage->name}.";
    }
}
