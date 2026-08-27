<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

class ApiMessage
{
    public static function forCode(string $code): string
    {
        $key = "api.messages.{$code}";

        if (Lang::has($key)) {
            return __($key);
        }

        $key = "api.errors.{$code}";

        if (Lang::has($key)) {
            return __($key);
        }

        $namespace = explode('.', $code)[0] ?? 'server';
        $fallback = "api.errors.{$namespace}.default";

        return Lang::has($fallback) ? __($fallback) : __('api.errors.server.default');
    }
}
