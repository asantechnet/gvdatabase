<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;

/**
 * Resolves authority from the role seniority flag.
 *
 * A role is either senior or junior. Senior employees may manage departments
 * and supervise the work of junior employees; permissions still decide which
 * actions each role can perform.
 */
class RoleHierarchy
{
    /** Legacy seed defaults, retained only to seed/repair the is_senior flag. */
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
        'teacher' => 40,
        'applicant' => 0,
    ];

    public function defaultLevel(string $roleName): int
    {
        return self::LEVELS[$roleName] ?? 0;
    }

    public function isSeniorRole(Role $role): bool
    {
        return $role->name === 'super_admin' || (bool) $role->is_senior;
    }

    public function isSenior(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->roles()->where('is_senior', true)->exists();
    }

    /** Legacy numeric rank used by older callers and audit tooling. */
    public function rank(User $user): int
    {
        return $user->isSuperAdmin() ? self::LEVELS['super_admin'] : ($this->isSenior($user) ? 200 : 100);
    }

    /** @param array<int, string> $roleNames */
    public function rankForRoles(array $roleNames): int
    {
        if (in_array('super_admin', $roleNames, true)) {
            return self::LEVELS['super_admin'];
        }

        return Role::query()->whereIn('name', $roleNames)->where('is_senior', true)->exists() ? 200 : 100;
    }

    public function canReview(User $reviewer, User $requester, ?int $protectedRank = null): bool
    {
        if ($reviewer->is($requester)) {
            return false;
        }

        if ($requester->isSuperAdmin()) {
            return $reviewer->isSuperAdmin();
        }

        return $this->isSenior($reviewer) && ! $this->isSenior($requester);
    }

    public function canReviewRank(User $reviewer, int $requesterRank): bool
    {
        $reviewerRank = $this->rank($reviewer);

        return $requesterRank >= self::LEVELS['super_admin']
            ? $reviewerRank >= self::LEVELS['super_admin']
            : $reviewerRank > $requesterRank;
    }

    public function canAssignRole(User $actor, Role $role): bool
    {
        if ($role->name === 'super_admin') {
            return $actor->isSuperAdmin();
        }

        return ! $role->is_senior || $actor->isSuperAdmin() || $this->isSenior($actor);
    }

    /** Legacy level guard; new callers should use canAssignRole(). */
    public function canAssignLevel(User $actor, int $targetLevel): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $this->isSenior($actor) && $targetLevel < 200;
    }

    public function canSupervise(Employee $supervisor, Employee $subordinate): bool
    {
        if ((int) $supervisor->id === (int) $subordinate->id || ! $supervisor->user) {
            return false;
        }

        return $this->isSenior($supervisor->user)
            && $supervisor->user->hasPermission('view_subordinate_records');
    }
}
