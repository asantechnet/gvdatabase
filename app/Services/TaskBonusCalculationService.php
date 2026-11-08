<?php

namespace App\Services;

use App\Models\AutomationRun;
use App\Models\CaseWin;
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
        $taskCredits = $this->taskCredits($tasks, (bool) $rules['task_quality_weighting_enabled']);
        $wins = CaseWin::query()->with('applicant:id,name')->whereBetween('won_at', [$start, $end])->get()
            ->map(fn (CaseWin $win) => ['case_win_id' => $win->id, 'applicant_id' => $win->applicant_id, 'employee_id' => $win->employee_id, 'won_at' => $win->won_at->toISOString(), 'stage_key' => $win->stage_key]);

        $employeeIds = $taskCredits->pluck('employee_id')->merge($wins->pluck('employee_id'))->unique();
        $created = $updated = $ineligible = 0;
        $eligibleKeys = [];

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::selectable()->find($employeeId);
            $employeeTasks = $taskCredits->where('employee_id', $employeeId)->values();
            $employeeWins = $wins->where('employee_id', $employeeId)->values();
            $credit = round((float) $employeeTasks->sum('credit'), 4);
            $wonCases = $employeeWins->count();

            if (! $employee || in_array((int) $employeeId, $rules['excluded_employee_ids'], true)
                || in_array((int) $employee->office_id, $rules['excluded_office_ids'] ?? [], true)
                || $credit < $rules['minimum_completed_tasks'] || $wonCases < $rules['minimum_cases_won']) {
                $ineligible++;

                continue;
            }

            $taskAmount = $credit * $rules['amount_per_completed_task'];
            $caseAmount = $wonCases * $rules['amount_per_case_won'];
            $amount = round(min($taskAmount + $caseAmount, $rules['maximum_monthly_bonus']), $currency->decimal_places);
            $key = "task-case-bonus:{$month}:{$employeeId}";
            $eligibleKeys[] = $key;

            DB::transaction(function () use ($employee, $employeeTasks, $employeeWins, $credit, $wonCases, $amount, $currency, $month, $key, $ruleSet, &$created, &$updated): void {
                $row = WorthyEmployee::query()->where('calculation_key', $key)->lockForUpdate()->first();
                if ($row?->is_granted || $row?->salaries()->exists()) {
                    return;
                }

                $payload = [
                    'employee_id' => $employee->id, 'source_task_id' => null, 'is_automatic' => true,
                    'calculation_key' => $key, 'calculation_score' => $credit + $wonCases,
                    'calculation_inputs' => [
                        'task_credit' => $credit, 'cases_won' => $wonCases,
                        'tasks' => $employeeTasks->all(), 'case_wins' => $employeeWins->all(),
                        'formula' => '(task credit × task reward) + (unique cases won × case-win reward), after both minimums are met',
                    ],
                    'rule_snapshot' => ['id' => $ruleSet->id, 'version' => $ruleSet->version, 'rules' => $ruleSet->rules],
                    'calculated_at' => now('UTC'), 'performance_score' => 3,
                    'achievement_details' => sprintf('%.2f credited task(s) and %d case(s) won. Suggested bonus: %s %.2f.', $credit, $wonCases, $currency->code, $amount),
                    'suggested_amount' => $amount, 'selected_amount' => $amount, 'selected_type' => 'Performance', 'is_granted' => false,
                    'currency_id' => $currency->id, 'base_currency_id' => $currency->id, 'currency_code' => $currency->code,
                    'currency_name' => $currency->name, 'currency_symbol' => $currency->symbol, 'currency_decimal_places' => $currency->decimal_places,
                    'base_currency_code' => $currency->code, 'rate' => 1, 'base_amount' => $amount, 'bonus_month' => "{$month}-01",
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

        $stale = WorthyEmployee::query()->where('is_automatic', true)
            ->where(function ($query) use ($month) {
                $query->where('calculation_key', 'like', "task-case-bonus:{$month}:%")->orWhere('calculation_key', 'like', "task-bonus:{$month}:%");
            })
            ->when($eligibleKeys !== [], fn ($query) => $query->whereNotIn('calculation_key', $eligibleKeys))
            ->where('is_granted', false)->whereDoesntHave('salaries')->get();
        foreach ($stale as $row) {
            $row->update(['calculation_score' => 0, 'suggested_amount' => 0, 'selected_amount' => 0,
                'achievement_details' => 'This nomination no longer meets the bonus requirements.', 'calculated_at' => now('UTC')]);
        }

        return ['month' => $month, 'tasks' => $tasks->count(), 'case_wins' => $wins->count(), 'employees_evaluated' => $employeeIds->count(),
            'created' => $created, 'updated' => $updated, 'ineligible' => $ineligible, 'withdrawn' => $stale->count()];
    }

    /**
     * Read-only projection for the task drawer. Submitted quality and
     * contribution values are applied to this task only, so the drawer can
     * show what the monthly run would produce without writing anything.
     *
     * @param  array<string|int, int|float|string>  $contributions
     * @return array<string, mixed>
     */
    public function preview(Task $task, ?float $qualityScore = null, array $contributions = []): array
    {
        $task->loadMissing('users.employee');
        $ruleSet = $this->ruleService->active();
        $rules = $ruleSet->rules;
        $timezone = $this->company->timezone();
        $month = $task->completed_at?->copy()->setTimezone($timezone)->format('Y-m')
            ?? now($timezone)->format('Y-m');
        $localStart = Carbon::createFromFormat('Y-m-d', "{$month}-01", $timezone)->startOfMonth();
        $start = $localStart->copy()->utc();
        $end = $localStart->copy()->endOfMonth()->utc();
        $currency = Currency::query()->where('is_base', true)->where('is_active', true)->first();
        $qualityWeighting = (bool) $rules['task_quality_weighting_enabled'];
        $tasks = Task::query()->with(['users.employee'])->where('progress', 100)
            ->whereBetween('completed_at', [$start, $end])->get();
        $credits = $tasks->flatMap(fn (Task $row) => $this->creditsForTask(
            $row,
            $qualityWeighting,
            $row->id === $task->id ? $qualityScore : null,
            $row->id === $task->id ? $contributions : [],
            $row->id === $task->id,
        ));
        $wins = CaseWin::query()->whereBetween('won_at', [$start, $end])->get()->countBy('employee_id');
        $nominations = WorthyEmployee::query()
            ->where('calculation_key', 'like', "task-case-bonus:{$month}:%")->get()
            ->keyBy(fn (WorthyEmployee $row) => (int) substr($row->calculation_key, (int) strrpos($row->calculation_key, ':') + 1));

        $assignees = $task->users
            ->filter(fn (User $user) => $user->employee)
            ->map(function (User $user) use ($task, $credits, $wins, $nominations, $rules, $currency): array {
                $employeeId = (int) $user->employee->id;
                $employee = Employee::selectable()->find($employeeId);
                $own = $credits->first(fn (array $credit) => (int) $credit['task_id'] === (int) $task->id
                    && (int) $credit['employee_id'] === $employeeId);
                $ownCredit = round((float) ($own['credit'] ?? 0), 4);
                $otherCredit = round((float) $credits
                    ->where('employee_id', $employeeId)
                    ->where('task_id', '!=', $task->id)
                    ->sum('credit'), 4);
                $credit = round($otherCredit + $ownCredit, 4);
                $casesWon = (int) ($wins[$employeeId] ?? 0);
                $reasons = [];
                if (! $employee) {
                    $reasons[] = 'employee_unavailable';
                }
                if (in_array($employeeId, $rules['excluded_employee_ids'], true)) {
                    $reasons[] = 'excluded_employee';
                }
                if ($employee && in_array((int) $employee->office_id, $rules['excluded_office_ids'] ?? [], true)) {
                    $reasons[] = 'excluded_office';
                }
                if ($credit < $rules['minimum_completed_tasks']) {
                    $reasons[] = 'minimum_task_credit';
                }
                if ($casesWon < $rules['minimum_cases_won']) {
                    $reasons[] = 'minimum_cases';
                }
                $rawAmount = $credit * $rules['amount_per_completed_task'] + $casesWon * $rules['amount_per_case_won'];
                $projected = $currency ? round(min($rawAmount, $rules['maximum_monthly_bonus']), $currency->decimal_places) : null;
                $nomination = $nominations->get($employeeId);

                return [
                    'employeeId' => $employeeId,
                    'userId' => (int) $user->id,
                    'name' => $employee?->name ?? $user->name,
                    'contributionPercent' => (float) ($own['contribution_percent'] ?? 0),
                    'thisTaskCredit' => $ownCredit,
                    'otherCredit' => $otherCredit,
                    'monthCredit' => $credit,
                    'casesWon' => $casesWon,
                    'eligible' => $reasons === [],
                    'reasons' => $reasons,
                    'projectedAmount' => $projected,
                    'capped' => $rawAmount > $rules['maximum_monthly_bonus'],
                    'nomination' => $nomination ? [
                        'id' => (string) $nomination->id,
                        'isGranted' => (bool) $nomination->is_granted,
                        'suggestedAmount' => (float) $nomination->suggested_amount,
                        'calculatedAt' => $nomination->calculated_at?->toISOString(),
                    ] : null,
                ];
            })->values();

        return [
            'month' => $month,
            'completedAt' => $task->completed_at?->toISOString(),
            'alreadyProcessed' => AutomationRun::query()
                ->where(['job' => 'bonus_calculation', 'period_key' => $month, 'status' => 'completed'])->exists(),
            'ruleVersion' => $ruleSet->version,
            'rules' => $rules,
            'currency' => $currency ? [
                'code' => $currency->code,
                'symbol' => $currency->symbol,
                'decimalPlaces' => $currency->decimal_places,
            ] : null,
            'assignees' => $assignees->all(),
        ];
    }

    private function taskCredits(Collection $tasks, bool $qualityWeighting): Collection
    {
        return $tasks->flatMap(fn (Task $task) => $this->creditsForTask($task, $qualityWeighting))->values();
    }

    /**
     * @param  array<string|int, int|float|string>  $contributionOverrides
     * @return Collection<int, array<string, mixed>>
     */
    private function creditsForTask(
        Task $task,
        bool $qualityWeighting,
        ?float $qualityOverride = null,
        array $contributionOverrides = [],
        bool $keepZero = false,
    ): Collection {
        $eligible = $task->users->filter(fn (User $user) => $user->employee);
        $fallback = $eligible->isEmpty() ? 0 : 100 / $eligible->count();
        $quality = $qualityOverride ?? $task->manager_quality_score;
        $qualityFactor = $this->qualityFactor($quality, $qualityWeighting);
        $revisionFactor = $this->revisionFactor((int) $task->rejected_count);

        return $eligible->map(function (User $user) use ($task, $fallback, $quality, $qualityFactor, $revisionFactor, $contributionOverrides) {
            $override = $contributionOverrides[(string) $user->id] ?? $contributionOverrides[$user->id] ?? null;
            $contribution = $override !== null
                ? (float) $override
                : ($user->pivot->contribution_percent === null ? $fallback : (float) $user->pivot->contribution_percent);

            return [
                'task_id' => $task->id, 'task_title' => $task->title, 'employee_id' => $user->employee->id,
                'contribution_percent' => $contribution, 'quality_score' => $quality,
                'credit' => round(($contribution / 100) * $qualityFactor * $revisionFactor, 4),
                'completed_at' => $task->completed_at?->toISOString(),
            ];
        })->when(! $keepZero, fn (Collection $rows) => $rows->filter(fn (array $item) => $item['credit'] > 0))->values();
    }

    private function qualityFactor(?float $score, bool $weighting): float
    {
        return $weighting ? min(1.5, max(0.5, ((float) ($score ?? 3)) / 3)) : 1.0;
    }

    private function revisionFactor(int $rejectedCount): float
    {
        return max(0.5, 1 - ($rejectedCount * 0.1));
    }

    private function notifyReviewers(WorthyEmployee $nomination, string $month): void
    {
        $reviewers = User::query()->get()->filter(fn (User $user) => $user->hasPermission('grant_bonuses'))->pluck('id');
        if ($reviewers->isEmpty()) {
            return;
        }
        $notification = Notification::firstOrCreate(
            ['notification_key' => "bonus.review.{$month}.{$nomination->employee_id}"],
            ['type' => 'bonus', 'data' => ['nomination_id' => $nomination->id, 'month' => $month, 'translation_key' => 'bonus.review'], 'date_time' => now(), 'employee_id' => $nomination->employee_id, 'go_to_url' => '/bonuslist'],
        );
        $notification->deliverTo($reviewers);
    }
}
