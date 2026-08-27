<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Http\Resources\BoardResource;
use App\Models\User;
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

        $boards = Board::query()
        ->when(! $user->hasPermission('view_all_boards'), fn ($query) => $query
            ->whereHas('users', fn ($members) => $members->whereKey($user->id)))
        ->with([
            // Load nested tasks ordered by their vertical position
            'tasks' => fn($query) => $query
                ->when(! $user->hasPermission('view_all_tasks'), fn ($tasks) => $tasks
                    ->whereHas('users', fn ($assignees) => $assignees->whereKey($user->id)))
                ->orderBy('order'),
            // Eager load all relations required by TaskResource
            'tasks.users.employee',
            'tasks.tags',
            'tasks.subtasks',
            'tasks.comments.user.employee', // Names and avatars for comments
            'tasks.dependencies',
            'tasks.attachments'
        ])
        ->orderBy('order')
        ->get();

        return BoardResource::collection($boards);
    }
}
