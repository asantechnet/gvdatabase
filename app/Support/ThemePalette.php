<?php

namespace App\Support;

/**
 * The colours exposed in company settings map one-to-one to Vuexy/Vuetify
 * tokens. Keeping their defaults here lets the API return a complete, usable
 * palette even for installations created before theme settings existed.
 */
final class ThemePalette
{
    public const DEFAULTS = [
        'primary_color' => '#0F766E',
        'secondary_color' => '#475569',
        'success_color' => '#15803D',
        'info_color' => '#0284C7',
        'warning_color' => '#D97706',
        'error_color' => '#DC2626',
        'light_background_color' => '#F5F7F4',
        'light_surface_color' => '#FFFEFA',
        'dark_background_color' => '#102321',
        'dark_surface_color' => '#173330',
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
