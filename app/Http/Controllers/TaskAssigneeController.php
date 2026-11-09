<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Support\EmployeeScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Returns the minimal account data needed to assign people to a task. */
class TaskAssigneeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $scope = app(EmployeeScope::class);

        // A role grant may assign anyone; a department head may only choose the
        // members of the department they manage.
        $unrestricted = $user->hasDirectPermission('assign_task_members');
        $departmentManager = $user->hasPermission('manage_department_tasks') && $scope->isDepartmentManager($user);
        abort_unless($unrestricted || $departmentManager, 403, 'auth.forbidden');

        $query = User::query()
            ->excludePrimaryRecord()
            ->where('acc_status', 'active')
            // A deactivated employee keeps their login until it is retired, so
            // the assignee picker checks the workforce state as well. Accounts
            // without an employee record stay assignable.
            ->where(fn ($query) => $query
                ->whereDoesntHave('employee')
                ->orWhereHas('employee', fn ($employee) => $employee->where('status', Employee::STATUS_ACTIVE)))
            ->with('employee:id,user_id,avatar')
            ->orderBy('name');

        if (! $unrestricted) {
            $query->whereIn('id', $scope->managedDepartmentUserIds($user));
        }

        $users = $query->get(['id', 'name', 'email']);

        return response()->json(['success' => true, 'data' => $users->map(fn (User $assignee) => [
            'id' => (string) $assignee->id,
            'name' => $assignee->name,
            'email' => $assignee->email,
            'avatar' => $assignee->employee?->avatar,
        ])->values()]);
    }
}
