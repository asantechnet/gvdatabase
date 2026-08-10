<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all();
        return response()->json([
            'success' => true,
            'data' => PermissionResource::collection($permissions)
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
            'category' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $permission = Permission::create($request->all());
        return response()->json([
            'success' => true,
            'data' => new PermissionResource($permission)
        ], 201);
    }

    public function show($id)
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new PermissionResource($permission)
        ]);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:permissions,name,'.$id,
            'category' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $permission->update($request->all());
        return response()->json([
            'success' => true,
            'data' => new PermissionResource($permission)
        ]);
    }

    public function destroy($id)
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found'
            ], 404);
        }

        $permission->delete();
        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully'
        ]);
    }
}
