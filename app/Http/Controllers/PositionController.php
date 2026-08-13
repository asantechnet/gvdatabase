<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Http\Resources\PositionResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PositionController extends Controller
{
    public function index(): JsonResponse
    {
        $positions = Position::query()
            ->withCount(['employees', 'users'])
            ->orderBy('title')
            ->get();
        return response()->json([
            'success' => true,
            'data' => PositionResource::collection($positions)
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255|unique:positions,title',
            'salary' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $position = Position::create($data);

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position->loadCount(['employees', 'users']))
        ], 201);
    }

    public function show($id)
    {
        $position = Position::withCount(['employees', 'users'])->find($id);
        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new PositionResource($position->loadCount(['employees', 'users']))
        ]);
    }

    public function update(Request $request, $id)
    {
        $position = Position::find($id);
        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:255|unique:positions,title,'.$id,
            'salary' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $position->update($data);

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position->loadCount(['employees', 'users']))
        ]);
    }

    public function destroy($id)
    {
        $position = Position::find($id);
        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        if ($position->employees()->exists() || $position->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This position is assigned to employees or users and cannot be deleted.'
            ], 422);
        }

        $position->delete();
        return response()->json([
            'success' => true,
            'message' => 'Position deleted successfully'
        ]);
    }

}
