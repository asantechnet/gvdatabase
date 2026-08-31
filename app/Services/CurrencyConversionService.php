<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Validation\ValidationException;

/**
 * One authoritative definition for the application's exchange-rate convention:
 *
 *     1 base-currency unit = currency.rate units of that currency.
 */
class CurrencyConversionService
{
    public function currentBase(): Currency
    {
        return Currency::query()->where('is_base', true)->firstOrFail();
    }

    public function rate(Currency $currency): float
    {
        $rate = (float) $currency->rate;

        if ($rate <= 0) {
            throw ValidationException::withMessages([
                'currency_id' => ['business.currency_rate_invalid'],
            ]);
        }

        return $rate;
    }

    public function convert(float $amount, Currency $source, Currency $target, int $precision = 4): float
    {
        if ($source->is($target)) {
            return round($amount, $precision);
        }

        return round(($amount / $this->rate($source)) * $this->rate($target), $precision);
    }

    public function toCurrentBase(float $amount, Currency $source, int $precision = 4): float
    {
        return $this->convert($amount, $source, $this->currentBase(), $precision);
    }

    /** @return array<string, int|float|string> */
    public function snapshot(Currency $currency, ?Currency $base = null): array
    {
        $base ??= $this->currentBase();

        return [
            'base_currency_id' => $base->id,
            'cnv_curr_shrt' => $currency->code,
            'base_curr_shrt' => $base->code,
            'rate' => $this->rate($currency),
        ];
    }
}
