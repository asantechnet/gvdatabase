<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use App\Services\EducationalWorkflowNotificationService;
use App\Support\EditWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function __construct(private readonly EducationalWorkflowNotificationService $notifications) {}

    public function store(Request $request, Task $task)
    {
        Gate::authorize('create', [Comment::class, $task]);
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'mention_ids' => ['sometimes', 'array'],
            'mention_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);

        $comment = $task->comments()->create([
            'content' => $validated['content'],
            'user_id' => $request->user()->id,
        ]);

        $mentionIds = $this->eligibleMentionIds($task, $validated['mention_ids'] ?? []);
        $comment->mentionedUsers()->sync($mentionIds);
        $comment->load(['user.employee', 'mentionedUsers']);

        $this->notifications->taskCommented($task, $comment, $mentionIds, (int) $request->user()->id);

        return new CommentResource($comment);
    }

    public function update(Request $request, Task $task, Comment $comment, EditWindow $editWindow)
    {
        abort_unless($comment->task_id === $task->id, 404);
        Gate::authorize('view', $task);
        Gate::authorize('update', $comment);
        $editWindow->enforce($request->user(), $comment);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'mention_ids' => ['sometimes', 'array'],
            'mention_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);
        $comment->update(['content' => $data['content']]);

        $mentionIds = array_key_exists('mention_ids', $data)
            ? $this->eligibleMentionIds($task, $data['mention_ids'] ?? [])
            : $comment->mentionedUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        if (array_key_exists('mention_ids', $data)) {
            $comment->mentionedUsers()->sync($mentionIds);
        }

        $comment->load(['user.employee', 'mentionedUsers']);
        $this->notifications->taskCommented($task, $comment, $mentionIds, (int) $request->user()->id, notifyAllAssignees: false);

        return new CommentResource($comment);
    }

    public function destroy(Request $request, Task $task, Comment $comment)
    {
        abort_unless($comment->task_id === $task->id, 404);
        Gate::authorize('view', $task);
        Gate::authorize('delete', $comment);
        $comment->delete();

        return response()->json(['success' => true]);
    }

    /** @param array<int, int|string> $mentionIds @return array<int, int> */
    private function eligibleMentionIds(Task $task, array $mentionIds): array
    {
        $eligible = $task->users()->pluck('users.id')
            ->push($task->created_by)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        return collect($mentionIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $eligible->contains($id))
            ->unique()
            ->values()
            ->all();
    }
}
