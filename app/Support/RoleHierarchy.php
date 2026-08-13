<?php

namespace App\Support;

use App\Models\User;

class RoleHierarchy
{
    private const RANKS = [
        'viewer' => 100,
        'staff' => 200,
        'manager' => 300,
        'admin' => 400,
        'super_admin' => 500,
    ];

    public function rank(User $user): int
    {
        return collect($user->roleNames())
            ->map(fn (string $role) => self::RANKS[$role] ?? 0)
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
        return $requesterRank >= self::RANKS['super_admin']
            ? $reviewerRank >= self::RANKS['super_admin']
            : $reviewerRank > $requesterRank;
    }
}
