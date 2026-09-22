<?php

namespace App\Services;

use App\Models\BonusRuleSet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BonusRuleService
{
    public const DEFAULTS = [
        'minimum_completed_tasks' => 1,
        'amount_per_completed_task' => 10,
        'minimum_cases_won' => 1,
        'amount_per_case_won' => 100,
        'task_quality_weighting_enabled' => true,
        'maximum_monthly_bonus' => 1000,
        'excluded_employee_ids' => [],
        'excluded_office_ids' => [],
    ];

    public function active(): BonusRuleSet
    {
        $active = BonusRuleSet::query()->where('is_active', true)->latest('version')->first()
            ?? BonusRuleSet::create(['rules' => self::DEFAULTS]);
        $normalised = $this->normalise($active->rules ?? []);
        if ($active->rules !== $normalised) {
            $active->update(['rules' => $normalised]);
        }

        return $active;
    }

    public function update(array $rules, User $user): BonusRuleSet
    {
        return DB::transaction(function () use ($rules, $user): BonusRuleSet {
            $current = BonusRuleSet::query()->where('is_active', true)->lockForUpdate()->latest('version')->first()
                ?? BonusRuleSet::create(['rules' => self::DEFAULTS]);
            $current->update(['is_active' => false]);

            return BonusRuleSet::create([
                'name' => 'Task and case-win bonus rules', 'version' => $current->version + 1,
                'rules' => $this->normalise($rules), 'updated_by' => $user->id,
            ]);
        });
    }

    private function normalise(array $rules): array
    {
        return [
            ...self::DEFAULTS,
            'minimum_completed_tasks' => (int) ($rules['minimum_completed_tasks'] ?? self::DEFAULTS['minimum_completed_tasks']),
            // Preserve the practical value from legacy rule sets while dropping
            // the old scoring formula and all of its hidden multipliers.
            'amount_per_completed_task' => (float) ($rules['amount_per_completed_task'] ?? $rules['amount_per_point'] ?? self::DEFAULTS['amount_per_completed_task']),
            'minimum_cases_won' => (int) ($rules['minimum_cases_won'] ?? self::DEFAULTS['minimum_cases_won']),
            'amount_per_case_won' => (float) ($rules['amount_per_case_won'] ?? self::DEFAULTS['amount_per_case_won']),
            'task_quality_weighting_enabled' => (bool) ($rules['task_quality_weighting_enabled'] ?? self::DEFAULTS['task_quality_weighting_enabled']),
            'maximum_monthly_bonus' => (float) ($rules['maximum_monthly_bonus'] ?? self::DEFAULTS['maximum_monthly_bonus']),
            'excluded_employee_ids' => array_values($rules['excluded_employee_ids'] ?? []),
            'excluded_office_ids' => array_values($rules['excluded_office_ids'] ?? $rules['excluded_departments'] ?? []),
        ];
    }
}
