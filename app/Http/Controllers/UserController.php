<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleHierarchy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'view_users');
        $users = User::query()->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => UserResource::collection($users)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'create_users');
        $this->authorizePermission($request, 'assign_user_roles');
        $data = $this->validated($request);
        if (in_array('super_admin', $data['roles'], true) && ! $request->user()->isSuperAdmin()) {
            abort(403, 'auth.forbidden');
        }
        $this->guardRoleAssignment($request, $data['roles']);
        $roles = $data['roles'];
        unset($data['roles']);
        // A supplied password creates a ready-to-use account: the email is
        // marked verified and no invitation is mailed. Without one the user is
        // invited and chooses their own password after verifying.
        $password = $data['password'] ?? null;
        unset($data['password']);
        if ($password) {
            $data['password'] = Hash::make($password);
            $data['must_set_password'] = false;
            $data['email_verified_at'] = now();
        } else {
            $data['password'] = Hash::make(Str::random(64));
            $data['must_set_password'] = true;
        }
        $user = User::create($data);
        $user->syncRoles($roles);
        if (! $password) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['success' => true, 'data' => new UserResource($user->fresh())], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizePermission($request, 'view_users');

        return response()->json(['success' => true, 'data' => new UserResource($user)]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizePermission($request, 'edit_users');
        $this->guardTargetRank($request, $user);
        $data = $this->validated($request, $user);
        if (array_key_exists('acc_status', $data)
            && ($user->acc_status === 'deactivated' || $data['acc_status'] === 'deactivated')
            && $data['acc_status'] !== $user->acc_status) {
            abort(422, 'business.use_account_reactivation_workflow');
        }
        if ($request->has('roles')) {
            $this->authorizePermission($request, 'assign_user_roles');
            $this->guardSuperAdminAssignment($request, $user, $data);
            $this->guardRoleAssignment($request, $data['roles'] ?? []);
        }
        $this->guardCurrentSuperAdmin($request, $user, $data);
        $this->guardLastSuperAdminRole($user, $data);
        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;
        if ($emailChanged) {
            $data['email_verified_at'] = null;
        }
        $roles = $data['roles'] ?? null;
        unset($data['roles']);
        $user->update($data);
        if ($user->acc_status !== 'active') {
            $user->tokens()->delete();
        }
        if ($roles !== null) {
            $user->syncRoles($roles);
        }
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['success' => true, 'data' => new UserResource($user->fresh())]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizePermission($request, 'delete_users');
        abort_if((int) $user->id === User::primaryRecordId(), 422, 'business.primary_account_protected');
        $this->guardSuperAdminTarget($request, $user);
        $this->guardTargetRank($request, $user);
        abort_if($user->is($request->user()), 422, 'business.cannot_delete_current_user');
        DB::transaction(function () use ($user): void {
            abort_if($this->isLastSuperAdmin($user, true), 422, 'business.last_super_admin_required');
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $this->authorizePermission($request, 'manage_user_status');
        $this->guardSuperAdminTarget($request, $user);
        $this->guardTargetRank($request, $user);
        abort_if($user->acc_status === 'deactivated', 422, 'business.use_account_reactivation_workflow');
        $data = $request->validate(['acc_status' => ['required', Rule::in(['active', 'inactive'])]]);
        $this->guardCurrentSuperAdmin($request, $user, $data);
        DB::transaction(function () use ($user, $data): void {
            abort_if($data['acc_status'] !== 'active' && $this->isLastSuperAdmin($user, true), 422, 'business.last_super_admin_required');
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->update($data);
            if ($user->acc_status !== 'active') {
                $user->tokens()->delete();
            }
        });
        $user->refresh();

        return response()->json(['success' => true, 'data' => new UserResource($user)]);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => [$user ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$user ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => [$user ? 'sometimes' : 'required', 'nullable', 'string', 'max:20'],
            'address' => [$user ? 'sometimes' : 'required', 'nullable', 'string', 'max:2000'],
            'roles' => [$user ? 'sometimes' : 'required', 'array', 'min:1'],
            'roles.*' => [Rule::exists('roles', 'name')],
            'acc_status' => [
                $user ? 'sometimes' : 'required',
                Rule::in($user ? ['active', 'inactive', 'deactivated'] : ['active', 'inactive']),
            ],
        ];
        // A password may only be supplied when creating the account. An edit has
        // no rule for it at all, so the field is dropped from the validated data
        // and can never overwrite a stored credential.
        if (! $user) {
            $rules['password'] = ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'];
        }

        return $request->validate($rules);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403, 'auth.forbidden');
    }

    private function isLastSuperAdmin(User $user, bool $lock = false): bool
    {
        if (! $user->isSuperAdmin()) {
            return false;
        }

        $query = User::query()->where('acc_status', 'active')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->filter(fn (User $candidate) => $candidate->isSuperAdmin())
            ->count() <= 1;
    }

    private function guardCurrentSuperAdmin(Request $request, User $user, array $data): void
    {
        if ($user->is($request->user()) && (isset($data['roles']) && ! in_array('super_admin', $data['roles'], true))) {
            abort(422, 'business.cannot_remove_current_super_admin_role');
        }
    }

    /** Never let an edit remove the only active super-admin role. */
    private function guardLastSuperAdminRole(User $user, array $data): void
    {
        if (! array_key_exists('roles', $data) || in_array('super_admin', $data['roles'], true)) {
            return;
        }

        abort_if($this->isLastSuperAdmin($user), 422, 'business.last_super_admin_required');
    }

    private function guardSuperAdminTarget(Request $request, User $user): void
    {
        if ($user->isSuperAdmin() && ! $request->user()->isSuperAdmin()) {
            abort(403, 'auth.forbidden');
        }
    }

    private function guardSuperAdminAssignment(Request $request, User $user, array $data): void
    {
        $this->guardSuperAdminTarget($request, $user);
        if (in_array('super_admin', $data['roles'] ?? [], true) && ! $request->user()->isSuperAdmin()) {
            abort(403, 'auth.forbidden');
        }
    }

    /**
     * Senior roles may only be granted by a senior actor; only a super admin
     * may grant the super-admin role.
     *
     * @param  array<int, string>  $roleNames
     */
    private function guardRoleAssignment(Request $request, array $roleNames): void
    {
        $hierarchy = app(RoleHierarchy::class);
        $roles = Role::query()->whereIn('name', $roleNames)->get();

        foreach ($roles as $role) {
            abort_unless($hierarchy->canAssignRole($request->user(), $role), 403, 'auth.forbidden');
        }
    }

    /** A user may not edit or deactivate a senior employee unless they are senior too. */
    private function guardTargetRank(Request $request, User $user): void
    {
        $actor = $request->user();
        if ($actor->isSuperAdmin() || $actor->is($user)) {
            return;
        }

        abort_unless(app(RoleHierarchy::class)->isSenior($actor), 403, 'auth.forbidden');
    }
}
