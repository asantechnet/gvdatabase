<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'board_id' => 'required|exists:boards,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
        ]);

        // Place at the bottom of the column by default
        $validated['order'] = Task::where('board_id', $validated['board_id'])->max('order') + 1;

        $task = Task::create($validated);

        // Load empty relations so the resource doesn't fail
        $task->load(['users', 'tags', 'subtasks', 'comments', 'dependencies']);

        return new TaskResource($task);
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'progress' => 'sometimes|integer|min:0|max:100',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
        ]);

        $task->update($validated);

        // Optional: Handle syncing tags or assignees here if passed in the payload
        if ($request->has('assignees')) {
            $task->users()->sync($request->input('assignees'));
        }
        if ($request->has('tags')) {
            $task->tags()->sync($request->input('tags'));
        }

        return response()->json(['message' => 'Task updated successfully']);
    }

    public function destroy(Task $task)
    {
        $task->delete(); // Will cleanly cascade relationships

        return response()->json(['message' => 'Task deleted successfully']);
    }

    /**
     * Dedicated endpoint to handle Drag & Drop repositioning safely.
     */
    public function move(Request $request, Task $task)
    {
        $validated = $request->validate([
            'board_id' => 'required|exists:boards,id',
            'order' => 'required|integer|min:0',
        ]);

        $task->update([
            'board_id' => $validated['board_id'],
            'order' => $validated['order'],
        ]);

        return response()->json(['message' => 'Task repositioned']);
    }
}
