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
        $contributions = $this->contributions($tasks);
        $created = $updated = $ineligible = 0;
        $eligibleKeys = [];

        foreach ($contributions->groupBy('employee_id') as $employeeId => $items) {
            $employee = Employee::selectable()->find($employeeId);
            if (! $employee || in_array((int) $employeeId, $rules['excluded_employee_ids'], true)
                || in_array((int) $employee->office_id, $rules['excluded_office_ids'] ?? [], true)) {
                $ineligible++;
                continue;
            }

            $completedTasks = $items->count();
            if ($completedTasks < $rules['minimum_completed_tasks']) {
                $ineligible++;
                continue;
            }

            $amount = round(min($completedTasks * $rules['amount_per_completed_task'], $rules['maximum_monthly_bonus']), $currency->decimal_places);
            $key = "task-bonus:{$month}:{$employeeId}";
            $eligibleKeys[] = $key;

            DB::transaction(function () use ($employee, $items, $completedTasks, $amount, $currency, $month, $key, $ruleSet, &$created, &$updated): void {
                $row = WorthyEmployee::query()->where('calculation_key', $key)->lockForUpdate()->first();
                if ($row?->is_granted || $row?->salaries()->exists()) {
                    return;
                }

                $payload = [
                    'employee_id' => $employee->id,
                    'source_task_id' => null,
                    'is_automatic' => true,
                    'calculation_key' => $key,
                    'calculation_score' => $completedTasks,
                    'calculation_inputs' => [
                        'tasks' => $items->values()->all(),
                        'formula' => 'completed tasks × reward per completed task (up to the monthly limit)',
                    ],
                    'rule_snapshot' => ['id' => $ruleSet->id, 'version' => $ruleSet->version, 'rules' => $ruleSet->rules],
                    'calculated_at' => now('UTC'),
                    'performance_score' => 3,
                    'achievement_details' => $this->explanation($items, $amount, $currency->code),
                    'suggested_amount' => $amount,
                    'selected_amount' => $amount,
                    'selected_type' => 'Task Completion',
                    'is_granted' => false,
                    'currency_id' => $currency->id,
                    'base_currency_id' => $currency->id,
                    'currency_code' => $currency->code,
                    'currency_name' => $currency->name,
                    'currency_symbol' => $currency->symbol,
                    'currency_decimal_places' => $currency->decimal_places,
                    'base_currency_code' => $currency->code,
                    'rate' => 1,
                    'base_amount' => $amount,
                    'bonus_month' => "{$month}-01",
                ];

                if ($row) {
                    $row->update($payload);
                    $updated++;
                } else {
                    $row = WorthyEmployee::create($payload);
                    $created++;
                }

                $this->notifyReviewers($row, $month);
            });
        }

        $stale = WorthyEmployee::query()->where('is_automatic', true)->where('calculation_key', 'like', "task-bonus:{$month}:%")
            ->when($eligibleKeys !== [], fn ($query) => $query->whereNotIn('calculation_key', $eligibleKeys))
            ->where('is_granted', false)->whereDoesntHave('salaries')->get();
        foreach ($stale as $row) {
            $row->update([
                'calculation_score' => 0,
                'suggested_amount' => 0,
                'selected_amount' => 0,
                'achievement_details' => 'This nomination no longer meets the completed-task requirement.',
                'calculated_at' => now('UTC'),
            ]);
        }

        return ['month' => $month, 'tasks' => $tasks->count(), 'employees_evaluated' => $contributions->pluck('employee_id')->unique()->count(), 'created' => $created, 'updated' => $updated, 'ineligible' => $ineligible, 'withdrawn' => $stale->count()];
    }

    private function contributions(Collection $tasks): Collection
    {
        return $tasks->flatMap(function (Task $task) {
            return $task->users->filter(fn (User $user) => $user->employee)->map(fn (User $user) => [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'employee_id' => $user->employee->id,
                'completed_at' => $task->completed_at?->toISOString(),
            ]);
        })->values();
    }

    private function explanation(Collection $items, float $amount, string $currency): string
    {
        return sprintf('%d completed task(s) this month. Suggested bonus: %s %.2f.', $items->count(), $currency, $amount);
    }

    private function notifyReviewers(WorthyEmployee $nomination, string $month): void
    {
        $reviewers = User::query()->get()->filter(fn (User $user) => $user->hasPermission('grant_bonuses'))->pluck('id');
        if ($reviewers->isEmpty()) {
            return;
        }

        $notification = Notification::firstOrCreate(
            ['notification_key' => "bonus.review.{$month}.{$nomination->employee_id}"],
            ['type' => 'bonus', 'data' => ['nomination_id' => $nomination->id, 'month' => $month], 'date_time' => now(), 'employee_id' => $nomination->employee_id, 'go_to_url' => '/bonuslist'],
        );
        $notification->deliverTo($reviewers);
    }
}
