<?php

namespace App\Support;

use App\Models\Board;
use App\Models\User;

/**
 * Single source of truth for the operational Kanban boards.
 *
 * Each built-in service owns exactly one delivery board, and work that is not
 * tied to a service lives on the Personal board. Seeders and the
 * contract-delivery automation both resolve titles through this class so the
 * two can never drift apart and re-seeding can never create duplicates.
 */
final class ServiceBoard
{
    /** Default board for manually-created tasks. */
    public const PERSONAL = 'Personal';

    /** Operational board for applicant-stage work (created on demand). */
    public const APPLICANT_CASES = 'Applicant Cases';

    /** @var array<string, string> service value ⇒ board title */
    private const BY_SERVICE = [
        BuiltInService::PRIVATE => 'Private Contracts',
        BuiltInService::SCHOLARSHIP => 'Scholarship',
        BuiltInService::TEST_OFFER => 'Test Offer',
        BuiltInService::ACADEMY => 'Academy',
    ];

    /**
     * Deterministic order for the seeded **shared** boards. The Personal board
     * is intentionally absent: it is private per user and created on demand.
     */
    public const ORDER = [
        'Private Contracts',
        'Scholarship',
        'Test Offer',
        'Academy',
    ];

    /** @return list<string> */
    public static function serviceTitles(): array
    {
        return array_values(self::BY_SERVICE);
    }

    public static function serviceForTitle(string $title): ?string
    {
        $service = array_search($title, self::BY_SERVICE, true);

        return $service === false ? null : $service;
    }

    /** @return list<string> */
    public static function titlesVisibleTo(User $user): array
    {
        $titles = [];
        foreach (self::BY_SERVICE as $service => $title) {
            if ($user->hasPermission(BuiltInService::contractPermission($service))) {
                $titles[] = $title;
            }
        }

        return $titles;
    }

    /**
     * The shared board title a service's delivery work belongs on. Work with no
     * resolvable service is applicant-stage work; it must never fall back to the
     * private Personal board.
     */
    public static function titleFor(?string $service): string
    {
        return self::BY_SERVICE[$service] ?? self::APPLICANT_CASES;
    }

    /** @return list<string> */
    public static function titles(): array
    {
        return self::ORDER;
    }

    /**
     * Idempotently create (or restore) a board and keep its order stable.
     * Attributes are assigned directly because `deleted_at` is not fillable.
     */
    public static function ensure(string $title, int $order): Board
    {
        $board = Board::withTrashed()->firstOrNew(['title' => $title]);
        $board->order = $order;
        $board->deleted_at = null;
        $board->save();

        return $board;
    }

    /**
     * The user's private personal board, created (or restored) on demand.
     * Ownership, not board membership, is what grants access to it.
     */
    public static function personalFor(User $user): Board
    {
        $board = Board::withTrashed()->firstOrNew([
            'user_id' => $user->id,
            'title' => self::PERSONAL,
        ]);
        if (! $board->exists) {
            $board->order = (Board::query()->max('order') ?? -1) + 1;
        }
        $board->deleted_at = null;
        $board->save();

        return $board;
    }

    /**
     * Resolve a shared board on demand without disturbing an existing board's
     * order. Used by the workflow services so a fresh install needs no seeded
     * boards.
     */
    public static function firstOrCreateFor(string $title): Board
    {
        $board = Board::withTrashed()->firstOrNew(['title' => $title]);
        if (! $board->exists) {
            $board->order = (Board::query()->max('order') ?? -1) + 1;
        }
        $board->deleted_at = null;
        $board->save();

        return $board;
    }
}
