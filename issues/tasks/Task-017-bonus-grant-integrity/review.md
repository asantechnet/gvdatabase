# Review — Task-017 bonus grant integrity

- **Reviewed:** 2026-11-04
- **Verdict:** Needs a formatting correction — grant behavior otherwise passed
- **Method:** Playwright CLI on `/bonuslist`; `BonusGrantTest` passed; source inspection.
- **Scope:** review only. No code was changed.

## Finding — Bonus KPIs and rows render both the currency symbol and code

**Severity:** Low–Medium (presentation consistency; no grant or payroll data is wrong)

**Expected:** Money is formatted once through the shared formatter, with the currency code exactly once and no hand-built symbol-plus-code string.

**Actual:** The bonus KPIs render `؋0.00 AFN` for both “Projected Grant Pool Base” and “Granted payout pool (current base)”. Per-employee amounts use the same pattern. `resources/ts/pages/bonuslist.vue` contains two local formatters that concatenate `currencySymbol` and `currencyCode` (`formatBonusAmount` around line 325 and `formatCurrentBaseAmount` around line 333). `resources/ts/utils/money.ts` explicitly says: “Money is always printed with the currency code exactly once… Never concatenate a symbol plus a code yourself.”

**Evidence:** `/bonuslist` at 1280×720: `؋0.00 AFN` in both KPI cards. Source: `bonuslist.vue:325` and `:333`.

**Suggested fix:** Use `formatActiveMoney`/`formatMoney` with the row or base currency, so the output is a single representation such as `AFN 0.00`. Keep the per-row currency snapshot used for the grant amount; only the display formatter should change.

## Notes

- `BonusGrantTest` passed, including zero-grant rejection, explicit amount handling and payroll inclusion.
- No source was modified during this review.
