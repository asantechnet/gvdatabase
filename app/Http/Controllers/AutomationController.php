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
            'minimum_completed_tasks' => ['required', 'integer', 'min:1'],
            'amount_per_completed_task' => ['required', 'numeric', 'min:0'],
            'minimum_cases_won' => ['required', 'integer', 'min:1'],
            'amount_per_case_won' => ['required', 'numeric', 'min:0'],
            'task_quality_weighting_enabled' => ['required', 'boolean'],
            'maximum_monthly_bonus' => ['required', 'numeric', 'min:0'],
            // Older saved rule sets have no exclusion lists. Do not make an
            // upgrade reject an otherwise valid simple rule update.
            'excluded_employee_ids' => ['sometimes', 'array'],
            'excluded_employee_ids.*' => ['integer', 'exists:employees,id'],
            'excluded_office_ids' => ['sometimes', 'array'],
            'excluded_office_ids.*' => ['integer', 'exists:offices,id'],
        ]);
        $validated = [...$rules->active()->rules, ...$validated];

        return response()->json(['success' => true, 'data' => $rules->update($validated, $request->user())]);
    }

    public function runs(Request $request): JsonResponse
    {
        $filters = $request->validate(['job' => ['nullable', 'string', 'max:80'], 'status' => ['nullable', 'in:running,completed,failed']]);
        $rows = AutomationRun::query()->when($filters['job'] ?? null, fn ($q, $job) => $q->where('job', $job))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest('started_at')->limit(200)->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'year' => ['nullable', 'integer', 'between:2000,3000']]);
        $daily = DailyAnalytics::query()->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))->orderBy('date')->get();
        $monthly = MonthlyAnalytics::query()->when($filters['year'] ?? null, fn ($q, $year) => $q->where('year', $year))->orderBy('year')->orderBy('month')->get();

        return response()->json(['success' => true, 'data' => ['daily' => $daily, 'monthly' => $monthly]]);
    }
}
