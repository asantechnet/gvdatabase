<?php

namespace App\Support;

use Illuminate\Validation\Validator;

class SettingsValidation
{
    /** Validate the combined dimensions, using saved values for partial updates. */
    public static function safeArea(Validator $validator, array $values, array $defaults, string $prefix = ''): void
    {
        foreach (['x' => 'width', 'y' => 'height'] as $offset => $size) {
            $offsetField = $prefix.'safe_'.$offset;
            $sizeField = $prefix.'safe_'.$size;
            if (! array_key_exists($offsetField, $values) && ! array_key_exists($sizeField, $values)) {
                continue;
            }
            if ($validator->errors()->has($offsetField) || $validator->errors()->has($sizeField)) {
                continue;
            }
            $position = $values[$offsetField] ?? $defaults[$offsetField] ?? 0;
            $length = $values[$sizeField] ?? $defaults[$sizeField] ?? 100;
            if (is_numeric($position) && is_numeric($length) && (float) $position + (float) $length > 100.000001) {
                foreach ([$offsetField, $sizeField] as $field) {
                    $validator->errors()->add($field, __('settings.validation.'.($size === 'width' ? 'safeWidth' : 'safeHeight'), [
                        'attribute' => __('settings.fields.'.$field),
                    ]));
                }
            }
        }
    }
}
