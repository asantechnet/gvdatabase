<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubtaskResource;
use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Support\EditWindow;

class SubtaskController extends Controller
{
    public function store(Request $request, Task $task)
    {
        Gate::authorize('create', [Subtask::class, $task]);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $userId = $request->user()->id;
        [$subtask, $progress] = DB::transaction(function () use ($task, $validated, $userId) {
            $subtask = $task->subtasks()->create([
                ...$validated,
                'user_id' => $userId,
                'category' => $validated['category'] ?? 'General',
                'priority' => $validated['priority'] ?? 'medium',
            ]);

            return [$subtask, $this->syncTaskProgress($task)];
        });

        return (new SubtaskResource($subtask))->additional(['taskProgress' => $progress]);
    }

    public function update(Request $request, Task $task, Subtask $subtask, EditWindow $editWindow)
    {
        $this->ensureSubtaskBelongsToTask($task, $subtask);
        Gate::authorize('view', $task);
        Gate::authorize('update', $subtask);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'is_completed' => 'sometimes|boolean',
        ]);
        if (array_intersect(array_keys($validated), ['title', 'category', 'priority'])) {
            $editWindow->enforce($request->user(), $subtask);
        }

        $progress = DB::transaction(function () use ($task, $subtask, $validated) {
            $subtask->update($validated);

            return $this->syncTaskProgress($task);
        });

        return (new SubtaskResource($subtask->fresh()))->additional(['taskProgress' => $progress]);
    }

    public function destroy(Task $task, Subtask $subtask)
    {
        $this->ensureSubtaskBelongsToTask($task, $subtask);
        Gate::authorize('view', $task);
        Gate::authorize('delete', $subtask);

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
