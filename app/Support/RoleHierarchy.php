<?php

namespace App\Support;

use App\Models\Role;
use App\Models\Employee;
use App\Models\User;

/**
 * Resolves role seniority from the `roles.level` column.
 *
 * Levels are deliberately linear: a higher number outranks a lower one.
 * The constants below are the seed defaults and the fallback for rows that
 * predate the level column, so rank comparisons stay stable even before the
 * seeder has run.
 */
class RoleHierarchy
{
    public const LEVELS = [
        'super_admin' => 500,
        'admin' => 400,
        'manager' => 300,
        'team_lead' => 260,
        'senior_counselor' => 240,
        'finance_officer' => 220,
        'counselor' => 200,
        'case_officer' => 160,
        'staff' => 150,
        'document_officer' => 100,
        'viewer' => 50,
        'applicant' => 0,
    ];

    public function defaultLevel(string $roleName): int
    {
        return self::LEVELS[$roleName] ?? 0;
    }

    public function rank(User $user): int
    {
        return $this->rankForRoles($user->roleNames());
    }

    /** @param array<int, string> $roleNames */
    public function rankForRoles(array $roleNames): int
    {
        if ($roleNames === []) {
            return 0;
        }

        $levels = Role::query()->whereIn('name', $roleNames)->pluck('level', 'name')->all();

        return collect($roleNames)
            ->map(function (string $role) use ($levels): int {
                $level = (int) ($levels[$role] ?? 0);

                return $level > 0 ? $level : $this->defaultLevel($role);
            })
            ->max() ?? 0;
    }

    public function canReview(User $reviewer, User $requester, ?int $protectedRank = null): bool
    {
        if ($reviewer->is($requester)) {
            return false;
        }

        return $this->canReviewRank($reviewer, max($this->rank($requester), $protectedRank ?? 0));
    }

    public function canReviewRank(User $reviewer, int $requesterRank): bool
    {
        $reviewerRank = $this->rank($reviewer);

        // A protected super-administrator may only be reviewed by a different
        // super-administrator. Other equal-rank reviews are never permitted.
        return $requesterRank >= self::LEVELS['super_admin']
            ? $reviewerRank >= self::LEVELS['super_admin']
            : $reviewerRank > $requesterRank;
    }

    /**
     * True when the actor outranks (or is a super admin equivalent to) the
     * target role level. Used to stop a user granting themselves or others a
     * role at or above their own seniority.
     */
    public function canAssignLevel(User $actor, int $targetLevel): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $this->rank($actor) > $targetLevel;
    }

    /**
     * A reporting line is only meaningful when its supervisor is both an
     * authenticated staff member and strictly more senior than the employee
     * being supervised.  This deliberately uses role levels, not job titles:
     * positions describe work, while roles describe authority.
     */
    public function canSupervise(Employee $supervisor, Employee $subordinate): bool
    {
        if ((int) $supervisor->id === (int) $subordinate->id || ! $supervisor->user) {
            return false;
        }

        $supervisorRank = $this->rank($supervisor->user);
        if ($supervisorRank === 0 || ! $supervisor->user->hasPermission('view_subordinate_records')) {
            return false;
        }

        // Employees without an account receive the same baseline rank as the
        // staff account the employee workflow would create for them.
        $subordinateRank = $subordinate->user
            ? $this->rank($subordinate->user)
            : self::LEVELS['staff'];

        return $supervisorRank > $subordinateRank;
    }
}
