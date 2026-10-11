<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Maps a failing Laravel validation rule to the stable `validation.*` code the
 * API returns to clients.
 *
 * Laravel reports the failing rule in `Validator::failed()` as either a studly
 * rule name (`Max`, `Between`, `ProhibitedIf`) or a fully-qualified custom rule
 * class. Turning that into `validation.max` / `validation.between` lets the
 * frontend translate the actual failure instead of a blanket
 * `validation.invalid`.
 */
class ValidationErrorCode
{
    public static function forRule(?string $rule): string
    {
        if (! $rule) {
            return 'validation.invalid';
        }

        $name = Str::snake(class_basename($rule));

        return $name === '' ? 'validation.invalid' : 'validation.'.$name;
    }
}
