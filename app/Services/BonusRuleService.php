<?php

namespace App\Services;

use App\Models\BonusRuleSet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BonusRuleService
{
    public const DEFAULTS = [
        'completed_task_points' => 10,
        'priority_weights' => ['low' => 0.75, 'medium' => 1, 'high' => 1.5, 'urgent' => 2],
        'before_deadline_points' => 2,
        'after_deadline_points' => -2,
        'reopened_penalty' => -3,
        'rejected_penalty' => -5,
        'default_quality_score' => 3,
        'quality_multiplier_enabled' => true,
        'minimum_participation_percent' => 10,
        'minimum_completed_tasks' => 1,
        'monthly_score_threshold' => 10,
        'amount_per_point' => 10,
        'maximum_monthly_bonus' => 1000,
        'excluded_employee_ids' => [],
        'excluded_office_ids' => [],
    ];

    public function active(): BonusRuleSet
    {
        $active = BonusRuleSet::query()->where('is_active', true)->latest('version')->first()
            ?? BonusRuleSet::create(['rules' => self::DEFAULTS]);
        if (array_key_exists('excluded_departments', $active->rules ?? [])) {
            $active->update(['rules' => [...self::DEFAULTS, ...$active->rules, 'excluded_office_ids' => $active->rules['excluded_office_ids'] ?? []]]);
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
                'name' => 'Task bonus rules', 'version' => $current->version + 1,
                'rules' => [...self::DEFAULTS, ...$rules], 'updated_by' => $user->id,
            ]);
        });
    }
}
