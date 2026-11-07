<?php

namespace App\Http\Controllers;

use App\Http\Resources\BoardResource;
use App\Models\Board;
use App\Support\EmployeeScope;
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
            ->with(['tasks' => function ($query) use ($user) {
                $query->when(! $user->hasPermission('view_all_tasks'), fn ($tasks) => $tasks
                    ->whereHas('users', fn ($assignees) => $assignees->whereIn('users.id', app(EmployeeScope::class)->visibleUserIds($user))))
                    ->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        return BoardResource::collection($boards);
    }
}
