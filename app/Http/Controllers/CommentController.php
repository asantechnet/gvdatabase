<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Comment;
use App\Http\Resources\CommentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Support\EditWindow;

class CommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        Gate::authorize('create', [Comment::class, $task]);
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        // Create the comment and assign it to the currently logged-in user
        $comment = $task->comments()->create([
            'content' => $validated['content'],
            'user_id' => $request->user()->id,
        ]);

        // Load the user relation so CommentResource can fetch the 'senderName'
        $comment->load('user');

        return new CommentResource($comment);
    }

    public function update(Request $request, Task $task, Comment $comment, EditWindow $editWindow)
    {
        abort_unless($comment->task_id === $task->id, 404);
        Gate::authorize('view', $task);
        Gate::authorize('update', $comment);
        $editWindow->enforce($request->user(), $comment);

        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $comment->update($data);

        return new CommentResource($comment->fresh()->load('user'));
    }

    public function destroy(Request $request, Task $task, Comment $comment)
    {
        abort_unless($comment->task_id === $task->id, 404);
        Gate::authorize('view', $task);
        Gate::authorize('delete', $comment);
        $comment->delete();

        return response()->json(['success' => true]);
    }
}
