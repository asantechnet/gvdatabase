<?php

namespace App\Http\Controllers;

use App\Models\WorthyEmployee;
use App\Models\Employee;
use App\Http\Resources\WorthyEmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WorthyEmployeeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return WorthyEmployeeResource::collection(
            WorthyEmployee::with('employee')->orderBy('bonus_month', 'desc')->orderBy('created_at', 'desc')->get()
        );
    }

    public function store(Request $request): WorthyEmployeeResource
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:employees,id',
            'performanceScore' => 'required|numeric|between:1.0,5.0',
            'eligibilityReason' => ['required', Rule::in([
                'Top Performer', 'Project Milestone', 'Client Praise', 'Work Anniversary', 'Manager Nomination'
            ])],
            'achievementDetails' => 'required|string',
            'suggestedAmount' => 'required|numeric|min:0',
            'selectedAmount' => 'nullable|numeric|min:0',
            'selectedType' => ['required', Rule::in([
                'Performance', 'Spot Bonus', 'Project Completion', 'Retention'
            ])],
            'bonusMonth' => 'sometimes|date_format:Y-m|before_or_equal:now',
        ]);

        $employee = Employee::findOrFail($validated['employeeId']);

        $bonusMonth = ($validated['bonusMonth'] ?? now()->format('Y-m')) . '-01';

        $worthyEmployee = WorthyEmployee::create([
            'employee_id' => $validated['employeeId'],
            'performance_score' => $validated['performanceScore'],
            'eligibility_reason' => $validated['eligibilityReason'],
            'achievement_details' => $validated['achievementDetails'],
            'suggested_amount' => $validated['suggestedAmount'],
            'selected_amount' => $validated['selectedAmount'] ?? $validated['suggestedAmount'],
            'selected_type' => $validated['selectedType'],
            'is_granted' => false,
            'bonus_month' => $bonusMonth,
        ]);

        return new WorthyEmployeeResource($worthyEmployee);
    }

    public function show(WorthyEmployee $worthyEmployee): WorthyEmployeeResource
    {
        return new WorthyEmployeeResource($worthyEmployee->load('employee'));
    }

    public function update(Request $request, WorthyEmployee $worthyEmployee): WorthyEmployeeResource
    {
        $validated = $request->validate([
            'selectedAmount' => 'sometimes|required|numeric|min:0',
            'selectedType' => ['sometimes', 'required', Rule::in([
                'Performance', 'Spot Bonus', 'Project Completion', 'Retention'
            ])],
            'isGranted' => 'sometimes|required|boolean',
        ]);

        if (isset($validated['selectedAmount'])) {
            $worthyEmployee->selected_amount = $validated['selectedAmount'];
        }
        if (isset($validated['selectedType'])) {
            $worthyEmployee->selected_type = $validated['selectedType'];
        }
        if (isset($validated['isGranted'])) {
            $worthyEmployee->is_granted = $validated['isGranted'];
        }

        $worthyEmployee->save();

        return new WorthyEmployeeResource($worthyEmployee);
    }

    public function destroy(WorthyEmployee $worthyEmployee): JsonResponse
    {
        $worthyEmployee->delete();

        return response()->json([
            'message' => 'Candidate successfully dismissed.'
        ]);
    }
}
