import { ref } from "vue";
import {
  currencyCode,
  currencyDecimalPlaces,
  formatMoney,
  type CurrencyFormatSource,
} from "@/utils/money";

export interface DisplayCurrency extends CurrencyFormatSource {
  name?: string;
  rate?: number | null;
  isBase?: boolean;
  isActive?: boolean;
}

const storageKey = "educational-db-display-currency";

const storedCurrency = (): DisplayCurrency | null => {
  if (typeof window === "undefined") return null;

  try {
    const value = JSON.parse(window.localStorage.getItem(storageKey) ?? "null");
    const rate = Number(value?.rate);

    return value && /^[A-Za-z]{3}$/.test(String(value.code)) && rate > 0
      ? {
          code: String(value.code).toUpperCase(),
          symbol: value.symbol ?? null,
          decimalPlaces: value.decimalPlaces ?? 2,
          rate,
        }
      : null;
  } catch {
    return null;
  }
};

/** A browser-local presentation setting. It never writes to the currencies API. */
export const displayCurrency = ref<DisplayCurrency | null>(storedCurrency());

export const setDisplayCurrency = (currency: DisplayCurrency | null) => {
  displayCurrency.value = currency
    ? {
        code: currencyCode(currency),
        symbol: currency.symbol ?? null,
        decimalPlaces: currencyDecimalPlaces(currency),
        rate: Number(currency.rate),
      }
    : null;

  if (typeof window === "undefined") return;

  if (displayCurrency.value) {
    window.localStorage.setItem(
      storageKey,
      JSON.stringify(displayCurrency.value),
    );
  } else {
    window.localStorage.removeItem(storageKey);
  }
};

const validRate = (value: unknown): value is number =>
  typeof value === "number" && Number.isFinite(value) && value > 0;

const formatWithSymbol = (
  value: number,
  currency: CurrencyFormatSource,
  locale: string,
) => {
  const formatted = new Intl.NumberFormat(locale, {
    minimumFractionDigits: currencyDecimalPlaces(currency),
    maximumFractionDigits: currencyDecimalPlaces(currency),
  }).format(value);
  const label = String(currency.symbol ?? currencyCode(currency)).trim();

  return label ? `${label}\u00a0${formatted}` : formatted;
};

/**
 * Converts a presentation amount through the current base currency when both
 * source and selected display rates are known. The source record remains
 * untouched, and an unknown rate falls back to the original formatting.
 */
export const formatDisplayMoney = (
  value: number | string | null | undefined,
  source?: DisplayCurrency | null,
  locale = "en",
) => {
  const amount = Number(value);
  const target = displayCurrency.value;
  const sourceRate = Number(source?.rate);

  if (!Number.isFinite(amount)) return formatMoney(value, source, locale);

  if (target && validRate(sourceRate) && validRate(Number(target.rate))) {
    const converted = (amount / sourceRate) * Number(target.rate);

    return formatWithSymbol(converted, target, locale);
  }

  if (source) return formatWithSymbol(amount, source, locale);

  return formatMoney(value, source, locale);
};
