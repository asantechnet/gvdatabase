<?php

namespace App\Http\Controllers;

use App\Support\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Web app manifest, served dynamically so a Settings change (name, logo,
 * theme colour) reaches clients without rebuilding the frontend.
 *
 * Static PNG fallbacks are committed at the public root so installability
 * never depends on an uploaded file.
 */
class PwaManifestController extends Controller
{
    private const DEFAULT_NAME = 'Educational Agency Portal';

    private const DEFAULT_THEME_COLOR = '#7367F0';

    private const DEFAULT_BACKGROUND_COLOR = '#FFFFFF';

    public function __invoke(CompanyProfile $profile): JsonResponse
    {
        $setting = $profile->current();

        $name = $setting?->short_company_name
            ?: $setting?->website_title
            ?: self::DEFAULT_NAME;

        $icons = [];

        if ($setting?->website_logo) {
            $icons[] = [
                'src' => Storage::disk('public')->url($setting->website_logo),
                'sizes' => 'any',
                'type' => $this->mimeType($setting->website_logo),
                'purpose' => 'any',
            ];
        }

        $icons = array_merge($icons, [
            [
                'src' => '/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/maskable-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ]);

        return response()->json([
            'name' => $name,
            'short_name' => $setting?->short_company_name ?: $name,
            'description' => $setting?->website_title ?: self::DEFAULT_NAME,
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => $setting?->primary_color ?: self::DEFAULT_THEME_COLOR,
            'background_color' => $setting?->light_background_color ?: self::DEFAULT_BACKGROUND_COLOR,
            'icons' => $icons,
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /** Uploaded logos may be PNG/JPEG/WebP/SVG; the extension is trustworthy here. */
    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }
}
