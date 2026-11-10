<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Support\RoleHierarchy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;



class RoleController extends Controller
{
    private const PROTECTED_ROLE_NAMES = ['super_admin', 'applicant'];
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('view_roles'), 403, 'auth.forbidden');

        $roles = Role::query()
            ->with(['permissions', 'users' => function ($query) {
                // Eager load only the necessary user fields and their related employee avatar
                $query->select('users.id', 'users.name')
                    ->with('employee:id,user_id,avatar');
            }])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $allPermissions = Permission::query()->orderBy('name')->get();

        $roles->each(function (Role $role) use ($allPermissions) {
            if ($role->name === 'super_admin') {
                $role->setRelation('permissions', $allPermissions);
            }
        });

        return response()->json(['success' => true, 'data' => RoleResource::collection($roles)]);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()->hasPermission('view_roles'), 403, 'auth.forbidden');

        $role->load('permissions');

        return response()->json(['success' => true, 'data' => new RoleResource($role)]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('create_roles'), 403, 'auth.forbidden');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
            'is_senior' => 'sometimes|boolean',
        ]);
        $validator->validate();
        $data = $validator->validated();
        $this->guardSeniorityChange($request, (bool) ($data['is_senior'] ?? false));

        $role = Role::create($data);

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role),
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()->hasPermission('edit_roles'), 403, 'auth.forbidden');
        abort_if(in_array($role->name, self::PROTECTED_ROLE_NAMES, true), 422, 'business.request_rejected');

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
            'is_senior' => 'sometimes|boolean',
        ]);
        $validator->validate();
        $data = $validator->validated();

        if (array_key_exists('is_senior', $data) && (bool) $data['is_senior'] !== (bool) $role->is_senior) {
            abort_if($role->name === 'super_admin', 422, 'business.request_rejected');
            $this->guardSeniorityChange($request, (bool) $data['is_senior']);
        }

        $role->update($data);

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role),
        ]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()->hasPermission('delete_roles'), 403, 'auth.forbidden');
        abort_if(in_array($role->name, self::PROTECTED_ROLE_NAMES, true), 422, 'business.request_rejected');
        abort_if($role->users()->exists(), 422, 'business.role_in_use');

        $role->delete();

        return response()->json(['success' => true, 'message' => 'Role deleted successfully.']);
    }

    /**
     * Assign which existing permissions a role has (writes the role_permissions
     * pivot). The permission catalog itself is system-owned and read-only.
     */
    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage_role_permissions'), 403, 'auth.forbidden');
        abort_if($role->name === 'super_admin', 422, 'business.request_rejected');

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,name'],
        ]);

        $ids = Permission::query()->whereIn('name', $data['permissions'])->pluck('id')->all();
        $role->permissions()->sync($ids);

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role),
        ]);
    }

    /** Changing whether a role is senior is a hierarchy decision. */
    private function guardSeniorityChange(Request $request, bool $isSenior): void
    {
        abort_unless($request->user()->hasPermission('manage_role_hierarchy'), 403, 'auth.forbidden');

        if ($isSenior) {
            abort_unless(app(RoleHierarchy::class)->isSenior($request->user()), 403, 'auth.forbidden');
        }
    }
}
