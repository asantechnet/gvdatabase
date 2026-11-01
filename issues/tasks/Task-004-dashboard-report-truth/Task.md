# Task Task-004 — Make dashboard and report figures say what they mean

## Metadata
- Task ID: `Task-004`
- Title: `Make dashboard and report figures say what they mean`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P2`
- Owner role: `Full-stack Laravel/Vue implementer`
- Estimated size: `M`
- Source plan/version: `issues/review/issues/03-financial-labels/issue.md`; `issues/review/issues/06-contract-attention/issue.md` dashboard slice; `issues/review/REPORT.md` §4 P2-1, P2-7; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-03`, `ISSUE-06-LINK`, `ISSUE-12-DASHBOARD`, `ISSUE-13-DASHBOARD`

## Objective
Every financial or contractual number on the agency dashboard and reports states its period, currency and status, and unpaid salary is never presented as posted.

## Context and problem
`EducationalDashboardController::financial()` sums `Salary::total_amount` by `payment_date` without filtering `payment_status`. The dashboard label `educational.postedSalaries` reads "Salaries posted". The demo database has 14 salaries totalling 1,070,000 AFN, all `payment_status = open` and 0 paid. `EducationalReportController::financialReport()` emits every salary row with `status => 'posted'` regardless of payment state. The reports page shows "Payroll cost AFN 0" for a period that is not displayed. The dashboard "Still to collect" sums all applicant contract fees including future-dated extensions that start 2026-11-28.

The dashboard also links "Contract attention" to `/employee-contracts` without an expiring filter, and its KPI strip overflows at 390 px. Dari mode mixes Persian and Western digits.

## Scope
### Included
- Separate salary figures into accrued/open, paid and posted (posted only when a payment or posting record exists); never label open rows as posted.
- Make payroll visible in expense reporting or show it as a separately labelled payroll figure with the same period.
- Make the financial report derive each salary row's status from `payment_status` instead of hard-coding posted.
- Split receivables into currently collectible and future-dated extension fees; label or exclude not-yet-effective extensions in "Still to collect".
- Display the applied report period and currency on dashboard financial cards and report headers.
- Point the contract attention tile to the register's expiring view and show the contract identifier and end date in the tile or its tooltip.
- Make the dashboard KPI region usable at 390 px without page-level horizontal overflow.
- Keep one digit set within the Dari locale for dashboard KPI numbers.

### Excluded
- The contract register's columns and filter implementation (Task-003 owns `contract_list.vue`; this task only links to its stable expiring URL).
- Shared money/date formatter implementation (Task-012; this task consumes it).
- Report CSV column redesign beyond the status/period truth above.
- Payroll generation, salary mutation or salary API rules.

## Preconditions and assumptions
- `salaries.payment_status` is the authoritative paid/open state; `salary_payments` may be needed to distinguish partially paid. Confirm the exact relation in implementation.
- `contracts.start_date` determines whether an extension is currently effective; `contract_role = extension` identifies it.
- Assumption to validate: "posted expenses" means expenses that reached the approved/posted ledger. If the system has no posting marker for expenses, the label must change to "Recorded expenses" rather than inventing a status.
- The dashboard date filter passes ISO dates; display formatting belongs to the UI.

## Dependencies
- `Task-003` — the contract register must expose the stable `?expiring=1` view the attention tile links to.
- `Task-012` — money, date and number display use the shared formatter.

## Contracts and boundaries
- Input/output: dashboard and report JSON keep their existing top-level keys; new keys are additive for accrued, paid and future receivables.
- Compatibility guarantee: existing consumers of `financial.salaries` continue to receive a value; its meaning is documented in code and UI.
- Error behavior: a report with an invalid date range returns the existing validation error; the UI keeps the previous period visible instead of showing zero as if it were data.
- Permission/data scope: financial figures remain behind the existing dashboard/report permissions; no new data leak.
- Integration seam: the attention tile URL `/employee-contracts?expiring=1` is owned by Task-003; do not duplicate the filter query here.
- Files this task must not modify: `resources/ts/pages/contract_list.vue`, `app/Http/Controllers/ContractController.php`, `resources/ts/utils/localization.ts`.

## Exclusive file ownership
- `app/Http/Controllers/EducationalDashboardController.php` — salary status split, receivables split and attention link data.
- `app/Http/Controllers/EducationalReportController.php` — salary status and period in report rows.
- `resources/ts/pages/educational-dashboard.vue` — labels, period/currency display, attention link and 390 px layout.
- `resources/ts/pages/educational-reports.vue` — period header and payroll/expense presentation.
- None yet — the theme brand-mark discovery is recorded in Progress.md before any edit; no product path is claimed until that discovery is complete.

## Acceptance criteria
- [ ] Given 14 open salaries and 0 paid, when the dashboard renders, then the salary card shows accrued/open and paid separately and no label calls the open amount posted.
- [ ] Given the financial report is opened for a period, when salary rows are listed, then each row's status comes from its payment state and no open salary is labelled posted.
- [ ] Given payroll is not part of posted expenses, when expenses are displayed, then payroll is shown as a separate labelled figure for the same period, or included with an explicit explanation.
- [ ] Given the "Still to collect" card, when future-dated extension fees exist, then they are excluded from the current receivable total or shown in a separately labelled future bucket.
- [ ] Given the reports page and dashboard financial cards, when they render, then the applied period and the currency code are visible without opening a filter.
- [ ] Given the contract attention tile, when it is clicked, then it opens the register's expiring view and the tile names the affected contract and its end date.
- [ ] Given a 390 px viewport, when the dashboard loads, then the KPI region does not overflow the page and every KPI remains reachable; in Dari, KPI digits use one consistent digit set.
- [ ] Given dark theme, when the dashboard header renders, then the brand mark remains legible.

## Verification plan
- Automated: feature tests for salary status splits, future-extension receivables and report salary status; run the focused dashboard/report tests and `npm run typecheck`.
- Manual: compare the dashboard salary card with `SELECT payment_status, SUM(total_amount) FROM salaries GROUP BY payment_status`; do the same for the report. Resize to 390 px; switch to Dari and dark theme.
- Fixtures: one open salary, one partially paid salary, one paid salary, one current contract and one future extension. Non-production only.
- Regression: confirm the dashboard date filter, contract attention count and report exports still work; confirm no N+1 growth on the financial query.
- Evidence: SQL totals, screenshot of the salary card, report header, attention tile target, 390 px dashboard, Dari dashboard.

## Implementation notes
Do not change the salary ledger. Compute presentation buckets from existing rows. If "posted" cannot be defined from a real record, remove the word rather than inventing a marker. Keep the attention count and the register filter on the same 30-day definition and document it in both places. For 390 px, prefer stacking KPI cards or an intentional horizontal strip with a visible affordance over shrinking text.

## Risks and rollback
- Risk: changing the meaning of `financial.salaries` can break another consumer. Mitigation: keep the key and add explicit keys; search all consumers before editing.
- Risk: a new receivables split changes KPI totals users compare with history. Mitigation: show both current and future buckets and document the change in the dashboard copy.
- Data migration: none. Rollback is code-only.
- Stop condition: if expense posting has no authoritative marker, stop and get a decision on the label instead of guessing.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
