<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('positions')->get();
        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users)
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'acc_status' => 'required|string|in:active,inactive,suspended',
            'position_ids' => 'nullable|array',
            'position_ids.*' => 'exists:positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        if ($request->has('position_ids')) {
            $user->positions()->sync($request->position_ids);
        }

        $user->load('positions');

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ], 201);
    }

    public function show($id)
    {
        $user = User::with('positions')->find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$id,
            'password' => 'sometimes|string|min:8',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string',
            'acc_status' => 'sometimes|string|in:active,inactive,suspended',
            'position_ids' => 'nullable|array',
            'position_ids.*' => 'exists:positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        if ($request->has('position_ids')) {
            $user->positions()->sync($request->position_ids);
        }

        $user->load('positions');

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->delete();
        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'acc_status' => 'required|string|in:active,inactive,suspended',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update(['acc_status' => $request->acc_status]);
        $user->load('positions');

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }

    public function attachPosition(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'position_id' => 'required|exists:positions,id',
            'assigned_at' => 'nullable|date',
            'ended_at' => 'nullable|date|after_or_equal:assigned_at',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->positions()->attach($request->position_id, [
            'assigned_at' => $request->assigned_at,
            'ended_at' => $request->ended_at,
        ]);
        $user->load('positions');

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }

    public function detachPosition(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'position_id' => 'required|exists:positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->positions()->detach($request->position_id);
        $user->load('positions');

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }
}
