<?php

namespace App\Http\Controllers;

use App\Http\Resources\BoardResource;
use App\Models\Board;
use App\Models\User;
use App\Support\EmployeeScope;
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
            ->when($request->filled('project_id'), fn ($query) => $query->where('project_id', $request->integer('project_id')))
            ->when(! $user->hasPermission('view_all_boards'), fn ($query) => $query
                ->whereHas('users', fn ($members) => $members->whereIn('users.id', app(EmployeeScope::class)->visibleUserIds($user))))
            ->with([
                // Load nested tasks ordered by their vertical position
                'tasks' => fn ($query) => $query
                    ->when(! $user->hasPermission('view_all_tasks'), fn ($tasks) => $tasks
                        ->whereHas('users', fn ($assignees) => $assignees->whereIn('users.id', app(EmployeeScope::class)->visibleUserIds($user))))
                    ->orderBy('order'),
                // Eager load all relations required by TaskResource
                'tasks.users.employee',
                'tasks.tags',
                'tasks.services.currency',
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
