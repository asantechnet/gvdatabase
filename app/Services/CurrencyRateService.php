<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for a user-supplied currency rate.
 *
 * Every portal that lets a user set a rate (contract line, receipt or expense)
 * must persist it on the currency itself, so the next screen pre-fills
 * the same rate instead of a stale one. Centralising the write here keeps the
 * "rate is saved to currencies as well" rule from being re-implemented per
 * form, and the save is audited because `Currency` is auditable.
 */
class CurrencyRateService
{
    /**
     * Persist a rate for a currency and return the stored value.
     *
     * Returns null when there is no usable rate, so callers can fall back to the
     * currency's existing value instead of writing a zero.
     */
    public function remember(int $currencyId, mixed $rate): ?float
    {
        if (! is_numeric($rate) || is_finite((float) $rate) === false || (float) $rate <= 0) {
            return null;
        }

        $value = round((float) $rate, 8);
        if ($value <= 0) {
            return null;
        }

        return DB::transaction(function () use ($currencyId, $value): ?float {
            $currency = Currency::query()->lockForUpdate()->find($currencyId);
            if (! $currency) {
                return null;
            }

            $stored = $currency->is_base ? 1.0 : $value;
            if ((float) $currency->rate !== $stored) {
                $currency->rate = $stored;
                $currency->save();
            }

            return (float) $currency->rate;
        });
    }
}
