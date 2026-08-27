<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorthyEmployeeResource;
use App\Models\Employee;
use App\Models\WorthyEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorthyEmployeeController extends Controller
{
    public function index(): AnonymousResourceCollection { return WorthyEmployeeResource::collection(WorthyEmployee::with('employee.user')->latest('bonus_month')->latest()->get()); }
    public function show(WorthyEmployee $worthyEmployee): WorthyEmployeeResource { return new WorthyEmployeeResource($worthyEmployee->load('employee.user')); }

    public function store(Request $request): WorthyEmployeeResource
    {
        $data = $request->validate([
            'employeeId' => 'required|exists:employees,id', 'performanceScore' => 'required|numeric|between:1,5',
            'eligibilityReason' => ['required', Rule::in(['Top Performer', 'Project Milestone', 'Client Praise', 'Work Anniversary', 'Manager Nomination'])],
            'achievementDetails' => 'required|string', 'suggestedAmount' => 'required|numeric|min:0', 'selectedAmount' => 'nullable|numeric|min:0',
            'selectedType' => ['required', Rule::in(['Performance', 'Spot Bonus', 'Project Completion', 'Retention'])], 'bonusMonth' => 'sometimes|date_format:Y-m|before_or_equal:now',
        ]);
        if (!Employee::selectable()->whereKey($data['employeeId'])->exists()) throw ValidationException::withMessages(['employeeId' => ['business.employee_not_selectable']]);
        $suggested = (float) $data['suggestedAmount'];
        $row = WorthyEmployee::create(['employee_id' => $data['employeeId'], 'performance_score' => $data['performanceScore'], 'eligibility_reason' => $data['eligibilityReason'], 'achievement_details' => $data['achievementDetails'], 'suggested_amount' => $suggested, 'selected_amount' => (float) ($data['selectedAmount'] ?? $suggested), 'selected_type' => $data['selectedType'], 'is_granted' => false, 'bonus_month' => ($data['bonusMonth'] ?? now()->format('Y-m')).'-01']);
        return new WorthyEmployeeResource($row->load('employee.user'));
    }

    public function update(Request $request, WorthyEmployee $worthyEmployee): WorthyEmployeeResource
    {
        abort_unless($request->user()->hasPermission($request->has('isGranted') ? 'grant_bonuses' : 'edit_bonus_nominations'), 403, 'auth.forbidden');
        $data = $request->validate(['selectedAmount' => 'sometimes|required|numeric|min:0', 'selectedType' => ['sometimes', 'required', Rule::in(['Performance', 'Spot Bonus', 'Project Completion', 'Retention'])], 'isGranted' => 'sometimes|required|boolean']);
        abort_if($worthyEmployee->is_granted && $data !== [], 422, 'business.granted_bonus_immutable');
        abort_if($worthyEmployee->salaries()->exists(), 422, 'business.bonus_already_in_salary');
        if (isset($data['selectedAmount'])) $worthyEmployee->selected_amount = $data['selectedAmount'];
        if (isset($data['selectedType'])) $worthyEmployee->selected_type = $data['selectedType'];
        if (($data['isGranted'] ?? false) === true) { $worthyEmployee->is_granted = true; $worthyEmployee->granted_amount = $worthyEmployee->selected_amount; $worthyEmployee->granted_at = now('UTC'); $worthyEmployee->granted_by = $request->user()->id; }
        $worthyEmployee->save(); return new WorthyEmployeeResource($worthyEmployee->fresh()->load('employee.user'));
    }

    public function destroy(WorthyEmployee $worthyEmployee): JsonResponse { abort_if($worthyEmployee->is_granted || $worthyEmployee->salaries()->exists(), 422, 'business.granted_bonus_immutable'); $worthyEmployee->delete(); return response()->json(['message' => 'Candidate successfully dismissed.']); }
}
