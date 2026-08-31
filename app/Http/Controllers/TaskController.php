<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Board;
use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'board_id' => 'required|exists:boards,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'results' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'assignees' => 'sometimes|array',
            'assignees.*' => 'integer|distinct|exists:users,id',
            'tags' => 'sometimes|array',
            'tags.*' => 'integer|distinct|exists:tags,id',
            'service_ids' => 'sometimes|array',
            'service_ids.*' => 'integer|distinct|exists:services,id',
        ]);

        $board = Board::findOrFail($validated['board_id']);
        Gate::authorize('create', [Task::class, $board]);

        if ($request->has('assignees') && ! $request->user()->hasPermission('assign_task_members')) {
            abort(403, 'auth.forbidden');
        }
        if ($request->has('tags') && ! $request->user()->hasPermission('manage_task_tags')) {
            abort(403, 'auth.forbidden');
        }

        $assignees = $validated['assignees'] ?? [$request->user()->id];
        $tags = $validated['tags'] ?? [];
        $serviceIds = $validated['service_ids'] ?? [];
        unset($validated['assignees'], $validated['tags'], $validated['service_ids']);

        // Place at the bottom of the column by default
        $validated['order'] = (Task::where('board_id', $validated['board_id'])->max('order') ?? -1) + 1;

        $task = Task::create($validated);
        $task->users()->sync($assignees);
        $task->tags()->sync($tags);
        $this->syncServices($task, $serviceIds, $board);
        $board->users()->syncWithoutDetaching($assignees);

        // Load empty relations so the resource doesn't fail
        $task->load(['users.employee', 'tags', 'services.currency', 'subtasks', 'comments.user.employee', 'dependencies', 'attachments']);

        return new TaskResource($task);
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'results' => 'nullable|string',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'progress' => 'prohibited',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'dependencies' => 'sometimes|array',
            'dependencies.*' => [
                'integer',
                'distinct',
                'exists:tasks,id',
                Rule::notIn([$task->id]),
            ],
            'assignees' => 'sometimes|array',
            'assignees.*' => 'integer|distinct|exists:users,id',
            'tags' => 'sometimes|array',
            'tags.*' => 'integer|distinct|exists:tags,id',
            'service_ids' => 'sometimes|array',
            'service_ids.*' => 'integer|distinct|exists:services,id',
        ]);

        Gate::authorize('update', $task);

        if ($request->has('assignees')) {
            Gate::authorize('assignMembers', $task);
        }
        if ($request->has('tags')) {
            Gate::authorize('manageTags', $task);
        }
        if ($request->has('dependencies')) {
            Gate::authorize('manageDependencies', $task);
        }

        $dependencies = $validated['dependencies'] ?? null;
        $serviceIds = $validated['service_ids'] ?? null;
        unset($validated['dependencies'], $validated['service_ids']);
        $task->update($validated);

        // Optional: Handle syncing tags or assignees here if passed in the payload
        if ($request->has('assignees')) {
            $task->users()->sync($request->input('assignees'));
            $task->board->users()->syncWithoutDetaching($request->input('assignees'));
        }
        if ($request->has('tags')) {
            $task->tags()->sync($request->input('tags'));
        }
        if ($request->has('dependencies')) {
            $task->dependencies()->sync($dependencies ?? []);
        }
        if ($serviceIds !== null) {
            $this->syncServices($task, $serviceIds, $task->board);
        }

        return new TaskResource($task->fresh()->load(['users.employee', 'tags', 'services.currency', 'subtasks', 'comments.user.employee', 'dependencies', 'attachments']));
    }

    public function destroy(Task $task)
    {
        Gate::authorize('delete', $task);
        $task->delete(); // Will cleanly cascade relationships

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function reviewBonusInputs(Request $request, Task $task)
    {
        abort_unless($request->user()->hasPermission('edit_all_tasks'), 403, 'auth.forbidden');
        abort_unless((int) $task->progress === 100, 422, 'business.task_bonus_review_requires_completion');
        $validated = $request->validate([
            'quality_score' => ['required', 'numeric', 'between:1,5'],
            'rejected' => ['sometimes', 'boolean'],
            'contributions' => ['sometimes', 'array'],
            'contributions.*' => ['numeric', 'between:0,100'],
        ]);
        $contributions = $validated['contributions'] ?? [];
        if ($contributions !== [] && abs(array_sum($contributions) - 100) > 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages(['contributions' => ['business.task_contributions_must_total_100']]);
        }
        if ($contributions !== []) {
            $assignedIds = $task->users()->pluck('users.id')->map(fn ($id) => (string) $id)->sort()->values()->all();
            $contributionIds = collect(array_keys($contributions))->map(fn ($id) => (string) $id)->sort()->values()->all();
            if ($assignedIds !== $contributionIds) {
                throw \Illuminate\Validation\ValidationException::withMessages(['contributions' => ['business.task_contributions_assignees_mismatch']]);
            }
        }

        DB::transaction(function () use ($task, $request, $validated, $contributions): void {
            $locked = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'manager_quality_score' => $validated['quality_score'],
                'quality_reviewed_by' => $request->user()->id,
                'quality_reviewed_at' => now('UTC'),
                'rejected_count' => (int) $locked->rejected_count + (($validated['rejected'] ?? false) ? 1 : 0),
            ]);
            foreach ($contributions as $userId => $percent) {
                abort_unless($locked->users()->whereKey($userId)->exists(), 422, 'business.task_contribution_user_not_assigned');
                $locked->users()->updateExistingPivot($userId, ['contribution_percent' => $percent]);
            }
        });

        return new TaskResource($task->fresh()->load(['users.employee', 'tags', 'subtasks', 'comments.user.employee', 'dependencies', 'attachments']));
    }

    /**
     * Dedicated endpoint to handle Drag & Drop repositioning safely.
     */
    public function move(Request $request, Task $task)
    {
        $validated = $request->validate([
            'board_id' => 'required|exists:boards,id',
            'order' => 'required|integer|min:0',
        ]);
        $destination = Board::findOrFail($validated['board_id']);
        Gate::authorize('move', [$task, $destination]);
        abort_unless($task->board->project_id === $destination->project_id, 422, 'business.task_cross_project_move');

        $positions = DB::transaction(function () use ($task, $validated) {
            // Lock both affected columns. This prevents overlapping drag
            // requests from calculating their positions against stale rows.
            $task = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $sourceBoardId = $task->board_id;
            $targetBoardId = (int) $validated['board_id'];
            $lockedTasks = Task::query()
                ->whereIn('board_id', array_unique([$sourceBoardId, $targetBoardId]))
                ->orderBy('board_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $sortTasks = fn ($tasks) => $tasks->sortBy([
                ['order', 'asc'],
                ['id', 'asc'],
            ])->values();

            if ($sourceBoardId === $targetBoardId) {
                $orderedTasks = $sortTasks($lockedTasks->reject(fn (Task $row) => $row->id === $task->id));
                $targetOrder = min($validated['order'], $orderedTasks->count());
                $orderedTasks->splice($targetOrder, 0, [$task]);

                foreach ($orderedTasks as $order => $orderedTask) {
                    $orderedTask->update(['order' => $order]);
                }

                return $orderedTasks->map(fn (Task $orderedTask) => [
                    'id' => (string) $orderedTask->id,
                    'boardId' => (string) $orderedTask->board_id,
                    'order' => (int) $orderedTask->order,
                ])->all();
            }

            $sourceTasks = $sortTasks($lockedTasks
                ->where('board_id', $sourceBoardId)
                ->reject(fn (Task $row) => $row->id === $task->id));
            $targetTasks = $sortTasks($lockedTasks->where('board_id', $targetBoardId));
            $targetOrder = min($validated['order'], $targetTasks->count());
            $targetTasks->splice($targetOrder, 0, [$task]);

            foreach ($sourceTasks as $order => $sourceTask) {
                $sourceTask->update(['order' => $order]);
            }
            foreach ($targetTasks as $order => $targetTask) {
                $targetTask->update(['board_id' => $targetBoardId, 'order' => $order]);
            }

            return $sourceTasks->merge($targetTasks)->map(fn (Task $orderedTask) => [
                'id' => (string) $orderedTask->id,
                'boardId' => (string) $orderedTask->board_id,
                'order' => (int) $orderedTask->order,
            ])->all();
        });

        return response()->json(['message' => 'Task repositioned', 'positions' => $positions]);
    }

    /** @param list<int> $serviceIds */
    private function syncServices(Task $task, array $serviceIds, Board $board): void
    {
        if ($board->project_id && $serviceIds !== []) {
            $allowedCount = $board->project->services()->whereKey($serviceIds)->count();
            abort_unless($allowedCount === count($serviceIds), 422, 'business.task_service_not_linked_to_project');
        }

        $task->services()->sync($serviceIds);
    }
}
