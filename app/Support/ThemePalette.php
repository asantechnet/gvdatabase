<?php

namespace App\Support;

/**
 * The colours exposed in company settings map to editable Vuexy/Vuetify
 * brand and surface tokens. Semantic status colours intentionally remain
 * fixed in the frontend theme utility so their meaning stays consistent.
 */
final class ThemePalette
{
    public const DEFAULTS = [
        'primary_color' => '#0F766E',
        'secondary_color' => '#475569',
        'light_background_color' => '#F5F7F4',
        'light_surface_color' => '#FFFEFA',
        'dark_background_color' => '#111313',
        'dark_surface_color' => '#1A1B1B',
    ];

    public static function fields(): array
    {
        return array_keys(self::DEFAULTS);
    }

    public static function value(string $field, ?string $value): string
    {
        return $value ?: self::DEFAULTS[$field];
    }
}
