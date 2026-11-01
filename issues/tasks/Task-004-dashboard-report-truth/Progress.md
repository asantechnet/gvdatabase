# Progress — Task-004

## Status snapshot
- Status: `Review`
- Owner: `Implementer (agent)`
- Last updated: `2026-10-31`
- Current focus: `Financial truth shipped; two visual criteria remain unverified`
- Blocker: `None`
- Next action: `Check 390 px KPI reachability and Dari digit consistency, then the dark header brand mark.`

## Acceptance criteria progress
- [x] Given 14 open salaries and 0 paid, when the dashboard renders, then the salary card shows accrued/open and paid separately and no label calls the open amount posted. — Evidence: `financial.salaries_payable` (open + partially paid) and `financial.salaries_paid` come from `payment_status`; the dashboard renders Net payroll and Paid rows and no longer references `educational.postedSalaries`.
- [x] Given the financial report is opened for a period, when salary rows are listed, then each row's status comes from its payment state and no open salary is labelled posted. — Evidence: `EducationalReportController::financialReport` sets salary `status` from `payment_status` (default `open`); income/expense rows are labelled `recorded`, since they have no posting marker.
- [x] Given payroll is not part of posted expenses, when expenses are displayed, then payroll is shown as a separate labelled figure for the same period, or included with an explicit explanation. — Evidence: payroll is its own card rows (Net payroll / Paid) using the same date filter as income and expenses; expenses remain a separate figure.
- [x] Given the "Still to collect" card, when future-dated extension fees exist, then they are excluded from the current receivable total or shown in a separately labelled future bucket. — Evidence: `operations.outstanding` now only includes contracts whose start date is null or already effective; future-dated lines are returned separately as `operations.outstanding_future`.
- [x] Given the reports page and dashboard financial cards, when they render, then the applied period and the currency code are visible without opening a filter. — Evidence: the dashboard shows an applied-period chip and `money()` prints each amount with its currency code; the reports result bar shows the applied period next to the row count, and the financial report carries a currency column per row.
- [x] Given the contract attention tile, when it is clicked, then it opens the register's expiring view and the tile names the affected contract and its end date. — Evidence: expiring active contracts route to `employee-contracts` with `query.expiring = 1`, and the subtitle now reads `ends YYYY-MM-DD`; `EducationalDashboardTest` asserts name, query and subtitle.
- [ ] Given a 390 px viewport, when the dashboard loads, then the KPI region does not overflow the page and every KPI remains reachable; in Dari, KPI digits use one consistent digit set. — Evidence: `agency-overview.scss` already makes the summary grid a horizontal snap strip below 600 px, but neither the 390 px render nor Dari digits were re-checked. Unchecked.
- [ ] Given dark theme, when the dashboard header renders, then the brand mark remains legible. — Evidence: not verified; the page header carries no product brand mark, so this may be a global header concern rather than a dashboard one. Unchecked.

## Work completed with evidence
- Payroll is bucketed by persisted `payment_status` per currency; `salaries` keeps its total meaning for existing consumers, with `salaries_by_status`, `salaries_payable` and `salaries_paid` added.
- Receivables split into currently collectible and future-dated by `contracts.start_date`; the current bucket no longer includes extensions that have not started.
- Contract attention hits name the contract number and end date and deep-link to the register's expiring view.
- Financial report salary status is derived; open salaries are never labelled posted.
- Dashboard salary card, applied-period chip and report period line added.

## Files changed and why
- `app/Http/Controllers/EducationalDashboardController.php` — salary buckets, receivable split, attention link/end date.
- `app/Http/Controllers/EducationalReportController.php` — derived row status.
- `resources/ts/api/educationalDashboard.ts` — typed new keys and query-capable attention routes.
- `resources/ts/pages/educational-dashboard.vue` — payroll rows, period chip.
- `resources/ts/pages/educational-reports.vue` — applied period in the result bar.
- `tests/Feature/EducationalDashboardTest.php` — attention route/end-date assertions.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php artisan test tests/Feature/EducationalDashboardTest.php` | Root checkout, MySQL `testing` | Pass — 7 tests / 57 assertions | tool output | Includes the updated expiring-route assertion. |
| 2026-10-31 | `php artisan test tests/Feature/EducationalReportTest.php` | Root checkout, MySQL `testing` | Pass — 5 tests | tool output | Run before the status change; report suite has no status assertion. |
| 2026-10-31 | `php -l` on both controllers, `npm run typecheck` | PHP 8.2 / Node | Pass (exit 0) | — | |

## Decisions and deviations
- Income and expenses have no posting marker, so the report labels them `recorded` instead of inventing `posted`.
- `financial.salaries` is retained as the period total; payable/paid are additive keys. The dashboard no longer shows the word "posted" for payroll.
- The future receivable bucket is exposed by the API but not rendered on the 390 px card; the current total simply excludes it.

## Blockers and open questions
- None blocking. 390 px/Dari and dark header checks remain.

## Handoff notes
- Backend figures are authoritative and tested; only the two visual criteria are open.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record; implementation had not started.
- `2026-10-31` Split payroll and receivables by persisted state, fixed the report status and attention deep link, updated the dashboard/report presentation; dashboard suite and typecheck pass. Status moved to `Review` pending visual checks.
