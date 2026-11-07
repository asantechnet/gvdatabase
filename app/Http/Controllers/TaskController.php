<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskResource;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Services\EducationalWorkflowNotificationService;
use App\Support\EmployeeScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function __construct(
        private readonly EducationalWorkflowNotificationService $notifications,
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'board_id' => 'required|exists:boards,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assignees' => 'sometimes|array',
            'assignees.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')->where('acc_status', 'active')],
            'tags' => 'sometimes|array',
            'tags.*' => 'integer|distinct|exists:tags,id',
            'service_ids' => ['prohibited'],
            'applicant_id' => 'nullable|integer|exists:applicants,id',
        ]);

        $board = Board::findOrFail($validated['board_id']);
        // Case-level tasks are personal work. Contract delivery tasks are
        // created by the workflow service on their service board, so only the
        // manual applicant-linked path needs this guard.
        if (! empty($validated['applicant_id'])) {
            abort_unless(
                $board->isPersonal() && (int) $board->user_id === (int) $request->user()->id,
                422,
                'business.case_task_requires_personal_board'
            );
        }
        Gate::authorize('create', [Task::class, $board]);

        if ($request->has('assignees')) {
            $this->assertAssigneesAssignable($request->user(), $validated['assignees'] ?? []);
        }
        if ($request->has('tags') && ! $request->user()->hasPermission('manage_task_tags')) {
            abort(403, 'auth.forbidden');
        }

        $assignees = $validated['assignees'] ?? [$request->user()->id];
        $tags = $validated['tags'] ?? [];
        unset($validated['assignees'], $validated['tags'], $validated['service_ids']);

        $task = DB::transaction(function () use ($validated, $assignees, $tags) {
            $board = Board::whereKey($validated['board_id'])->lockForUpdate()->firstOrFail();

            // Serializing creation per board prevents duplicate ordering when
            // multiple tasks are added to the same column concurrently.
            $validated['order'] = (Task::where('board_id', $board->id)->max('order') ?? -1) + 1;

            $task = Task::create($validated);
            $task->users()->sync($assignees);
            $task->tags()->sync($tags);
            $board->users()->syncWithoutDetaching($assignees);

            return $task;
        });

        // Load empty relations so the resource doesn't fail
        $task->load(['users.employee', 'tags', 'applicant', 'subtasks', 'comments.user.employee', 'dependencies', 'attachments']);

        $this->notifications->taskAssigned($task, $assignees, (int) $request->user()->id);

        return new TaskResource($task);
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'progress' => ['sometimes', 'integer', Rule::in([0, 100])],
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'dependencies' => 'sometimes|array',
            'dependencies.*' => [
                'integer',
                'distinct',
                'exists:tasks,id',
                Rule::notIn([$task->id]),
            ],
            'assignees' => 'sometimes|array',
            'assignees.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')->where('acc_status', 'active')],
            'tags' => 'sometimes|array',
            'tags.*' => 'integer|distinct|exists:tags,id',
            'service_ids' => ['prohibited'],
            'applicant_id' => 'nullable|integer|exists:applicants,id',
        ]);

        Gate::authorize('update', $task);

        if (array_key_exists('progress', $validated)) {
            abort_if($task->subtasks()->exists(), 422, 'business.task_progress_derived_from_subtasks');
            if ((int) $validated['progress'] === 100) {
                abort_if($task->dependencies()->where('progress', '<', 100)->exists(), 422, 'business.task_dependencies_incomplete');
            }
        }

        if ($request->has('assignees')) {
            Gate::authorize('assignMembers', $task);
            $this->assertAssigneesAssignable($request->user(), $validated['assignees'] ?? []);
        }
        if ($request->has('tags')) {
            Gate::authorize('manageTags', $task);
        }
        if ($request->has('dependencies')) {
            Gate::authorize('manageDependencies', $task);
        }

        $syncAssignees = $request->has('assignees');
        $syncTags = $request->has('tags');
        $syncDependencies = $request->has('dependencies');
        $dependencies = $validated['dependencies'] ?? [];
        $assignees = $validated['assignees'] ?? [];
        $tags = $validated['tags'] ?? [];
        unset($validated['dependencies'], $validated['assignees'], $validated['tags'], $validated['service_ids']);

        $task = DB::transaction(function () use (
            $task,
            $validated,
            $syncAssignees,
            $syncTags,
            $syncDependencies,
            $dependencies,
            $assignees,
            $tags,
        ) {
            if ($syncDependencies) {
                // Every dependency edit follows this lock order, making the
                // graph check and pivot update one serialized operation.
                $lockedTasks = Task::query()->orderBy('id')->lockForUpdate()->get();
                $task = $lockedTasks->firstWhere('id', $task->id) ?? throw new \RuntimeException('Task not found.');
                $this->assertNoDependencyCycle($task, $dependencies);
            } else {
                $task = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            }

            $task->update($validated);

            if ($syncAssignees) {
                $task->users()->sync($assignees);
                $task->board->users()->syncWithoutDetaching($assignees);
            }
            if ($syncTags) {
                $task->tags()->sync($tags);
            }
            if ($syncDependencies) {
                $task->dependencies()->sync($dependencies);
            }

            return $task;
        });

        $task = $task->fresh()->load(['users.employee', 'tags', 'applicant', 'subtasks', 'comments.user.employee', 'dependencies', 'attachments']);

        if ($syncAssignees) {
            $this->notifications->taskAssigned($task, $assignees, (int) $request->user()->id);
        }

        return new TaskResource($task);
    }

    public function destroy(Task $task)
    {
        Gate::authorize('delete', $task);
        abort_if(DB::table('task_dependencies')->where('depends_on_task_id', $task->id)->exists(), 422, 'business.task_is_dependency');
        $task->delete();

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
            throw ValidationException::withMessages(['contributions' => ['business.task_contributions_must_total_100']]);
        }
        if ($contributions !== []) {
            $assignedIds = $task->users()->pluck('users.id')->map(fn ($id) => (string) $id)->sort()->values()->all();
            $contributionIds = collect(array_keys($contributions))->map(fn ($id) => (string) $id)->sort()->values()->all();
            if ($assignedIds !== $contributionIds) {
                throw ValidationException::withMessages(['contributions' => ['business.task_contributions_assignees_mismatch']]);
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

    /**
     * Authorises the requested assignees. A role grant is unrestricted; a
     * department manager may only hand work to their own department members.
     *
     * @param  list<int|string>  $assigneeIds
     */
    private function assertAssigneesAssignable(User $user, array $assigneeIds): void
    {
        if ($user->hasDirectPermission('assign_task_members')) {
            return;
        }

        $scope = app(EmployeeScope::class);
        if (! $scope->isDepartmentManager($user)) {
            abort(403, 'auth.forbidden');
        }

        $allowed = [...$scope->managedDepartmentUserIds($user), (int) $user->id];
        $outside = collect($assigneeIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => in_array($id, $allowed, true));

        if ($outside->isNotEmpty()) {
            throw ValidationException::withMessages([
                'assignees' => ['business.task_assignee_outside_department'],
            ]);
        }
    }

    /** @param list<int> $dependencyIds */
    private function assertNoDependencyCycle(Task $task, array $dependencyIds): void
    {
        $frontier = array_values($dependencyIds);
        $visited = [];

        while ($frontier !== []) {
            if (in_array($task->id, $frontier, true)) {
                throw ValidationException::withMessages([
                    'dependencies' => ['business.task_dependency_cycle'],
                ]);
            }

            $visited = array_values(array_unique([...$visited, ...$frontier]));
            $frontier = DB::table('task_dependencies')
                ->whereIn('task_id', $frontier)
                ->pluck('depends_on_task_id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => in_array($id, $visited, true))
                ->unique()
                ->values()
                ->all();
        }
    }
}
