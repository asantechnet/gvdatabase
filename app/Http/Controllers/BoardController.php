<?php

namespace App\Http\Controllers;

use App\Http\Resources\BoardResource;
use App\Models\Board;
use App\Support\ServiceBoard;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    /**
     * Get all readable boards in order.
     *
     * Board definitions are product-owned: each service has one board and each
     * employee has one private personal board. There is deliberately no board
     * create/update/delete path.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Every employee keeps one private personal board. Create it lazily so
        // the Kanban always has a Personal column after a fresh install.
        if (! Board::query()->where('user_id', $user->id)->exists()) {
            ServiceBoard::personalFor($user);
        }

        $boards = Board::query()
            ->visibleTo($user)
            ->with([
                'tasks' => fn ($query) => $query
                    ->visibleTo($user)
                    ->orderBy('order'),
                'tasks.users.employee',
                'tasks.creator.employee',
                'tasks.tags',
                'tasks.subtasks',
                'tasks.comments.user.employee',
                'tasks.comments.mentionedUsers',
                'tasks.dependencies',
                'tasks.attachments',
            ])
            ->orderBy('order')
            ->get();

        return BoardResource::collection($boards);
    }
}
