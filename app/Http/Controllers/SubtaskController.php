<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubtaskResource;
use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubtaskController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        [$subtask, $progress] = DB::transaction(function () use ($task, $validated) {
            $subtask = $task->subtasks()->create([
                ...$validated,
                'category' => $validated['category'] ?? 'General',
                'priority' => $validated['priority'] ?? 'medium',
            ]);

            return [$subtask, $this->syncTaskProgress($task)];
        });

        return (new SubtaskResource($subtask))->additional(['taskProgress' => $progress]);
    }

    public function update(Request $request, Task $task, Subtask $subtask)
    {
        $this->ensureSubtaskBelongsToTask($task, $subtask);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'is_completed' => 'sometimes|boolean',
        ]);

        $progress = DB::transaction(function () use ($task, $subtask, $validated) {
            $subtask->update($validated);

            return $this->syncTaskProgress($task);
        });

        return (new SubtaskResource($subtask->fresh()))->additional(['taskProgress' => $progress]);
    }

    public function destroy(Task $task, Subtask $subtask)
    {
        $this->ensureSubtaskBelongsToTask($task, $subtask);

        $progress = DB::transaction(function () use ($task, $subtask) {
            $subtask->delete();

            return $this->syncTaskProgress($task);
        });

        return response()->json([
            'data' => ['id' => (string) $subtask->id],
            'taskProgress' => $progress,
        ]);
    }

    private function syncTaskProgress(Task $task): int
    {
        $total = $task->subtasks()->count();
        $completed = $task->subtasks()->where('is_completed', true)->count();
        $progress = $total === 0 ? 0 : (int) round(($completed / $total) * 100);

        $task->update(['progress' => $progress]);

        return $progress;
    }

    private function ensureSubtaskBelongsToTask(Task $task, Subtask $subtask): void
    {
        abort_unless($subtask->task_id === $task->id, 404);
    }
}
