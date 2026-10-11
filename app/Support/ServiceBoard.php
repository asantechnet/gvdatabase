<?php

namespace App\Support;

use App\Models\Board;

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

    /** Deterministic board order used by the seeder. */
    public const ORDER = [
        'Private Contracts',
        'Scholarship',
        'Test Offer',
        'Academy',
        self::PERSONAL,
    ];

    /** The board title a service's delivery work belongs on. */
    public static function titleFor(?string $service): string
    {
        return self::BY_SERVICE[$service] ?? self::PERSONAL;
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
     * Resolve a board on demand without disturbing an existing board's order.
     * Used by the workflow services so a fresh install needs no seeded boards.
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
