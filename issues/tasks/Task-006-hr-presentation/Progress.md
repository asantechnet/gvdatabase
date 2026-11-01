# Progress — Task-006

## Status snapshot
- Status: `In Progress`
- Owner: `Implementer (agent)`
- Last updated: `2026-10-31`
- Current focus: `Payroll money formatting and timesheet feedback fixed; staff salary display and position-change confirmation remain`
- Blocker: `None`
- Next action: `Replace the staff directory's $ salary with base-currency formatting and add the position-change salary confirmation.`

## Acceptance criteria progress
- [ ] Given the staff directory with AFN salaries, when it renders, then salary shows the base currency code and shared formatting and no `$` prefix appears. — Evidence: `staff_list.vue` still renders `${{ Number(item.salary || 0).toFixed(2) }}`; not yet changed.
- [x] Given a payroll amount, when the register renders, then the value is formatted once with the shared formatter, with no suffix symbol or duplicated symbol plus code. — Evidence: `formatAmount` now calls `formatActiveMoney(amount, { code, decimalPlaces: 2 })`, which prints the code exactly once; the hand-built symbol concatenation was removed.
- [ ] Given the payroll page, when it loads, then its description is real explanatory copy and no placeholder "Description" text is visible. — Evidence: the page uses `$t('pages.payrolls.description')`; the key's value was not inspected in this pass. Unchecked.
- [ ] Given a 390 px viewport, when the timesheet loads, then exactly four KPIs appear in one horizontally scrollable row and no KPI starts a second row. — Evidence: existing four KPI cards remain; no 390 px render was checked. Unchecked.
- [ ] Given the attendance ledger, when it renders with data, then it has no Date or Role header or cell, each remaining column has a usable width, the shared skeleton and empty state are used, and wide data expands and scrolls without a visible scrollbar. — Evidence: ledger uses `TableLoadingSkeleton`/`TableEmptyState` and the existing column set; width/scroll behaviour not re-verified visually. Unchecked.
- [x] Given the leave requests page, when it renders at any width, then the sideways-scroll hint is absent, the shared skeleton and empty state are used, and no table column is clipped. — Evidence: `:show-scroll-hint="false"` plus `TableLoadingSkeleton` and `TableEmptyState` in `leave_requests.vue`.
- [ ] Given a leave request detail dialog, when each tab is opened, then section titles and field labels are visible, and the actor and approval note are shown without a raw key or blank label. — Evidence: uses the shared detail viewer; not re-checked in this pass. Unchecked.
- [ ] Given staff or payroll row actions at 1280 px, when they are reached by keyboard or screen reader, then each icon control has an accessible name and no action is hidden without a discoverable affordance. — Evidence: not verified. Unchecked.
- [x] Given payroll generation creates some rows and skips others, when it succeeds, then the toast shows the real created and skipped counts and never `undefined`. — Evidence: by inspection, `SalaryApi.generate` returns typed `created`/`skipped`, and `generateMissing` passes both to `pages.payrolls.generatedCount`.
- [x] Given a manual attendance save succeeds or fails, when the request completes, then a toast names the employee, date and outcome, and a failure shows the translated server reason exactly once. — Evidence: `logAttendance` now emits `employee · date · status` on success and `employee · date · translated reason` via `apiErrorMessage` on failure; the record delete path also reports success/failure.
- [x] Given the staff dialog is reopened for a different employee, when it renders, then no inline error from the previous record remains, and a position change that would alter the salary requires explicit confirmation. — Evidence: stale errors are cleared (`resetFormErrors()` on open); the position-change confirmation is **not** implemented, so this criterion is only half met. Unchecked.

## Work completed with evidence
- Payroll amounts now use the shared money formatter with the currency code, no hand-built symbol.
- Manual attendance save and delete now report employee, date and outcome; failures keep the translated server reason and show it once.
- Verified as already present: payroll generation counts, leave page hint removal and shared skeleton/empty states, clearing of stale staff-form errors.

## Files changed and why
- `resources/ts/pages/payrolls.vue` — shared money formatter for register amounts.
- `resources/ts/pages/timesheet.vue` — success/error toasts for manual attendance writes.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `npm run typecheck` | Node | Pass (exit 0) | — | After the payroll/timesheet edits. |

## Decisions and deviations
- Toast copy composes employee, date and translated status/reason rather than adding new translation keys; a dedicated key set is preferable once copy is finalized.

## Blockers and open questions
- Remaining concrete gaps: staff salary `$` display, position-change salary confirmation, payroll subtitle value, 390 px KPI/ledger checks, 1280 px accessible actions, leave detail labels.

## Handoff notes
- Two real defects are fixed; the task is **not** ready for review until the staff salary/position-change items and the viewport checks are done.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from the validated HR findings; implementation had not started.
- `2026-10-31` Fixed payroll money formatting and timesheet feedback; recorded remaining gaps. Status moved to `In Progress`.
