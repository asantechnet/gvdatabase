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

    /** Services that own a stage workflow and an applicant case. */
    public const CASE_BEARING = [self::SCHOLARSHIP, self::PRIVATE];

    /** Services sold as a direct agreement without a case or stage workflow. */
    public const CASE_FREE = [self::TEST_OFFER, self::ACADEMY];

    /** Human labels. The UI renders its own translations for these values. */
    private const LABELS = [
        self::SCHOLARSHIP => 'Scholarship',
        self::TEST_OFFER => 'Test Offer',
        self::ACADEMY => 'Academy',
        self::PRIVATE => 'Private',
    ];

    /** Contract-category permission for each built-in service. */
    public const CONTRACT_PERMISSIONS = [
        self::SCHOLARSHIP => 'view_scholarship_contracts',
        self::TEST_OFFER => 'view_test_offer_contracts',
        self::ACADEMY => 'view_academy_contracts',
        self::PRIVATE => 'view_private_contracts',
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

    public static function isCaseBearing(?string $service): bool
    {
        return in_array($service, self::CASE_BEARING, true);
    }

    public static function isCaseFree(?string $service): bool
    {
        return in_array($service, self::CASE_FREE, true);
    }

    public static function contractPermission(?string $service): ?string
    {
        return self::CONTRACT_PERMISSIONS[$service] ?? null;
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
