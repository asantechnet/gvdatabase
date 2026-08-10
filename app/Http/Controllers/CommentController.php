<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Comment;
use App\Http\Resources\CommentResource;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
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
}
