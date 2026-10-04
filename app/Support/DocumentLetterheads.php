<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves the letterhead artwork used by the printable documents that are not
 * contracts: receipts, invoices and payslips.
 *
 * Keeping the resolution here means the settings API (preview) and every print
 * read path agree on which image is shown and where the auto-filled text is
 * allowed to land. See RECEIPTS_AND_DOCUMENT_LETTERHEADS_PLAN.md.
 */
class DocumentLetterheads
{
    public const CONTRACT = 'contract';
    public const CUSTOM = 'custom';
    public const NONE = 'none';

    /** letterhead sources offered for receipts and invoices. */
    public const SOURCES = [self::NONE, self::CONTRACT, self::CUSTOM];

    /** @var array<string, array{0: float, 1: float, 2: float, 3: float}> */
    public const SAFE_DEFAULTS = [
        'receipt' => [6.00, 18.00, 88.00, 64.00],
        'invoice' => [8.50, 15.00, 83.00, 70.00],
        'payslip' => [8.00, 14.00, 84.00, 72.00],
    ];

    /**
     * @return array{source: string, url: string|null, raw: string|null, safe: array{x: float, y: float, width: float, height: float}}
     */
    public static function resolve(?Setting $setting, string $type): array
    {
        $defaults = self::SAFE_DEFAULTS[$type] ?? self::SAFE_DEFAULTS['receipt'];
        $source = self::normalizeSource($setting?->{"{$type}_letterhead_source"});
        $raw = $setting?->{"{$type}_letterhead"};

        $path = match ($source) {
            self::CONTRACT => $setting?->contract_letterhead,
            self::CUSTOM => $raw,
            default => null,
        };

        return [
            'source' => $source,
            'url' => $path ? Storage::url($path) : null,
            'raw' => $raw,
            'safe' => [
                'x' => (float) ($setting?->{"{$type}_letterhead_safe_x"} ?? $defaults[0]),
                'y' => (float) ($setting?->{"{$type}_letterhead_safe_y"} ?? $defaults[1]),
                'width' => (float) ($setting?->{"{$type}_letterhead_safe_width"} ?? $defaults[2]),
                'height' => (float) ($setting?->{"{$type}_letterhead_safe_height"} ?? $defaults[3]),
            ],
        ];
    }

    public static function normalizeSource(?string $source): string
    {
        return in_array($source, self::SOURCES, true) ? $source : self::CUSTOM;
    }
}
