<?php

namespace App\Support;

/**
 * The four services this firm sells. They are fixed product lines, not
 * catalogue rows: the old `services` table is retired and every contract,
 * applicant and contract template names one of these values instead.
 *
 * The value is stored as a string column (`contracts.service`,
 * `applicant_contract.service`, `contract_templates.service`,
 * `applicants.service`) so reports and filters stay readable.
 */
final class BuiltInService
{
    public const SCHOLARSHIP = 'scholarship';

    public const TEST_OFFER = 'test_offer';

    public const ACADEMY = 'academy';

    public const PRIVATE = 'private';

    /** @var list<string> */
    public const ALL = [self::SCHOLARSHIP, self::TEST_OFFER, self::ACADEMY, self::PRIVATE];

    /** Human labels. The UI renders its own translations for these values. */
    private const LABELS = [
        self::SCHOLARSHIP => 'Scholarship',
        self::TEST_OFFER => 'Test Offer',
        self::ACADEMY => 'Academy',
        self::PRIVATE => 'Private',
    ];

    /** Translation keys the frontend uses, keyed by service value. */
    public const LABEL_KEYS = [
        self::SCHOLARSHIP => 'educational.builtInServices.scholarship',
        self::TEST_OFFER => 'educational.builtInServices.testOffer',
        self::ACADEMY => 'educational.builtInServices.academy',
        self::PRIVATE => 'educational.builtInServices.private',
    ];

    public static function isValid(mixed $service): bool
    {
        return is_string($service) && in_array($service, self::ALL, true);
    }

    public static function label(?string $service): string
    {
        return self::LABELS[$service] ?? '—';
    }

    public static function labelKey(?string $service): string
    {
        return self::LABEL_KEYS[$service] ?? 'educational.builtInServices.unknown';
    }

    /** @return array<string, string> service value ⇒ label */
    public static function labels(): array
    {
        return self::LABELS;
    }

    /** @return array<string, string> service value ⇒ translation key */
    public static function labelKeys(): array
    {
        return self::LABEL_KEYS;
    }
}
