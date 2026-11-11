<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Academy teachers are active employees with the teacher position or role. */
class AcademyTeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teachers = Employee::query()
            ->active()
            ->with(['position:id,title,system_key', 'user:id'])
            ->where(function ($query): void {
                $query->whereHas('position', fn ($position) => $position->where('system_key', 'teacher'))
                    ->orWhereHas('user.roles', fn ($role) => $role->where('name', 'teacher'));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'contact', 'avatar', 'position_id', 'user_id', 'is_commission_based']);

        return response()->json([
            'success' => true,
            'data' => $teachers->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'contact' => $employee->contact,
                'avatar' => $employee->avatar,
                'position' => $employee->position?->title,
                'is_commission_based' => (bool) $employee->is_commission_based,
            ])->values()->all(),
        ]);
    }
}
