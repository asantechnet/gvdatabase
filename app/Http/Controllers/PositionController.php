<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Http\Resources\PositionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PositionController extends Controller
{
    public function index()
    {
        $positions = Position::with('permissions')->get();
        return response()->json([
            'success' => true,
            'data' => PositionResource::collection($positions)
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:positions,title',
            'salary' => 'required|integer|min:0',
            'status' => 'required|string|max:255',
            'is_sys_user' => 'required|boolean',
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $position = Position::create($request->all());

        if ($request->has('permission_ids')) {
            $position->permissions()->sync($request->permission_ids);
        }

        $position->load('permissions');

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position)
        ], 201);
    }

    public function show($id)
    {
        $position = Position::with('permissions')->find($id);
        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new PositionResource($position)
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

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255|unique:positions,title,'.$id,
            'salary' => 'sometimes|integer|min:0',
            'status' => 'sometimes|string|max:255',
            'is_sys_user' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $position->update($request->all());

        if ($request->has('permission_ids')) {
            $position->permissions()->sync($request->permission_ids);
        }

        $position->load('permissions');

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position)
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

        $position->delete();
        return response()->json([
            'success' => true,
            'message' => 'Position deleted successfully'
        ]);
    }

    public function attachPermission(Request $request, $id)
    {
        $position = Position::find($id);
        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'permission_id' => 'required|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $position->permissions()->attach($request->permission_id);
        $position->load('permissions');

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position)
        ]);
    }

    public function detachPermission(Request $request, $id)
    {
        $position = Position::find($id);
        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'permission_id' => 'required|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $position->permissions()->detach($request->permission_id);
        $position->load('permissions');

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position)
        ]);
    }
}
