<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Maps the SPA's BCP 47 tags (`fa-AF`) onto Laravel's language packs (`fa`).
 *
 * `config/localization.php` is the single source of truth and the same mapping
 * is needed in two places: the request middleware, and the printable/exported
 * reports, which must be rendered in the language the user is currently using
 * even when the print document is fetched as a separate request.
 */
final class AcceptedLocale
{
    /** @return array<string, string> frontend tag ⇒ Laravel language code */
    public static function all(): array
    {
        return config('localization.accepted_locales', []);
    }

    /** Resolve an accepted tag, tolerating case and `-`/`_` differences. */
    public static function resolve(?string $tag): ?string
    {
        if (! is_string($tag) || trim($tag) === '') {
            return null;
        }

        $normalized = fn (string $value): string => strtolower(str_replace('_', '-', $value));

        foreach (self::all() as $accepted => $language) {
            if ($normalized((string) $accepted) === $normalized($tag)) {
                return $language;
            }
        }

        return null;
    }

    /** Switch the current request to a language, reporting whether it matched. */
    public static function apply(?string $tag): bool
    {
        $language = self::resolve($tag);

        if ($language === null) {
            return false;
        }

        app()->setLocale($language);

        return true;
    }

    /**
     * Resolve the request language: an explicit `?locale=` tag first (the SPA
     * knows its own locale), then `Accept-Language`, then the configured
     * default.
     */
    public static function fromRequest(Request $request): void
    {
        app()->setLocale(config('app.locale'));

        if (self::apply($request->query('locale'))) {
            return;
        }

        $accepted = array_map('strval', array_keys(self::all()));

        if ($accepted === []) {
            return;
        }

        $preferred = $request->getPreferredLanguage($accepted);
        self::apply(is_string($preferred) ? $preferred : null);
    }
}
