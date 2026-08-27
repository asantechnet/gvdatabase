<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * The permission catalog is system-owned and read-only. The mutable
     * role->permission assignment lives in the role_permissions pivot via
     * RoleController::updatePermissions.
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::query()->orderBy('category')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => PermissionResource::collection($permissions),
        ]);
    }
}