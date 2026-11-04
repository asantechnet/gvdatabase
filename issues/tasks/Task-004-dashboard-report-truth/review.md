# Review — Task-004 dashboard and report truth

- **Reviewed:** 2026-11-04
- **Verdict:** Needs changes — not ready for sign-off
- **Method:** Playwright CLI against `http://127.0.0.1:8000/agency-overview` after `php artisan migrate:fresh --seed`; source inspection; `EducationalDashboardTest` and `EducationalReportTest` passed.
- **Scope:** review only. No code was changed.

## Finding 1 — The dashboard has no accrued/open versus paid salary split

**Severity:** High

**Expected:** The salary card shows accrued/open and paid amounts separately, and no open salary is labelled as posted.

**Actual:** The “Money & service sales” card has only one payroll row, `Net payroll`, showing `؋ 580,000.00`. There is no accrued/open or paid bucket on the dashboard. The controller returns `salaries_by_status`, `salaries_payable` and `salaries_paid`, but `financialRows` in `resources/ts/pages/educational-dashboard.vue` only renders `financial.salaries` under the `Net payroll` label.

**Evidence:** `/agency-overview` at 1280×720: `Net payroll` is the only payroll figure. Source: `resources/ts/pages/educational-dashboard.vue` lines 243–268.

**Suggested fix:** Render the payable/open and paid buckets from the existing API keys, with labels that cannot be read as “posted”. Do not derive them from the net total.

## Finding 2 — Applied period and currency code are not visible on the dashboard

**Severity:** Medium

**Expected:** The applied period and the currency code are visible on the financial cards without opening a filter.

**Actual:** The header contains only `Filters` and `Refresh`. The KPI values show `؋ 65,000.00` and `؋ 580,000.00`; no date range or `AFN` code is displayed. `money()` uses `formatActiveMoney`, which formats through `displayCurrency.ts` using the currency symbol and does not include the code.

**Evidence:** `/agency-overview` snapshot: no `AFN`, period, date range or applied month outside the filter control.

**Suggested fix:** Show the applied range beside the dashboard heading/financial section and use the shared `formatMoney` contract (`AFN 65,000.00`) for financial rows, or otherwise expose the code next to the symbol.

## Notes and limitations

- The report page (`/visa-reports`) was not the failing surface in this pass; its tests passed.
- The contract-attention tile correctly names `ends 2026-11-24` and targets `/employee-contracts?expiring=1`.
- No source was modified during this review.
