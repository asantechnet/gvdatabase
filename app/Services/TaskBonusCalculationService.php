<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Models\WorthyEmployee;
use App\Support\CompanyProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskBonusCalculationService
{
    public function __construct(private readonly BonusRuleService $ruleService, private readonly CompanyProfile $company) {}

    public function calculate(string $month): array
    {
        $ruleSet = $this->ruleService->active();
        $rules = $ruleSet->rules;
        $localStart = Carbon::createFromFormat('Y-m-d', "{$month}-01", $this->company->timezone())->startOfMonth();
        $start = $localStart->copy()->utc();
        $end = $localStart->copy()->endOfMonth()->utc();
        $currency = Currency::query()->where('is_base', true)->where('is_active', true)->firstOrFail();
        $tasks = Task::query()->with(['users.employee'])->where('progress', 100)->whereBetween('completed_at', [$start, $end])->get();
        $contributions = $this->contributions($tasks, $rules);
        $created = $updated = $ineligible = 0;
        $eligibleKeys = [];

        foreach ($contributions->groupBy('employee_id') as $employeeId => $items) {
            $employee = Employee::selectable()->find($employeeId);
            if (! $employee || in_array((int) $employeeId, $rules['excluded_employee_ids'], true)
                || in_array($employee->department, $rules['excluded_departments'], true)) { $ineligible++; continue; }
            $score = round((float) $items->sum('score'), 4);
            if ($items->count() < $rules['minimum_completed_tasks'] || $score < $rules['monthly_score_threshold']) { $ineligible++; continue; }
            $amount = round(min($score * $rules['amount_per_point'], $rules['maximum_monthly_bonus']), $currency->decimal_places);
            $key = "task-bonus:{$month}:{$employeeId}";
            $eligibleKeys[] = $key;
            DB::transaction(function () use ($employee, $items, $score, $amount, $currency, $month, $key, $ruleSet, &$created, &$updated): void {
                $row = WorthyEmployee::query()->where('calculation_key', $key)->lockForUpdate()->first();
                if ($row?->is_granted || $row?->salaries()->exists()) return;
                $payload = [
                    'employee_id' => $employee->id, 'source_task_id' => null, 'is_automatic' => true, 'calculation_key' => $key,
                    'calculation_score' => $score, 'calculation_inputs' => ['tasks' => $items->values()->all(), 'formula' => 'sum(task_score) × amount_per_point, capped by maximum_monthly_bonus'],
                    'rule_snapshot' => ['id' => $ruleSet->id, 'version' => $ruleSet->version, 'rules' => $ruleSet->rules], 'calculated_at' => now('UTC'),
                    'currency_id' => $currency->id, 'currency_code' => $currency->code, 'currency_name' => $currency->name,
                    'currency_symbol' => $currency->symbol, 'currency_decimal_places' => $currency->decimal_places,
                    'performance_score' => round((float) $items->avg('quality_score'), 1), 'eligibility_reason' => 'Project Milestone',
                    'achievement_details' => $this->explanation($items, $score, $amount, $currency->code),
                    'suggested_amount' => $amount, 'suggested_rate_to_base' => $currency->rate_against_base, 'suggested_base_amount' => $amount,
                    'selected_amount' => $amount, 'selected_currency_id' => $currency->id, 'selected_currency_code' => $currency->code,
                    'selected_currency_name' => $currency->name, 'selected_currency_symbol' => $currency->symbol,
                    'selected_currency_decimal_places' => $currency->decimal_places, 'selected_rate_to_base' => $currency->rate_against_base,
                    'selected_base_amount' => $amount, 'selected_type' => 'Project Completion', 'is_granted' => false,
                    'bonus_month' => "{$month}-01", 'currency_snapshot_source' => 'recorded_at_calculation',
                    'currency_snapshot_note' => 'Calculated in the base currency. Grant approval is required before payroll inclusion.',
                ];
                if ($row) { $row->update($payload); $updated++; } else { $row = WorthyEmployee::create($payload); $created++; }
                $this->notifyReviewers($row, $month);
            });
        }

        $stale = WorthyEmployee::query()->where('is_automatic', true)->where('calculation_key', 'like', "task-bonus:{$month}:%")
            ->when($eligibleKeys !== [], fn ($query) => $query->whereNotIn('calculation_key', $eligibleKeys))
            ->where('is_granted', false)->whereDoesntHave('salaries')->get();
        foreach ($stale as $row) {
            $row->update([
                'calculation_score' => 0, 'suggested_amount' => 0, 'suggested_base_amount' => 0,
                'selected_amount' => 0, 'selected_base_amount' => 0,
                'achievement_details' => 'This nomination is no longer eligible after recalculation. The original calculation evidence remains in its audit history.',
                'calculated_at' => now('UTC'),
            ]);
        }

        return ['month' => $month, 'tasks' => $tasks->count(), 'employees_evaluated' => $contributions->pluck('employee_id')->unique()->count(), 'created' => $created, 'updated' => $updated, 'ineligible' => $ineligible, 'withdrawn' => $stale->count()];
    }

    private function contributions(Collection $tasks, array $rules): Collection
    {
        return $tasks->flatMap(function (Task $task) use ($rules) {
            $assigned = $task->users->filter(fn (User $user) => $user->employee);
            $defaultParticipation = $assigned->count() ? 100 / $assigned->count() : 0;
            return $assigned->map(function (User $user) use ($task, $rules, $defaultParticipation) {
                $participation = (float) ($user->pivot->contribution_percent ?? $defaultParticipation);
                if ($participation < $rules['minimum_participation_percent']) return null;
                $priority = (float) ($rules['priority_weights'][$task->priority] ?? 1);
                $base = $rules['completed_task_points'] * $priority;
                $timing = $task->due_date && $task->completed_at
                    ? ($task->completed_at->timezone('UTC')->startOfDay()->lte($task->due_date->startOfDay()) ? $rules['before_deadline_points'] : $rules['after_deadline_points']) : 0;
                $quality = (float) ($task->manager_quality_score ?? $rules['default_quality_score']);
                $qualityMultiplier = $rules['quality_multiplier_enabled'] ? $quality / 3 : 1;
                $reopened = $task->reopened_count * $rules['reopened_penalty'];
                $rejected = $task->rejected_count * $rules['rejected_penalty'];
                $preParticipation = max(0, ($base + $timing + $reopened + $rejected) * $qualityMultiplier);
                return [
                    'task_id' => $task->id, 'task_title' => $task->title, 'employee_id' => $user->employee->id,
                    'completed_at' => $task->completed_at?->toISOString(), 'due_date' => $task->due_date?->format('Y-m-d'),
                    'priority' => $task->priority, 'priority_weight' => $priority, 'participation_percent' => round($participation, 2),
                    'quality_score' => $quality, 'reopened_count' => $task->reopened_count, 'rejected_count' => $task->rejected_count,
                    'base_points' => $base, 'timing_points' => $timing, 'reopened_points' => $reopened, 'rejected_points' => $rejected,
                    'score' => round($preParticipation * ($participation / 100), 4),
                ];
            })->filter();
        })->values();
    }

    private function explanation(Collection $items, float $score, float $amount, string $currency): string
    {
        return sprintf('%d completed task contribution(s) produced %.2f points. Suggested bonus: %s %.2f. Priority, deadline timing, quality, reopen/rejection penalties, and participation are preserved in calculation inputs.', $items->count(), $score, $currency, $amount);
    }

    private function notifyReviewers(WorthyEmployee $nomination, string $month): void
    {
        $reviewers = User::query()->get()->filter(fn (User $user) => $user->hasPermission('grant_bonuses'))->pluck('id');
        if ($reviewers->isEmpty()) return;
        $notification = Notification::firstOrCreate(
            ['notification_key' => "bonus.review.{$month}.{$nomination->employee_id}"],
            ['type' => 'bonus', 'data' => ['nomination_id' => $nomination->id, 'month' => $month], 'date_time' => now(), 'employee_id' => $nomination->employee_id, 'go_to_url' => '/bonuslist'],
        );
        $notification->deliverTo($reviewers);
    }
}
