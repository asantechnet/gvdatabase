<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Http\Resources\BoardResource;
use Illuminate\Http\Request;

class KanbanController extends Controller
{
    /**
     * Fetch the entire Kanban board state.
     */
    public function index(Request $request)
    {
        $boards = Board::with([
            // Load nested tasks ordered by their vertical position
            'tasks' => fn($query) => $query->orderBy('order'),
            // Eager load all relations required by TaskResource
            'tasks.users',
            'tasks.tags',
            'tasks.subtasks',
            'tasks.comments.user', // Nested eager load for sender names
            'tasks.dependencies'
        ])
        ->orderBy('order')
        ->get();

        return BoardResource::collection($boards);
    }
}
