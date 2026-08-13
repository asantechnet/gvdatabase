<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Http\Resources\BoardResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class BoardController extends Controller
{
    /**
     * Get all boards in order
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $boards = Board::query()
            ->when(! $user->hasPermission('view_all_boards'), fn ($query) => $query
                ->whereHas('users', fn ($members) => $members->whereKey($user->id)))
            ->with(['tasks' => function ($query) use ($user) {
                $query->when(! $user->hasPermission('view_all_tasks'), fn ($tasks) => $tasks
                    ->whereHas('users', fn ($assignees) => $assignees->whereKey($user->id)))
                    ->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        return BoardResource::collection($boards);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Board::class);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'order' => 'nullable|integer',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($request->has('user_ids') && ! $request->user()->hasPermission('assign_board_members')) {
            abort(403, 'auth.forbidden');
        }

        // Default to the end of the board list if order isn't provided
        if (!isset($validated['order'])) {
            $validated['order'] = Board::max('order') + 1;
        }

        $memberIds = $validated['user_ids'] ?? [];
        unset($validated['user_ids']);
        $board = Board::create($validated);
        $board->users()->syncWithoutDetaching(array_unique([...$memberIds, $request->user()->id]));

        // Load empty tasks array so the frontend structure matches
        $board->load('tasks');

        return new BoardResource($board);
    }

    /** Persist the complete Kanban column order after a drag-and-drop. */
    public function reorder(Request $request)
    {
        Gate::authorize('reorder', Board::class);
        $validated = $request->validate([
            'board_ids' => 'required|array',
            'board_ids.*' => 'integer|distinct|exists:boards,id',
        ]);

        DB::transaction(function () use ($validated) {
            $boards = Board::query()->orderBy('id')->lockForUpdate()->get();
            $boardIds = collect($validated['board_ids'])->map(fn ($id) => (int) $id);

            abort_unless(
                $boards->count() === $boardIds->count()
                && $boards->pluck('id')->sort()->values()->all() === $boardIds->sort()->values()->all(),
                422,
                'business.board_order_invalid'
            );

            foreach ($boardIds->values() as $order => $boardId) {
                $boards->firstWhere('id', $boardId)->update(['order' => $order]);
            }
        });

        return response()->json(['message' => 'Boards reordered successfully']);
    }

    public function update(Request $request, Board $board)
    {
        Gate::authorize('update', $board);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'order' => 'nullable|integer',
        ]);

        $board->update($validated);

        return response()->json(['message' => 'Board updated successfully']);
    }

    public function destroy(Board $board)
    {
        Gate::authorize('delete', $board);
        $board->delete(); // Cascades to delete nested tasks and comments

        return response()->json(['message' => 'Board deleted successfully']);
    }

    public function syncMembers(Request $request, Board $board)
    {
        Gate::authorize('assignMembers', $board);
        $data = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        // A board manager must retain visibility after updating membership.
        $board->users()->sync(array_unique([...$data['user_ids'], $request->user()->id]));

        return new BoardResource($board->fresh()->load('users'));
    }
}
