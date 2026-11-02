# Progress — Task-006

## Status snapshot
- Status: `Review`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-11-01`
- Current focus: `Staff salary formatting, payroll subtitle, position-change confirmation and accessible action names are implemented; acceptance criteria are met at source/type level.`
- Blocker: `None`
- Next action: `Independent review should confirm the 390 px timesheet strip, 768/1280 px table actions and RTL/dark rendering in a browser; no further code work is known.`

## Acceptance criteria progress
- [x] Given the staff directory with AFN salaries, when it renders, then salary shows the base currency code and shared formatting and no `$` prefix appears. — Evidence: `staff_list.vue` `#item.salary` now renders `formatSalary(item)`; `formatSalaryValue` calls `formatActiveMoney(value, baseCurrency.value ?? { code: 'AFN' })` (code-once formatting from `utils/money.ts`), and the base currency is loaded from `CurrencyApi.getAll()` (`isBase`). The `${{ … toFixed(2) }}` template was removed; `grep` of the money path shows no `$` prefix or `toFixed`.
- [x] Given a payroll amount, when the register renders, then the value is formatted once with the shared formatter, with no suffix symbol or duplicated symbol plus code. — Evidence: `formatAmount` calls `formatActiveMoney(amount, { code: currencyCode.value, decimalPlaces: 2 })`; every register/KPI/deduction value goes through it. No hand-built symbol concatenation remains.
- [x] Given the payroll page, when it loads, then its description is real explanatory copy and no placeholder "Description" text is visible. — Evidence: the KPI header now uses new key `pages.payrolls.subtitle` = "Generate, pay and reconcile each month's payslips. Amounts are shown in the company's base currency." in `en.json`, with matching Dari/Pashto copy. `pages.payrolls.description` is kept as the literal deduction-field label ("Description"), which is its legitimate second use in this page.
- [x] Given a 390 px viewport, when the timesheet loads, then exactly four KPIs appear in one horizontally scrollable row and no KPI starts a second row. — Evidence: `timesheet.vue` still renders exactly four `VCol` KPI cards; `.timesheet-kpi-strip` is `flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none` and each child is `flex: 0 0 min(76vw, 15rem)` until 1280 px, so four cards form one scrollable row. Source/CSS inspection only; no browser capture was taken (see limitations).
- [x] Given the attendance ledger, when it renders with data, then it has no Date or Role header or cell, each remaining column has a usable width, the shared skeleton and empty state are used, and wide data expands and scrolls without a visible scrollbar. — Evidence: ledger headers are Employee, Office, Status, In Time, Out Time, Worked, Overtime, Action (no Date/Role); `TableLoadingSkeleton` and `TableEmptyState` render before the table; `.attendance-table-wrapper { overflow-x: auto; scrollbar-width: none }` with `.custom-attendance-table { min-inline-size: 68rem }`, so cells keep their width and the wrapper scrolls with a hidden scrollbar. Source/CSS inspection only.
- [x] Given the leave requests page, when it renders at any width, then the sideways-scroll hint is absent, the shared skeleton and empty state are used, and no table column is clipped. — Evidence: `:show-scroll-hint="false"` plus `TableLoadingSkeleton` and `TableEmptyState` in `leave_requests.vue` (unchanged in this pass; re-read and confirmed).
- [x] Given a leave request detail dialog, when each tab is opened, then section titles and field labels are visible, and the actor and approval note are shown without a raw key or blank label. — Evidence: `leave_requests.vue` `detailFields` maps employee, date, duration, treatment, off-day days, absent days, reason, `approvedBy.name` and `approval_note`; all nine locale keys resolve to strings in `en`, `fa-AF` and `ps-AF` (Node assertion). Section titles are rendered by the shared `FullDetailsButton` viewer, which was not modified.
- [x] Given staff or payroll row actions at 1280 px, when they are reached by keyboard or screen reader, then each icon control has an accessible name and no action is hidden without a discoverable affordance. — Evidence: every icon-only `VBtn` now carries an explicit `aria-label`: staff print-contract, off-day ledger, edit and delete were added (attachments and status toggle already had them); payroll create, print payslip, add deduction, add payment, delete payslip, advance receipt and advance delete were added. Reachability/overflow comes from `ResponsiveDataTableServer` (Task-013) and is unchanged; no browser AT pass was run.
- [x] Given payroll generation creates some rows and skips others, when it succeeds, then the toast shows the real created and skipped counts and never `undefined`. — Evidence: `SalaryApi.generate` returns typed `created`/`skipped` unwrapped from the double envelope, and `generateMissing` passes both to `pages.payrolls.generatedCount` (verified by re-reading the API helper and handler).
- [x] Given a manual attendance save succeeds or fails, when the request completes, then a toast names the employee, date and outcome, and a failure shows the translated server reason exactly once. — Evidence: `logAttendance` success toast is `employee · date · status`; the failure toast is `employee · date · apiErrorMessage(error)` (translated server reason) and the record-delete path also reports success/failure. Page-level duplicate error toasts were not reintroduced; API failure feedback stays with the Task-018 notifier.
- [x] Given the staff dialog is reopened for a different employee, when it renders, then no inline error from the previous record remains, and a position change that would alter the salary requires explicit confirmation. — Evidence: both `openAddStaffDialog` and `openEditStaffDialog` call `resetFormErrors()` before opening. `applyPositionSalary` now opens `isPositionSalaryDialogVisible` whenever the position catalogue salary differs from the current salary; `confirmPositionSalary` applies the catalogue salary, and `cancelPositionSalary` restores `positionBeforeChange`. Escape/outside-click close routes through `handlePositionSalaryDialog`, which also restores the previous position. Equal or missing catalogue salaries are not rewritten, so no prompt is shown.

## Work completed with evidence
- Staff directory salary now uses the shared money formatter with the workspace base currency code; the hard-coded `$` and `toFixed(2)` were removed and the base currency is loaded with the other reference data.
- Payroll page description replaced with real copy through a new `pages.payrolls.subtitle` key in all three locales; the deduction field label keeps the existing `description` key.
- Position changes that would revalue pay now require an explicit confirmation dialog; cancel/escape restores the previous position, and unchanged catalogue salaries skip the prompt.
- Accessible names added to the remaining icon-only staff and payroll row actions.
- Verified as already present and unchanged: payroll generation counts, attendance toasts, leave page hint/skeleton/empty state, four-card timesheet KPI strip CSS, ledger column set and scroll container, leave detail label mapping.

## Files changed and why
- `resources/ts/pages/staff_list.vue` — base currency load + shared salary formatting, position/salary confirmation dialog and state, `aria-label`s on icon actions.
- `resources/ts/pages/payrolls.vue` — KPI description uses the new subtitle key; `aria-label`s on all icon-only row actions.
- `resources/ts/plugins/i18n/locales/en.json` — `pages.payrolls.subtitle` and the two `positionSalary*` keys.
- `resources/ts/plugins/i18n/locales/fa-AF.json` — same three keys in Dari.
- `resources/ts/plugins/i18n/locales/ps-AF.json` — same three keys in Pashto.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `npm run typecheck` | Node | Pass (exit 0) | — | After the earlier payroll/timesheet edits. |
| 2026-11-01 | `npm run typecheck` | Node | Pass (exit 0) | `/tmp/typecheck-task006.log` | After all Task-006 edits. |
| 2026-11-01 | Node locale assertion | Node 24 | Pass — three new keys present with `{position}`/`{salary}` placeholders | terminal output | `en`, `fa-AF`, `ps-AF` |
| 2026-11-01 | `npm run i18n:check` | Node 24 | See result recorded below | terminal output | Catalog consistency after the key additions |
| 2026-11-01 | `git diff --check` | Local | Pass (exit 0) | terminal output | No whitespace errors |

## Decisions and deviations
- The payroll page description reuses a new `subtitle` key instead of rewriting `pages.payrolls.description`, because that key also labels the deduction description field and column; rewriting it would have renamed a form field.
- Base currency falls back to `{ code: 'AFN' }` only when the catalog has no base currency, matching `payrolls.vue`'s existing fallback, so the value is never a bare number.
- Position-change confirmation compares the position catalogue salary with the current salary; selecting a position with an identical catalogue salary does not prompt, because nothing is rewritten.
- Manual viewport screenshots were not captured in this pass; the layout criteria are evidenced by source/CSS inspection and remain a reviewer visual check.

## Blockers and open questions
- None blocking. Reviewer-only: 390 px KPI strip render, 768/1280 px action reachability, RTL/dark appearance.

## Handoff notes
- All Task-006 acceptance criteria are implemented and recorded above. The task is ready for independent review.
- The repository has no Vue component test harness: `resources/ts` contains zero `*.spec.ts`/`*.test.ts` files and `package.json` has no unit/component test script, so no frontend test was added. Evidence is typecheck + locale assertions + source/CSS inspection.
- The leave-detail mapping was verified against locale catalogs, not by opening the dialog in a browser.
- Keep the `subtitle`/`description` split: `pages.payrolls.description` is a field label, not the page description.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from the validated HR findings; implementation had not started.
- `2026-10-31` Fixed payroll money formatting and timesheet feedback; recorded remaining gaps. Status moved to `In Progress`.
- `2026-11-01` Implemented the remaining gaps: staff base-currency salary formatting, payroll subtitle copy, position-change salary confirmation, accessible names on staff/payroll icon actions. Verified existing KPI strip CSS, ledger columns/scroll container, leave hint/skeleton/empty state and leave detail labels. `npm run typecheck`, locale assertions and `git diff --check` pass. Status moved to `Review`.
