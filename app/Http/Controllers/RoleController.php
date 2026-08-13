<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Support\RoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('view_roles'), 403, 'auth.forbidden');

        $permissions = Permission::query()->orderBy('category')->orderBy('name')->get(['id', 'name', 'category']);
        $grants = RolePermission::query()->get()->groupBy('role');

        return response()->json(['success' => true, 'data' => collect(RoleCatalog::NAMES)->map(function (string $role) use ($permissions, $grants) {
            $permissionIds = $role === 'super_admin'
                ? $permissions->pluck('id')->all()
                : $grants->get($role, collect())->pluck('permission_id')->all();

            return [
                'name' => $role,
                'permissions' => $permissions->whereIn('id', $permissionIds)->pluck('name')->values(),
            ];
        })->values()]);
    }

    public function updatePermissions(Request $request, string $role): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage_role_permissions'), 403, 'auth.forbidden');
        abort_unless(RoleCatalog::isKnown($role), 404);
        abort_if($role === 'super_admin', 422, 'business.request_rejected');

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,name'],
        ]);

        $ids = Permission::query()->whereIn('name', $data['permissions'])->pluck('id')->all();
        RolePermission::query()->where('role', $role)->delete();
        RolePermission::query()->insert(array_map(fn (int $permissionId) => [
            'role' => $role,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $ids));

        return response()->json(['success' => true, 'data' => [
            'name' => $role,
            'permissions' => Permission::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->values(),
        ]]);
    }
}
