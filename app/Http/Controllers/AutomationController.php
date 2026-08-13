<?php

namespace App\Http\Controllers;

use App\Models\AutomationRun;
use App\Models\DailyAnalytics;
use App\Models\MonthlyAnalytics;
use App\Services\BonusRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function bonusRules(BonusRuleService $rules): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $rules->active()]);
    }

    public function updateBonusRules(Request $request, BonusRuleService $rules): JsonResponse
    {
        $validated = $request->validate([
            'completed_task_points' => ['required', 'numeric', 'min:0'],
            'priority_weights' => ['required', 'array:low,medium,high,urgent'],
            'priority_weights.*' => ['numeric', 'min:0'],
            'before_deadline_points' => ['required', 'numeric'],
            'after_deadline_points' => ['required', 'numeric'],
            'reopened_penalty' => ['required', 'numeric', 'max:0'],
            'rejected_penalty' => ['required', 'numeric', 'max:0'],
            'default_quality_score' => ['required', 'numeric', 'between:1,5'],
            'quality_multiplier_enabled' => ['required', 'boolean'],
            'minimum_participation_percent' => ['required', 'numeric', 'between:0,100'],
            'minimum_completed_tasks' => ['required', 'integer', 'min:1'],
            'monthly_score_threshold' => ['required', 'numeric', 'min:0'],
            'amount_per_point' => ['required', 'numeric', 'min:0'],
            'maximum_monthly_bonus' => ['required', 'numeric', 'min:0'],
            'excluded_employee_ids' => ['present', 'array'],
            'excluded_employee_ids.*' => ['integer', 'exists:employees,id'],
            'excluded_departments' => ['present', 'array'],
            'excluded_departments.*' => ['string', 'max:255'],
        ]);
        return response()->json(['success' => true, 'data' => $rules->update($validated, $request->user())]);
    }

    public function runs(Request $request): JsonResponse
    {
        $filters = $request->validate(['job' => ['nullable', 'string', 'max:80'], 'status' => ['nullable', 'in:running,completed,failed']]);
        $rows = AutomationRun::query()->when($filters['job'] ?? null, fn($q, $job) => $q->where('job', $job))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))->latest('started_at')->limit(200)->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'year' => ['nullable', 'integer', 'between:2000,3000']]);
        $daily = DailyAnalytics::query()->when($filters['from'] ?? null, fn($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn($q, $date) => $q->whereDate('date', '<=', $date))->orderBy('date')->get();
        $monthly = MonthlyAnalytics::query()->when($filters['year'] ?? null, fn($q, $year) => $q->where('year', $year))->orderBy('year')->orderBy('month')->get();
        return response()->json(['success' => true, 'data' => ['daily' => $daily, 'monthly' => $monthly]]);
    }
}
