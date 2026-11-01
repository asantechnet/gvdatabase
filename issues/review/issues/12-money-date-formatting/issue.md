# ISSUE-12 — Currency, date and number formatting is inconsistent across pages

- **Severity:** P2 (misreading financial data)
- **Category:** UI/UX / logic presentation
- **Pages:** Dashboard, Staff directory, Payrolls, Contracts, Bonuses, Reports, Dashboard filters, Dari dashboard
- **Persona:** Super administrator
- **Confidence:** High — observed on each page

## Expectation

One locale-aware formatter for money and dates. The currency code/symbol and decimal precision should be identical everywhere, with thousands separators, and number digits should not mix scripts within a locale.

## Observed

| Page | Example |
|---|---|
| Dashboard | `230,000 AFN` / `580,000 AFN` |
| Staff directory | **`$45000.00`** (DB salary is an AFN amount) |
| Contracts | `50000.00 AFN` |
| Payrolls | `60,000.00؋` (symbol suffix) |
| Bonuses | `؋0.00 AFN` (symbol and code) |
| Reports | `AFN 0` |
| Contract drawer | `Nov 28, 2026` |
| Contracts list | `2026-11-28` |
| Visa reports | `2026-10-29` |
| Dashboard filter | `mm/dd/yyyy` |
| Case timeline | `October 29, 2026 at 10:54 AM` |
| Dari dashboard | Persian digits (`۲`, `۷`) alongside Western digits (`1 / 1`) on the same screen |

Additional copy/count inconsistencies: "1 Active Filters", "2 case(s)", "1 users", "1 candidates", "0 Expense Records Shown".

## Impact

Staff may read the $ symbol as US dollars while payroll is in AFN. Mixed precision and separators make totals harder to scan and compare. Date ambiguity (11/12 vs 12/11) is a real risk.

## Evidence

- `review/evidence/staff-list.png`
- `review/evidence/payrolls.png`
- `review/evidence/contracts.png`
- `review/evidence/dashboard-full.png`
- `review/evidence/dashboard-filters.png`
- `review/evidence/dashboard-dari.png`

## Standard comparison

Standard ERP deployments use one currency/date formatter driven by company locale and transaction currency, and pluralisation through the i18n layer.
