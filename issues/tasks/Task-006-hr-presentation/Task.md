# Task Task-006 — Make staff, payroll, timesheet and leave screens truthful

## Metadata
- Task ID: `Task-006`
- Title: `Make staff, payroll, timesheet and leave screens truthful`
- Initial status: `Ready`
- Type: `Frontend`
- Priority: `P2`
- Owner role: `Vue/Vuetify implementer with payroll domain review`
- Estimated size: `M`
- Source plan/version: `issues/review/issues/07-tables-and-accessible-actions/issue.md` staff/payroll slice; `issues/review/issues/12-money-date-formatting/issue.md` staff/payroll slice; `issues/review/issues/16-headers-placeholders/issue.md` payroll slice; `hr-issues/issues/HR-005-payroll-generate-toast-empty-counts.md`; `hr-issues/issues/HR-007-timesheet-no-toast-feedback.md`; `hr-issues/issues/HR-008-staff-form-stale-inline-errors.md`; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-07-HR`, `ISSUE-12-HR`, `ISSUE-16-HR`, `NOTE-TIMESHEET`, `NOTE-LEAVE`, `HR-005`, `HR-007`, `HR-008`

## Objective
Staff and payroll money is unambiguous, the timesheet and leave screens are operable on a phone without losing columns, labels or feedback, and every HR write reports its result.

## Context and problem
`staff_list.vue` renders salary as `${{ Number(...).toFixed(2) }}` while payroll is in AFN. `payrolls.vue` appends a currency symbol to the number and its page description is literally "Description". Staff and payroll tables hide status/actions at 1280 px and use icon-only actions.

Payroll generation reports success with undefined `created`/`skipped` counts because the API helper and page disagree on the response envelope; a failure falls through to a generic page toast, and the global notifier can add a second. The timesheet's attendance log has no success or error feedback at all. The staff form opens a new dialog with the previous record's inline errors still attached, and changing position can silently rewrite the salary without warning.

The post-review notes asked for the timesheet to keep four KPIs in one scrollable row, to remove Date and Role columns from the attendance ledger, to use the shared skeleton and empty state, and to let wide data expand with horizontal scrolling rather than shrinking cells. Current code already implements much of this; this task verifies and locks it, and fixes anything that regressed. The same applies to leave request details, which now use the shared detail viewer.

## Scope
### Included
- Replace hard-coded `$` and ad-hoc currency strings on staff and payroll screens with the shared formatter from Task-012, using the workspace base currency.
- Replace the payroll placeholder subtitle with real copy.
- Keep exactly four range KPIs on the timesheet and place them in one horizontally scrollable row from 390 px through laptop widths.
- Verify the attendance ledger has no Date or Role columns, gives each remaining column a usable width, uses `TableLoadingSkeleton` and `TableEmptyState`, and expands/scrolls horizontally without a visible scrollbar.
- Remove any sideways-scroll hint from the leave requests page and confirm skeleton and shared empty state are used.
- Verify and lock the leave detail dialog: section titles, field labels on every tab, actor and approval note, and no clipped values.
- Ensure staff and payroll row actions have accessible names and remain reachable at 1280 px via Task-013 primitives.
- Fix payroll generation feedback so the success toast shows the real created/skipped counts, and a failure shows one specific message rather than a second generic toast.
- Emit a success or error toast for each manual attendance save in the timesheet, naming the employee and date and translating the server's reason.
- Clear stale inline form errors when the staff dialog opens for a different employee, and warn before a position change alters the salary instead of applying it silently.
- Add focused component or feature tests where the repository already has a test pattern for these screens; otherwise record manual evidence.

### Excluded
- Payroll calculation, salary mutation rules and advance recovery.
- New HR features, leave approval logic or attendance policy.
- Shared formatter implementation (Task-012) and shared table primitives (Task-013).
- Backend salary/leave API changes unless a display field is missing; if one is, record it as a dependency rather than changing the domain rule.

## Preconditions and assumptions
- The company base currency is available from the existing settings/currency API; AFN is the demo base.
- `TableLoadingSkeleton` and `TableEmptyState` exist and are the approved shared components.
- Assumption to validate: the leave detail viewer's `detailFields` already include date, duration, treatment, off-day days, absent days, reason, approved by and approval note. If any label falls back to a raw key, fix the label source, not the viewer.
- The timesheet screen currently has four KPI cards; this task does not add a fifth.

## Dependencies
- `Task-012` — shared money/date/number formatter.
- `Task-013` — responsive table, accessible actions and mobile card primitives.
- `Task-018` — one owner for API failure feedback; this task removes the page-level duplicate error toast.

## Contracts and boundaries
- Input/output: no API contract changes are expected; display uses existing fields.
- Compatibility guarantee: payroll amounts remain the same numeric values; only presentation changes.
- Error behavior: a missing currency falls back to the documented base currency and shows a visible fallback, never a bare number.
- Permission/data scope: staff salary visibility and payroll permissions are unchanged.
- Integration seam: money formatting is imported from the Task-012 utility, not reimplemented in the page.
- Files this task must not modify: `resources/ts/utils/localization.ts`, `resources/ts/components/responsive-data-table/**`, `app/Http/Controllers/SalaryController.php`.

## Exclusive file ownership
- `resources/ts/pages/staff_list.vue` — salary/currency display and accessible actions.
- `resources/ts/pages/payrolls.vue` — money display, subtitle and action reachability.
- `resources/ts/pages/timesheet.vue` — four-KPI strip and attendance ledger layout.
- `resources/ts/pages/leave_requests.vue` — hint removal, skeleton/empty state and detail trigger.
- `resources/ts/api/salary.ts` — typed currency fields used by the display.
- `resources/ts/api/leaveRequests.ts` — typed detail fields used by the viewer.

## Acceptance criteria
- [ ] Given the staff directory with AFN salaries, when it renders, then salary shows the base currency code and shared formatting and no `$` prefix appears.
- [ ] Given a payroll amount, when the register renders, then the value is formatted once with the shared formatter, with no suffix symbol or duplicated symbol plus code.
- [ ] Given the payroll page, when it loads, then its description is real explanatory copy and no placeholder "Description" text is visible.
- [ ] Given a 390 px viewport, when the timesheet loads, then exactly four KPIs appear in one horizontally scrollable row and no KPI starts a second row.
- [ ] Given the attendance ledger, when it renders with data, then it has no Date or Role header or cell, each remaining column has a usable width, the shared skeleton and empty state are used, and wide data expands and scrolls without a visible scrollbar.
- [ ] Given the leave requests page, when it renders at any width, then the sideways-scroll hint is absent, the shared skeleton and empty state are used, and no table column is clipped.
- [ ] Given a leave request detail dialog, when each tab is opened, then section titles and field labels are visible, and the actor and approval note are shown without a raw key or blank label.
- [ ] Given staff or payroll row actions at 1280 px, when they are reached by keyboard or screen reader, then each icon control has an accessible name and no action is hidden without a discoverable affordance.
- [ ] Given payroll generation creates some rows and skips others, when it succeeds, then the toast shows the real created and skipped counts and never `undefined`.
- [ ] Given a manual attendance save succeeds or fails, when the request completes, then a toast names the employee, date and outcome, and a failure shows the translated server reason exactly once.
- [ ] Given the staff dialog is reopened for a different employee, when it renders, then no inline error from the previous record remains, and a position change that would alter the salary requires explicit confirmation.

## Verification plan
- Automated: `npm run typecheck`; if a component test harness exists for these pages, add focused tests for the formatter output and detail labels. Otherwise record the manual scenarios as evidence.
- Manual: staff and payroll at 390, 768 and 1280 px in LTR and RTL, light and dark; timesheet KPI strip at 390 and 1024; attendance ledger with 30+ rows; leave detail for a pending, an off-day and an absent request.
- Fixtures: one employee with an AFN salary, one partially paid salary, one leave request per treatment. Non-production only.
- Regression: confirm payroll print, payslip preview, leave approval and timesheet print still render the same values.
- Evidence: screenshots for each viewport, before/after money strings, detail-dialog tabs, test output.

## Implementation notes
Do not mix a symbol and a code in the same value. Pick "code + amount" for registers and the shared formatter's explicit currency mode. For the timesheet strip, use a flex row with `overflow-x: auto` and hide the scrollbar only if a visible affordance remains; do not shrink cards below a readable width. For the attendance ledger, remove the columns in the markup, not with CSS, so screen readers do not announce stale headers.

## Risks and rollback
- Risk: a formatter change makes payroll totals look different even though values are unchanged. Mitigation: verify numeric equality before and after and show the currency code.
- Risk: removing Date from the attendance ledger removes the only per-row date cue. Mitigation: the record is already grouped/filtered by the range; confirm the range heading is visible, and keep the date in the row's accessible label if needed.
- Data migration: none. Rollback is presentation-only.
- Stop condition: if a required display field is absent from the API, stop and record the backend dependency instead of fabricating it.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
