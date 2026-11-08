<?php

namespace App\Http\Controllers;

use App\Http\Resources\BoardResource;
use App\Models\Board;
use App\Models\User;
use App\Support\ServiceBoard;
use Illuminate\Http\Request;

class KanbanController extends Controller
{
    /**
     * Fetch the entire Kanban board state.
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        // The Kanban always shows the caller's private personal board; create it
        // on first access.
        if (! Board::query()->where('user_id', $user->id)->exists()) {
            ServiceBoard::personalFor($user);
        }

        $boards = Board::query()
            ->visibleTo($user)
            ->with([
                // Load nested tasks ordered by their vertical position
                'tasks' => fn ($query) => $query
                    ->visibleTo($user)
                    ->orderBy('order'),
                // Eager load all relations required by TaskResource
                'tasks.users.employee',
                'tasks.tags',
                'tasks.subtasks',
                'tasks.comments.user.employee', // Names and avatars for comments
                'tasks.dependencies',
                'tasks.attachments',
            ])
            ->orderBy('order')
            ->get();

        return BoardResource::collection($boards);
    }
}
