<?php

namespace App\Http\Resources\Concerns;

use App\Models\Currency;

trait PresentsCurrentBaseMoney
{
    private function currentBaseCurrency(): ?Currency
    {
        static $base = null;
        static $loaded = false;

        if (! $loaded) {
            $base = Currency::query()->where('is_base', true)->first();
            $loaded = true;
        }

        return $base;
    }

    private function currentBaseAmount(float $amount, ?Currency $source): ?float
    {
        $rate = (float) ($source?->rate ?? 0);

        return $rate > 0 ? round($amount / $rate, 4) : null;
    }

    private function currentBaseCurrencyData(): ?array
    {
        $base = $this->currentBaseCurrency();

        return $base ? [
            'id' => $base->id,
            'code' => $base->code,
            'symbol' => $base->symbol,
            'decimalPlaces' => $base->decimal_places,
        ] : null;
    }
}
