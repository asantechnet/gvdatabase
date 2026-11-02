/**
 * Single source of truth for presentation formatting.
 *
 * Adoption rules for page authors:
 * - Format money, display dates and counts through this module, or through the
 *   active-locale wrappers exported by `@/utils/localization`
 *   (`formatActiveMoney`, `formatActiveDate`, `formatActiveCount`). Do not
 *   call `Intl` or `Number#toFixed` for money in a page or component.
 * - Money is always printed with the currency code exactly once and with the
 *   precision from the currency record (`decimalPlaces` / `decimal_places`).
 *   Never concatenate a symbol plus a code yourself.
 * - Form inputs and API payloads stay ISO (`YYYY-MM-DD` / ISO datetime). Only
 *   display strings are localized; use `toIsoDate` to normalize a form value.
 * - Missing or invalid values render `FORMATTING_PLACEHOLDER`, never `NaN`,
 *   `null` or `undefined`.
 *
 * Every function takes an explicit locale so it stays pure and testable; the
 * active locale is resolved by the wrappers in `localization.ts`.
 */

export const FORMATTING_PLACEHOLDER = "—";

export interface CurrencyFormatSource {
  code?: string | null;
  symbol?: string | null;
  rate?: number | null;
  decimalPlaces?: number | null;
  decimal_places?: number | null;
}

export type DisplayDateFormat = "short" | "long" | "time";

/** Tables use a localized short date; detail views and timelines use a long date/time. */
const displayDateOptions: Record<
  DisplayDateFormat,
  Intl.DateTimeFormatOptions
> = {
  short: { year: "numeric", month: "short", day: "numeric" },
  long: {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  },
  time: { hour: "numeric", minute: "2-digit" },
};

const isMissing = (value: unknown): value is null | undefined | "" =>
  value === null || value === undefined || value === "";

const toNumber = (value: number | string | null | undefined): number => {
  if (isMissing(value)) return Number.NaN;

  return typeof value === "number" ? value : Number(value);
};

export const currencyCode = (currency?: CurrencyFormatSource | null): string =>
  String(currency?.code ?? "")
    .trim()
    .toUpperCase();

/** Currency precision, falling back to two decimals when the record omits it. */
export const currencyDecimalPlaces = (
  currency?: CurrencyFormatSource | null,
): number => {
  const value = currency?.decimalPlaces ?? currency?.decimal_places;

  if (typeof value !== "number" || !Number.isFinite(value)) return 2;

  return Math.min(6, Math.max(0, Math.trunc(value)));
};

/**
 * Format a monetary value with its currency code exactly once.
 *
 * Example (en): `formatMoney(45000, { code: 'AFN', decimalPlaces: 2 })`
 * returns `AFN 45,000.00`.
 */
export const formatMoney = (
  value: number | string | null | undefined,
  currency?: CurrencyFormatSource | null,
  locale = "en",
): string => {
  const amount = toNumber(value);

  if (!Number.isFinite(amount)) return FORMATTING_PLACEHOLDER;

  const decimals = currencyDecimalPlaces(currency);
  const fractionOptions: Intl.NumberFormatOptions = {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  };
  const code = currencyCode(currency);

  // ISO 4217 shape only: `Intl` throws on malformed codes such as `US`.
  if (/^[A-Z]{3}$/.test(code)) {
    try {
      return new Intl.NumberFormat(locale, {
        style: "currency",
        currency: code,
        currencyDisplay: "code",
        ...fractionOptions,
      }).format(amount);
    } catch {
      // Fall through to the plain decimal plus code form below.
    }
  }

  const formatted = new Intl.NumberFormat(locale, fractionOptions).format(
    amount,
  );

  return code ? `${formatted} ${code}` : formatted;
};

/** Locale-aware decimal number, with optional overrides. */
export const formatNumber = (
  value: number | string | null | undefined,
  locale = "en",
  options: Intl.NumberFormatOptions = {},
): string => {
  const number = toNumber(value);

  if (!Number.isFinite(number)) return FORMATTING_PLACEHOLDER;

  return new Intl.NumberFormat(locale, options).format(number);
};

/** Whole-number count (for example `1,250 applicants`). */
export const formatCount = (
  value: number | string | null | undefined,
  locale = "en",
): string => formatNumber(value, locale, { maximumFractionDigits: 0 });

const calendarDate = (value: string | number | Date): Date => {
  // Date-only API values represent a local calendar day. Parsing them as UTC
  // can display the previous day for users west of UTC.
  if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value))
    return new Date(`${value}T00:00:00`);

  return value instanceof Date ? value : new Date(value);
};

/**
 * Localized display date. `short` is for tables, `long` for detail views and
 * timelines, `time` for a clock value. Inputs must keep their ISO value; this
 * function is display-only.
 */
export const formatDisplayDate = (
  value: string | number | Date | null | undefined,
  format: DisplayDateFormat = "short",
  locale = "en",
): string => {
  if (isMissing(value)) return FORMATTING_PLACEHOLDER;

  const date = calendarDate(value as string | number | Date);

  if (Number.isNaN(date.getTime())) return FORMATTING_PLACEHOLDER;

  return new Intl.DateTimeFormat(locale, displayDateOptions[format]).format(
    date,
  );
};

/** ISO calendar day for form inputs and API payloads. */
export const toIsoDate = (value: string | Date | null | undefined): string => {
  if (isMissing(value)) return "";

  if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value))
    return value;

  const date = value instanceof Date ? value : new Date(value);

  if (Number.isNaN(date.getTime())) return "";

  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${date.getFullYear()}-${month}-${day}`;
};
