<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskResource;
use App\Models\Board;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assignees' => 'sometimes|array',
            'assignees.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')->where('acc_status', 'active')],
            'tags' => 'sometimes|array',
            'tags.*' => 'integer|distinct|exists:tags,id',
            'service_ids' => 'sometimes|array',
            'service_ids.*' => 'integer|distinct|exists:services,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'applicant_id' => 'nullable|integer|exists:applicants,id',
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
        $services = $validated['service_ids'] ?? [];
        $validated['customer_id'] ??= $board->project?->customer_id;
        $validated['customer_id'] ??= $this->customerIdForApplicant($validated['applicant_id'] ?? null);
        $this->assertApplicantMatchesCustomer($validated['applicant_id'] ?? null, $validated['customer_id'] ?? null);
        $this->assertCustomerMatchesProject($board, $validated['customer_id']);
        $this->assertServicesMatchProject($board, $services);
        unset($validated['assignees'], $validated['tags'], $validated['service_ids']);

        $task = DB::transaction(function () use ($validated, $assignees, $tags, $services) {
            $board = Board::whereKey($validated['board_id'])->lockForUpdate()->firstOrFail();

            // Serializing creation per board prevents duplicate ordering when
            // multiple tasks are added to the same column concurrently.
            $validated['order'] = (Task::where('board_id', $board->id)->max('order') ?? -1) + 1;

            $task = Task::create($validated);
            $task->users()->sync($assignees);
            $task->tags()->sync($tags);
            $task->services()->sync($services);
            $board->users()->syncWithoutDetaching($assignees);

            return $task;
        });

        // Load empty relations so the resource doesn't fail
        $task->load(['users.employee', 'tags', 'services.currency', 'customer', 'applicant', 'subtasks', 'comments.user.employee', 'dependencies', 'attachments']);

        return new TaskResource($task);
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'results' => 'nullable|string',
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
            'service_ids' => 'sometimes|array',
            'service_ids.*' => 'integer|distinct|exists:services,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
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
        $syncServices = $request->has('service_ids');
        $dependencies = $validated['dependencies'] ?? [];
        $assignees = $validated['assignees'] ?? [];
        $tags = $validated['tags'] ?? [];
        $services = $validated['service_ids'] ?? [];
        if ($syncServices) {
            $this->assertServicesMatchProject($task->board, $services);
        }
        unset($validated['dependencies'], $validated['assignees'], $validated['tags'], $validated['service_ids']);

        $task = DB::transaction(function () use (
            $task,
            $validated,
            $syncAssignees,
            $syncTags,
            $syncDependencies,
            $syncServices,
            $dependencies,
            $assignees,
            $tags,
            $services,
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
            $this->assertCustomerMatchesProject($task->board, $task->customer_id);
            $this->assertApplicantMatchesCustomer($task->applicant_id, $task->customer_id);

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
            if ($syncServices) {
                $task->services()->sync($services);
            }

            return $task;
        });

        return new TaskResource($task->fresh()->load(['users.employee', 'tags', 'services.currency', 'customer', 'applicant', 'subtasks', 'comments.user.employee', 'dependencies', 'attachments']));
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

    /** @param list<int> $dependencyIds */
    private function assertNoDependencyCycle(Task $task, array $dependencyIds): void
    {
        $projectId = $task->board?->project_id;
        $crossProject = Task::query()->whereIn('id', $dependencyIds)
            ->whereHas('board', fn ($query) => $projectId === null
                ? $query->whereNotNull('project_id')
                : $query->where('project_id', '!=', $projectId)->orWhereNull('project_id'))
            ->exists();
        if ($crossProject) {
            throw ValidationException::withMessages(['dependencies' => ['business.task_dependency_cross_project']]);
        }

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

    private function assertCustomerMatchesProject(Board $board, ?int $customerId): void
    {
        $projectCustomerId = $board->project?->customer_id;
        if ($projectCustomerId !== null && (int) $customerId !== (int) $projectCustomerId) {
            throw ValidationException::withMessages(['customer_id' => ['business.task_customer_must_match_project']]);
        }
    }

    private function customerIdForApplicant(?int $applicantId): ?int
    {
        if ($applicantId === null) {
            return null;
        }

        return \App\Models\Applicant::whereKey($applicantId)->value('customer_id');
    }

    private function assertApplicantMatchesCustomer(?int $applicantId, ?int $customerId): void
    {
        if ($applicantId === null || $customerId === null) {
            return;
        }

        $ownerId = \App\Models\Applicant::whereKey($applicantId)->value('customer_id');
        if ((int) $ownerId !== (int) $customerId) {
            throw ValidationException::withMessages(['applicant_id' => ['business.task_applicant_must_match_customer']]);
        }
    }

    /** @param list<int> $serviceIds */
    private function assertServicesMatchProject(Board $board, array $serviceIds): void
    {
        if ($serviceIds === []) {
            return;
        }

        $project = $board->project;
        $validIds = $project?->services()->whereKey($serviceIds)->pluck('services.id')->map(fn ($id) => (int) $id)->all() ?? [];
        if (count(array_unique($serviceIds)) !== count($validIds)) {
            throw ValidationException::withMessages(['service_ids' => ['business.task_service_not_in_project']]);
        }
    }
}
