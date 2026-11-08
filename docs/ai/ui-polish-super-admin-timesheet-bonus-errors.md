## Task: Super-admin menu cleanup, timesheet off-day guards, form polish, bonus-rules validation, created-row ordering and dropdown scrollbars

- **Task ID:** ui-polish-super-admin-timesheet-bonus-errors
- **Created:** 2026-11-08
- **Last updated:** 2026-11-08
- **Status:** COMPLETED
- **Outcome:** All eight requested fixes are implemented. The automatic bonus dialog and the super-admin menu were verified in a real browser with Playwright against the running app.

### 1. Requirements and acceptance criteria

- [x] Super admins no longer see Start my day / End my day in the account menu.
- [x] Super admins no longer see My work history or My off days in the account menu (and the work-history account tab is not offered to them).
- [x] The timesheet roll logger is disabled for a company off day, and the reason is visible.
- [x] Off-day attendance rows have no delete action, and the API refuses to delete them with an informative error.
- [x] Browser autofill never repaints a field background (light/dark, LTR/RTL).
- [x] After a successful create, the new row is first in the page's data table; later loads keep the server order.
- [x] Bonus KPI label reads "Granted payout pool" (no "(current base)").
- [x] Automatic bonus dialog validates fields client-side, shows red inline field errors with reasons, and shows one toast for backend/logical errors. No `code.startsWith is not a function` crash.
- [x] A numeric/array API error payload can never crash error humanization.
- [x] Every dropdown list has a slim scrollbar with no scroll-arrow buttons.
- [x] Playwright confirms the automatic bonus dialog opens, validates inline, saves, and reports a backend rejection with a toast.

### 2. Scope and non-goals

- In scope: `resources/ts/layouts/components/UserProfile.vue`, `resources/ts/pages/account-settings.vue`, `resources/ts/pages/timesheet.vue`, `app/Http/Controllers/AttendanceController.php`, `resources/ts/utils/apiErrors.ts`, `resources/ts/components/bonus/BonusRulesDialog.vue`, `resources/ts/pages/bonuslist.vue`, locale files, global styles, shared create-order helper and the list pages with create flows.
- Out of scope: redesigning tables, changing API list ordering, new dependencies, schema changes.

### 3. Project findings

- `UserProfile.vue` owns both the workday control and the "My off days" entry; `account-settings.vue` owns the work-history tab. `userData.roles` already drives super-admin checks elsewhere.
- Timesheet delete is `AttendanceController::destroy`; it previously deleted `off_day` rows. Company off days live in `/off-days` and are exposed by `OffDayApi.list`.
- The bonus dialog already reads `apiFieldErrors`, but `humanizeApiError` assumed `code` was a string. Non-string `errors` payload values crashed `isErrorCode`.
- Vuetify fields receive the browser autofill background on the native input; global CSS is loaded from `resources/styles/styles.scss` (after `@layouts/styles/index.scss`).
- Tables are mostly `ResponsiveDataTable` (Vuetify `VDataTable`); create handlers either prepend already or reload and lose the new row.
- KPI string lives in `common.ui.grantedPayoutPoolCurrentBase` in `en.json`, `fa-AF.json`, `ps-AF.json`.
- Playwright discovered a success-path bug in the bonus dialog: `structuredClone(ruleSet.value.rules)` threw on Vue's reactive proxy. The raw API response is now cloned before it enters the ref.

### 4. Graph and impact review

- Used repository search + static inspection (graph artifacts are stale, no graph tool invocation available this session).
- Checked call sites of `humanizeApiError` / `apiErrorMessage` (shared notifier, settings validation, settings page) so defensive changes stay compatible.
- Checked attendance delete UI/API pair, off-day API shape, locale key usage, and global style import order.
- Audited every `.create`/`.createX` API call in `resources/ts` for a table row update and added created-row ordering where a list reloads.

### 5. Clarifications, decisions, and assumptions

- "Start/end day nav button" means the `WorkdayControl` menu entry. It is hidden in the profile menu for super admins; backend workday rules are unchanged.
- "Timesheet disabled for an off day" means the roll logger is disabled and an explanatory alert is shown; off-day rows also cannot be deleted.
- "New row first" is implemented in the create handler only. Later `load()`/`fetch` calls keep server order.
- Backend errors are surfaced through the existing localized API message contract; no new error channel is added.
- Dropdown scrollbar means teleported Vuetify overlay lists (`VSelect`, `VAutocomplete`, `VCombobox`, menus); scrollbar buttons are hidden with `::-webkit-scrollbar-button`.
- The bonus dialog sends `X-Suppress-Error-Notification` and owns its own field errors and single toast, so the global notifier cannot duplicate them.

### 6. Implementation plan

- [x] Hide workday control, work history and off-days entries for super admins.
- [x] Block off-day attendance deletion in the API and hide the row action; disable the logger when the log date is an approved company off day.
- [x] Add global autofill-safe field CSS.
- [x] Add shared created-row ordering helper and apply it to create handlers that reload lists.
- [x] Remove "(current base)" from all three locale strings.
- [x] Harden `apiErrors` against non-string codes and add bonus-rules client validation with inline errors + toast.
- [x] Add global slim dropdown scrollbar CSS without scroll buttons.
- [x] Verify with typecheck, focused attendance tests and Playwright.

### 7. Validation plan and results

- [x] `npm run typecheck` — exits 2 because of pre-existing errors in untouched files: `pages/applicant-case.vue`, `pages/staff_list.vue`, `pages/support.vue`. No error was reported in any file changed by this task, including `BonusRulesDialog.vue`, `commissions.vue` and `service-contracts.vue`.
- [x] `php artisan test --filter='Attendance|OffDay'` — 39 passed, 1 failed, 289 assertions. The only failure is the pre-existing `Tests\Feature\PhaseSixWorkdayTest > hr can correct a missed check` assertion that an `attendances` audit row exists for an admin action; it is outside this change.
- [x] `php -l` — passed for `AttendanceController.php`, `routes/api.php`, and `lang/{en,fa,ps}/api.php`.
- [x] `npm run i18n:check` — exits 1 for pre-existing missing/unused keys; the new keys were not reported missing.
- [x] Playwright, system Chrome, real app on `127.0.0.1:8000`:
  - Super-admin account menu contains no "My work history", "My off days", "Start my day" or "End my day".
  - Bonus list KPI reads "Granted payout pool" with no "(current base)".
  - Zero or negative numeric values turn the field red with the inline reason and do not submit.
  - A 422 field payload whose message is an object renders the message inline and leaves the dialog open; no `code.startsWith` console error.
  - A non-field 422 business rejection shows one explanatory toast.
  - A real save closes the dialog and shows the success toast.
- Results: 2 Playwright tests passed after fixing the reactive-proxy clone bug. Temporary test files were removed after the run.

### 8. Risks and mitigations

- Global autofill CSS must not force a fill color that conflicts with outlined fields — use the delayed-background technique and theme text color.
- Scrollbar rules are scoped to overlay lists so page/table scrollbars are unchanged.
- Created-row ordering mutates only the visible array in the create handler; filtering, pagination totals and edit flows are untouched.
- Backend field errors are normalized to strings before rendering; unexpected error shapes still fall back to a translated/generic message instead of throwing.

### 9. Progress log

- 2026-11-08 — DISCOVERY: traced menu ownership, timesheet delete, off-day API, bonus dialog, error humanizer, table create handlers, i18n keys and global style entry points.
- 2026-11-08 — PLAN: plan saved; implementation started.
- 2026-11-08 — IMPLEMENTATION: super-admin menu/tab hiding, off-day timesheet guards and API refusal, autofill CSS, created-row helper across create flows, KPI copy, apiErrors hardening, bonus dialog validation/quiet save, dropdown scrollbar CSS.
- 2026-11-08 — PLAYWRIGHT: menu and bonus dialog verified. Found and fixed the success-path `structuredClone` reactive-proxy failure; final run passed both tests.
- 2026-11-08 — REVIEW: added created-row ordering to the remaining reload-based commission and service-contract create flows; focused typecheck clean for all touched files.

### 10. Completion summary

- Delivered: all eight requested fixes.
- Acceptance criteria: met, except that the full typecheck and the focused attendance suite still report the pre-existing failures documented above.
- Checks: Playwright 2/2 passed; focused typecheck clean for touched files; PHP syntax clean.
- Remaining: none for this task.
