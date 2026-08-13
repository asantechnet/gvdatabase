<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Returns the minimal account data needed to assign people to a task. */
class TaskAssigneeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('assign_task_members'), 403, 'auth.forbidden');

        $users = User::query()
            ->where('acc_status', 'active')
            ->with('employee:id,user_id,avatar')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['success' => true, 'data' => $users->map(fn (User $user) => [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->employee?->avatar,
        ])->values()]);
    }
}
