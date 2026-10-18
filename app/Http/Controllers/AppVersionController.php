<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * The deployed frontend build version. `public/version.json` is written by the
 * Vite build; an open client compares it with its own `__APP_VERSION__` and
 * offers a reload when they differ.
 */
class AppVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = [
            'version' => (string) config('app.version', '0.0.0'),
            'built_at' => null,
        ];

        $path = public_path('version.json');

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);

            if (is_array($decoded)) {
                $payload = array_merge($payload, array_intersect_key($decoded, $payload));
            }
        }

        return response()->json($payload, 200, ['Cache-Control' => 'no-store']);
    }
}
