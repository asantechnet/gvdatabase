<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Http\Resources\BoardResource;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    /**
     * Get all boards in order
     */
    public function index()
    {
        $boards = Board::with(['tasks' => function($query) {
                $query->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        return BoardResource::collection($boards);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'order' => 'nullable|integer',
        ]);

        // Default to the end of the board list if order isn't provided
        if (!isset($validated['order'])) {
            $validated['order'] = Board::max('order') + 1;
        }

        $board = Board::create($validated);

        // Load empty tasks array so the frontend structure matches
        $board->load('tasks');

        return new BoardResource($board);
    }

    public function update(Request $request, Board $board)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'order' => 'nullable|integer',
        ]);

        $board->update($validated);

        return response()->json(['message' => 'Board updated successfully']);
    }

    public function destroy(Board $board)
    {
        $board->delete(); // Cascades to delete nested tasks and comments

        return response()->json(['message' => 'Board deleted successfully']);
    }
}
