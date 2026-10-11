<?php

namespace App\Support;

/**
 * Payment state of a contracted service line (`applicant_contract`).
 *
 * The stored values are snake_case; the labels are the three states the
 * business asked for (Paid, Unpaid, Partially Paid). The frontend renders its
 * own translations through `labelKey()`.
 */
final class PaymentStatus
{
    public const PAID = 'paid';

    public const UNPAID = 'unpaid';

    public const PARTIALLY_PAID = 'partially_paid';

    /** @var list<string> */
    public const ALL = [self::PAID, self::UNPAID, self::PARTIALLY_PAID];

    private const LABELS = [
        self::PAID => 'Paid',
        self::UNPAID => 'Unpaid',
        self::PARTIALLY_PAID => 'Partially Paid',
    ];

    public const LABEL_KEYS = [
        self::PAID => 'finance.paymentStatus.paid',
        self::UNPAID => 'finance.paymentStatus.unpaid',
        self::PARTIALLY_PAID => 'finance.paymentStatus.partiallyPaid',
    ];

    /**
     * Derive the state from the money paid against the payable total.
     * Zero payable counts as paid: there is nothing left to collect.
     */
    public static function derive(float $amountPaid, float $payableTotal): string
    {
        $epsilon = 0.000001;

        if ($amountPaid <= $epsilon) {
            return $payableTotal <= $epsilon ? self::PAID : self::UNPAID;
        }

        return $amountPaid + $epsilon >= $payableTotal ? self::PAID : self::PARTIALLY_PAID;
    }

    public static function isValid(mixed $status): bool
    {
        return is_string($status) && in_array($status, self::ALL, true);
    }

    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? '—';
    }

    public static function labelKey(?string $status): string
    {
        return self::LABEL_KEYS[$status] ?? 'finance.paymentStatus.unpaid';
    }

    /** @return array<string, string> value ⇒ label */
    public static function labels(): array
    {
        return self::LABELS;
    }

    /** @return array<string, string> value ⇒ translation key */
    public static function labelKeys(): array
    {
        return self::LABEL_KEYS;
    }
}
