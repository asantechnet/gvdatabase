<?php

namespace App\Services\Contracts;

/**
 * Minimal HTML sanitizer for contract content produced by the TipTap editor.
 *
 * Contract documents are trusted business content, but they are still
 * rendered inside the SPA and in print popups, so scripts, event handlers,
 * javascript: URLs and inline frames must never survive persistence.
 */
class ContentSanitizer
{
    private const FORBIDDEN_TAGS = 'script|iframe|object|embed|form|link|meta';

    public function clean(string $html): string
    {
        $html = preg_replace('#<\s*('.self::FORBIDDEN_TAGS.')(?=[\s>/])[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? '';
        $html = preg_replace('#<\s*(/?\s*'.self::FORBIDDEN_TAGS.')(?=[\s>/])[^>]*/?>#is', '', $html) ?? '';

        // Strip on* event handler attributes (onclick=..., onclick='...', unquoted).
        $html = preg_replace('/\son[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

        // Neutralize javascript:/vbscript: and data:text/html URLs.
        $html = preg_replace('/(href|src)\s*=\s*(["\']?)\s*(javascript|vbscript|data\s*:\s*text\/html)[^"\'>]*\2/i', '$1="#"', $html) ?? '';

        return $html;
    }
}
