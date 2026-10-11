<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Human-readable, localized dates for printed and exported reports.
 *
 * Report payloads carry machine values (ISO-8601 UTC timestamps and `Y-m-d`
 * filters). Printing those raw strings produced output like
 * `2026-10-11T09:24:31.000000Z`, which is neither normalized nor readable.
 *
 * `ext-intl` is not installed in this project, so dates are localized through
 * Carbon's locale packs. `fa_AF` and `ps_AF` ship with Carbon; any other locale
 * falls back to English month names, which stays readable rather than failing.
 */
final class ReportDateFormatter
{
    /** Frontend/backend locale ⇒ Carbon locale pack. */
    private const CARBON_LOCALES = [
        'fa' => 'fa_AF',
        'ps' => 'ps_AF',
        'ar' => 'ar',
        'en' => 'en',
    ];

    /** Locales whose month names read naturally with a 24-hour clock. */
    private const NON_LATIN = ['fa', 'ps', 'ar'];

    /**
     * A full, readable timestamp for the report footer, in the report's own
     * timezone with an explicit offset so a printed sheet is unambiguous.
     */
    public static function human(?string $value, ?string $timezone = null): string
    {
        $date = self::parse($value);

        if (! $date) {
            return (string) $value;
        }

        if ($timezone) {
            try {
                $date = $date->setTimezone($timezone);
            } catch (Throwable) {
                // An unknown company timezone must never break a print job.
            }
        }

        $format = self::isNonLatin() ? 'j F Y، H:i' : 'M j, Y, g:i A';

        return $date->locale(self::carbonLocale())->translatedFormat($format).' (GMT'.$date->format('P').')';
    }

    /** A readable calendar day for reporting-period lines. */
    public static function day(?string $value): string
    {
        $date = self::parse($value);

        if (! $date) {
            return (string) $value;
        }

        return $date->locale(self::carbonLocale())->translatedFormat(self::isNonLatin() ? 'j F Y' : 'M j, Y');
    }

    private static function parse(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function carbonLocale(): string
    {
        return self::CARBON_LOCALES[self::baseLocale()] ?? 'en';
    }

    private static function isNonLatin(): bool
    {
        return in_array(self::baseLocale(), self::NON_LATIN, true);
    }

    private static function baseLocale(): string
    {
        $locale = strtolower((string) app()->getLocale());
        $base = str_replace('_', '-', $locale);

        return explode('-', $base)[0];
    }
}
