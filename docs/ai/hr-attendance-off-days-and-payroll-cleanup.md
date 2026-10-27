## Task: HR cleanup — company off days vs off-day requests, three-status attendance and attendance-derived payrolls

- **Task ID:** hr-attendance-off-days-and-payroll-cleanup
- **Created:** 2026-10-26
- **Last updated:** 2026-10-27
- **Status:** COMPLETED
- **Outcome:** HR now has two distinct ledgers — **Company Off Days** (holidays) and **Off Day Requests** (employee leave) — attendance has exactly three statuses (`present`, `absent`, `off_day`), the per-employee allowance decides which approved request days are off days and which are absences, missing elapsed workdays are materialized under a configurable policy, and Staff Salary is replaced by an attendance-derived **Payrolls** workspace. Employee self-service profile editing is gated by its own permission and limited to personal fields. The same source is ported into the multi-tenancy worktree with its own migration set and bundle.

### 1. Requirements and acceptance criteria

- [x] HR navigation has two unambiguous pages: **Off Day Requests** (`/leave-requests`) and **Company Off Days** (`/off-days`). The company page heading uses `pages.offDays.companyTitle` in all three locales; the redundant `pages.offDays.title` key is gone.
- [x] `attendances.status` is limited to `present`, `absent`, `off_day` in migration, model, validation, API, filters, translations, timesheet, reports, analytics and payroll. No `paid-leave` / `unpaid-leave` remains.
- [x] An approved request is split by the employee's allowance: covered days are `off_day` (not deducted), the excess is `absent` (deducted). `grant_leave_requests` holders can force an over-allowance day to stay `off_day` with a recorded justification.
- [x] An approved company off day is `off_day` with `off_day_days = 1`, never absent, and never deducts pay.
- [x] The company-wide allowance setting is a creation-time catalogue value that prefills `employees.off_day_allowance_days`; only the employee value governs approvals. `offices.paid_leave_days_per_year` is dropped and must not return.
- [x] Settings → Operations exposes `missing_attendance_status` (`present` / `absent` / `off_day`); missing elapsed non-Friday workdays are materialized with that status, never over a holiday/leave/recorded work, and never for today or future days.
- [x] Settings → Operations shows the bad-weather accuracy warning.
- [x] Salary worked/total/absent/off days are attendance-derived and read-only; the API no longer accepts day overrides.
- [x] The automatic absence deduction is `amount_paid × absent_days ÷ total_days`; off days and present days are never deducted; manual deductions stay additive.
- [x] Payslips show total working days, worked days, absent days and off days; paid/unpaid leave metrics are removed.
- [x] **Payrolls** (`/payrolls`) replaces Staff Salary: monthly KPIs, active employees for the month, generation of missing payslips, deduction entry, payment recording, payslip preview/print, register print and the advances ledger/receipt.
- [x] Payroll totals use net/amount due; the monthly KPIs show net payroll, paid and outstanding.
- [x] `edit_own_employee_profile` is added. Without it an employee cannot update their HR record; with it they may change only personal/contact/guarantor/bank fields, and any other submitted field is rejected with 403.
- [x] All new labels exist in `en`, `fa-AF` and `ps-AF`; the new pages follow the existing RTL/LTR, light/dark and responsive conventions.
- [x] Audit registry labels, report sections, permissions, seeders, tests and docs use the new vocabulary.
- [x] `main` changes are mirrored into `../educational-multi-tenancy` with its own office migration and rebuilt bundle; no branch merge and no generated output copied between worktrees.
- [x] `npm run typecheck`, `npm run i18n:check`, `npm run build:icons` and `npm run build` pass on `main`; focused PHP sets pass on both worktrees. The full suite was not completed — see §7.

### 2. Scope and non-goals

- **In scope:** HR migrations, models, services, controllers, resources, routes, permissions, attendance/leave/payroll/report/analytics/settings behavior, frontend API contracts, HR pages and navigation, timesheet and print templates, translations, audit labels, tests, AGENTS/docs, generated assets, multi-tenancy mirror.
- **Out of scope:** re-architecting tenancy or Drive storage, websockets/broadcast, contract/payment/commission rules, non-HR modules, payroll tax/insurance engines, and compatibility with the removed `paid-time-off` URLs, statuses or day-override fields.
- **Compatibility:** intentionally none for the removed statuses and route names. The development database is migrated forward; no production data migration contract is offered.

### 3. Project findings (before the change)

- HR navigation showed **Leave** (`/leaves`) and **Off days** (`/off-days`) with near-identical, empty pages; the user reported the naming/logic conflict.
- `attendances.status` was `present` / `absent` / `paid-leave` / `unpaid-leave`, and every consumer branched on those values.
- `leaves` (model `PaidTimeOff`, alias `Leave`) stored `is_granted`, `is_paid`, `paid_days`, `paid_override`, `approval_note`. The controller resolved allowance as employee → office → global.
- Salary math deducted `total_days - worked_days`, so off days and leave were deducted like absences, and worked/total days were client-editable.
- Staff Salary already carried salary rows, a months tab and advances; Payrolls absorbs that surface.
- `EmployeeScope::canManageOwnedBy` allowed the user's own employee row while `EmployeeController::update` required `edit_employees`, so self-edit needed its own permission plus field restrictions.
- Tests encoding the old behavior: `SalaryAttendanceDeductionTest`, `PaidLeaveGrantTest`, `HrPayrollOffDaysTest`, `BusinessLogicConsistencyTest`, `PhaseSevenWorkingTimeTest`, `ReportedIssueRegressionTest`, `PhaseSixWorkdayTest`, `PhaseEightReportsTest`.
- Baseline: typecheck PASS, i18n check PASS, focused HR tests 14 passed / 81 assertions after creating the missing local `testing` database.

### 4. Graph and impact review

- **Graph artifact:** `graphify-out/GRAPH_REPORT.md` (built from `c4c97435`, stale) used for orientation only.
- **Fallback methods:** repository-wide search for `paid-leave`/`unpaid-leave`/`paid_time_off`/`PaidTimeOff`/`paid_leave`; direct reads of models, services, controllers, resources, migrations, seeders, routes, pages and locales; baseline and focused test runs; Playwright inspection of the live HR pages before and after.
- **Downstream effects:** attendance persistence and status math, leave grant/revert, holiday apply/revert, monthly summaries, salary counts/deduction/net math, payslip and timesheet printing, reports/analytics, permission names and role grants, audit labels, employee creation defaults, self-edit authorization, routes/navigation, all three locales, generated output, and the tenant office migration set.
- **Limitations:** the graph is stale; physical print output cannot be verified here; the tenant tree carries a large divergent surface that must be resolved manually.

### 5. Clarifications, decisions and final naming

- **User decisions:** over-allowance days become `absent` (with the existing forced off-day exception kept and justified); missing elapsed workdays are materialized; Payrolls replaces Staff Salary and absorbs advances; self-edit RBAC protects the HR employee profile only; the rename goes as deep as backend classes, routes and permission keys.
- **Implemented identifiers:**
  - `attendances.status` = `present|absent|off_day`, plus `leave_days` (0, 0.5 or 1), `off_day_days`, `source` (`manual|self_service|holiday|leave|missing_policy`), `leave_request_id`, `off_day_id`.
  - `leaves.off_day_days` and `leaves.off_day_override`; `is_paid`, `paid_days` and `paid_override` are dropped.
  - `employees.off_day_allowance_days` (renamed from `paid_leave_days`), `settings.off_day_allowance_days_per_year` (renamed), `settings.missing_attendance_status` (default `absent`, added after `default_break_minutes`).
  - Models/controllers: `LeaveRequest` (table `leaves`), `LeaveRequestController`, `LeaveRequestResource`, `LeaveRequestService`; `PaidTimeOff`, the `Leave` alias, their controller and resource are deleted.
  - Routes: `/leave-requests`, `/my-leave-requests`, `/employees/{employee}/leave-requests`, `PATCH /leave-requests/grant`, `POST /salaries/generate`; `/staff-salary` is only a redirect to `/payrolls`.
  - Permissions: `view|create|edit|delete_leave_requests`, `request_own_leave`, `grant_leave_requests`, `print_leave_requests`, plus `edit_own_employee_profile`. The migration renames existing permissions in place so role grants survive.
- **Assumptions:** demo/dev data may be migrated destructively; no old mobile clients exist; the demo admin credentials are for local verification only.

### 6. Implementation

- **Schema (`database/migrations/2026_10_26_000001_simplify_hr_attendance_and_payroll.php`):** converts existing rows before dropping columns — `paid-leave` → `off_day`, `unpaid-leave` → `absent`, paid fractions → `off_day_days`, inferred `source`; migrates `leaves.is_paid/paid_days/paid_override` into `off_day_days/off_day_override`; folds paid-leave days into `salaries.off_days` and `monthly_attendance_summaries.off_days`; renames `daily_analytics` / `monthly_analytics` `attendance_paid_leave` → `attendance_off_day`; drops the office allowance; renames permissions and inserts `edit_own_employee_profile`. `down()` restores the previous shape.
- **Attendance (`MonthlyAttendanceProcessor`, `AttendanceCalculationService`):** materializes missing elapsed non-Friday workdays (clipped to `join_date`, excluding today/future), preferring an approved company holiday, then an approved request, then `missing_attendance_status`; summarizes present fractions (`1 - leave_days`), `off_day_days`, and `absent = total - present - off`; refreshes employee worked-hour totals. `source` marks policy rows so they can be corrected.
- **Leave (`LeaveRequestService`):** applies a request to the ledger — allowance-covered days become `off_day`, the remainder `absent`; a half day that already has work stays `present` and carries both fractions; a company holiday wins over the request; full-day requests against recorded work are rejected (`business.leave_request_conflicts_with_attendance`). `release()` clears the claim (force-deleting synthesized rows, keeping worked days) and re-runs the month. `usedAllowance()` sums `off_day_days` of granted requests; `rangeDates()` skips Fridays.
- **Holidays (`OffDayService`):** writes `off_day` rows with `off_day_days = 1` and `source = holiday`, never overwrites worked/leave rows, and deletes or restores the synthesized rows on revert/delete.
- **Salary (`SalaryController`):** day counts come from the attendance ledger and the calendar (elapsed only, clipped to `join_date`); the automatic `attendance_proration` deduction is absent-only; `amount_paid` stays immutable after creation; `generate()` creates missing payslips per employee in its own transaction and reports created/skipped/errors.
- **Employees (`EmployeeController`):** `updateOwnProfile()` accepts only personal/contact/guarantor/bank fields and returns 403 for anything else; `edit_employees` keeps full access and `EmployeeResource` exposes the new allowance field.
- **Settings:** request validation, resource defaults, frontend contract/validation and the Operations panel expose the missing-day policy, the catalogue-only allowance copy and the bad-weather warning.
- **Frontend:** `payrolls.vue` (KPIs, month picker, generation, payslip table with worked/total, off days, absent, net/paid/outstanding/status, deduction and payment dialogs, payslip preview/print, register print, advances tab and receipt) replaces `staff_salary.vue`; `leave_requests.vue` replaces `paid_time_offs.vue`; `api/leaveRequests.ts` replaces `api/paidTimeOff.ts`; `attendance.ts`, `salary.ts`, `setting.ts`, `office.ts`, `employee.ts`, `report.ts`, the RBAC mirror and validation maps are updated; the timesheet, payslip, receipt and report surfaces drop paid/unpaid wording.
- **Audit flag guard (`AuditLog`):** the memoized `Schema::hasColumn` check was replaced with a narrow `QueryException` guard for a missing `unread_audit_logs` column. A cached "true" from an earlier class aborted `migrate:fresh` midway in multi-class test runs, leaving the shared `testing` schema at an older migration set. The guard has no tenancy content and the tenant branch already carried the same handling.

### 7. Validation plan and results

**`main` (production worktree)**

- `npm run typecheck` — **pass** (exit 0, no diagnostics).
- `npm run i18n:check` — **pass**: 298 source files, 3124 used keys, 4698 English keys, no "Missing in …" section.
- `npm run build:icons` — **pass**: 301 icon references scanned and generated.
- `npm run build` — **pass**: Vite build plus `injectManifest` service worker, 248 precache entries (6381.04 KiB), `public/sw.js` generated.
- `php artisan migrate:fresh` against MySQL (`DB_DATABASE=testing`) — **pass**: all 73 migrations including `2026_10_26_000001_simplify_hr_attendance_and_payroll`, ~1s for the new one.
- Focused HR set — **pass**: 87 tests / 570 assertions in 222.64s (`HrPayrollWorkspaceTest`, `LeaveRequestGrantTest`, `HrPayrollOffDaysTest`, `SalaryAttendanceDeductionTest`, `PhaseSevenWorkingTimeTest`, `PhaseSixWorkdayTest`, `BusinessLogicConsistencyTest`, `ReportedIssueRegressionTest`, `PhaseEightReportsTest`, `OfficeGeofenceAttendanceTest`, `EmployeeAdvanceTest`). Covers the allowance split, forced over-allowance day, half day, missing-day policy, generation idempotency, self-edit permission and field refusal, holiday rows, advance recovery, absent-only deduction and the audit heartbeat.
- Settings/audit set — **pass**: 27 tests / 161 assertions (`AuditManagementTest`, `CompanySettingsTest`, `SettingsEmailAndFileRemovalTest`), after the audit guard fix; the schema stays at 73 migrations afterwards.
- `AuthFlowTest` — **pass**: 6 tests / 26 assertions (unrelated-area sanity check).
- Browser (`http://127.0.0.1:8000/build/...`, demo admin): navigation shows **Off Day Requests**, **Company Off Days**, **Payrolls**; Payrolls renders 6 payslips with net/paid/outstanding KPIs, the deduction dialog lists the automatic `Attendance deduction` beside a manual `Late arrival fine`, recording a payment moves the row to **Paid** with paid = net and outstanding 0, and the payslip preview shows total 22 / worked 4 / off 0 / absent 18 with net payable and no paid/unpaid leave wording; Off Day Requests and Company Off Days carry the new descriptions; Settings → Operations shows the allowance catalogue copy, the missing-day select and the weather warning; switching to Dari sets `dir="rtl"` and translates the page title and KPIs.
- `php artisan test` (full suite) — **not completed, not counted as a result.** The first attempt ran ~2h with the shared `testing` database being rebuilt underneath it and produced spurious whole-file failures; it was stopped. A second clean run was skipped because another task was actively running tests and schema changes in the same worktree.

**Multi-tenancy worktree (`../educational-multi-tenancy`)**

- Source changes applied as ordinary edits (no merge, no cherry-pick); the new migration lives in `database/migrations/office/`; `BelongsToOffice`, office scoping and the Drive integration are preserved.
- Port defect found and fixed: the new `LeaveRequest` model had lost `BelongsToOffice`, so tenant leave rows would not be office-stamped or scoped. The trait was restored and the tenant HR tests re-run green.
- `npm run build` — **pass**: `injectManifest`, 266 precache entries (6561.72 KiB), `public/sw.js` generated.
- `npm run typecheck` — **pass** (exit 0).
- `npm run i18n:check` — no missing keys; exit code 1 is pre-existing and caused by two literal strings (`MySQL`, `SQLite`) in the tenant-only `resources/ts/pages/saas/offices.vue`.
- Focused HR set after the model fix — **pass**: 31 tests / 181 assertions in 29.53s; the wider tenant HR run before the fix was 73 tests / 468 assertions.
- Not re-run on the tenant: the full PHP suite.

**Known gaps**

- SQLite `migrate:fresh` cannot be exercised in this environment: PHP has no `pdo_sqlite` ("could not find driver"). From-scratch MySQL coverage comes from `RefreshDatabase` and the manual `migrate:fresh` above.
- Physical print output (A5 payslip, register, timesheet) was not produced; print/preview DOM and the shared `printDocument` workflow are unchanged in structure, and the payslip preview was inspected in the browser.
- Dark theme was not separately browser-verified for the new pages; they use standard Vuetify cards, tables and dialogs, and the theme tokens are unchanged.

### 8. Risks and mitigations

- **Data migration on MySQL/SQLite** — the migration converts before dropping, and runs down as well as up; verified from scratch on MySQL. SQLite remains unverified locally because the driver is absent.
- **Double-counting off days** — one fraction per attendance row (`off_day_days`) and one salary snapshot (`salaries.off_days`); holidays and approved requests both write that field, never the removed paid columns.
- **Half day becoming a fourth state** — it stays `present` with `leave_days` and `off_day_days` fractions; salary derives present = `1 - leave_days` and deducts only the uncovered part.
- **Missing policy overriding real work** — rows are created only when none exists (including trashed), never over punches, reports, holidays or leave, and carry `source = missing_policy`.
- **Payroll generation partial failure** — one transaction per employee, existing payslips skipped, failures collected and returned per employee.
- **Replacing Staff Salary breaks links** — `/payrolls` is the page, `/staff-salary` redirects, and navigation/report links were updated.
- **Permission rename breaks grants** — names are migrated in place (IDs unchanged) and every middleware, seeder and RBAC mirror entry was updated.
- **Shared test database flakiness** — the audit flag guard no longer aborts a mid-test schema rebuild; reruns on a complete schema are deterministic. A stale `testing` database must be recreated before a suite run after an interrupted migration.
- **Generated output and tenant drift** — each branch built its own bundle; `public/build`, `public/sw.js` and `public/version.json` were never copied across.

### 9. Progress log

- 2026-10-26 — DISCOVERY: reviewed AGENTS/docs, HR navigation, the live app, attendance/leave/holiday/payroll models, migrations, services, controllers, resources, routes, seeders, permissions, locales, reports, print templates, employee scope and tests.
- 2026-10-26 — BASELINE: created the missing local `testing` database; typecheck PASS, i18n PASS, focused HR 14/14.
- 2026-10-26 — CLARIFICATION: user chose absent-beyond-allowance with the forced exception, materialized missing rows, Payrolls replacing Staff Salary, HR-profile-only self-edit RBAC and a deep backend rename.
- 2026-10-26 — PLAN: full plan recorded before implementation.
- 2026-10-26/27 — IMPLEMENTATION: schema and data migration; models and services; attendance/leave/holiday controllers, resources and routes; salary generation and absent-only deduction; settings; backend and frontend contracts; Payrolls and Off Day Requests pages; timesheet/report/print vocabulary; self-edit RBAC; translations, audit labels, permissions and docs; generated icons and bundle on `main`.
- 2026-10-27 — VALIDATION (`main`): typecheck, i18n, icon build and bundle pass; focused HR 87/570; settings/audit 27/161; browser checks of Payrolls, off-day pages, settings and RTL.
- 2026-10-27 — FIX: replaced the memoized audit-column check that aborted `migrate:fresh` in multi-class runs; recreated the stale `testing` database and re-ran the affected set green.
- 2026-10-27 — MIRROR: applied the source into the tenant worktree with its office migration, restored the missing `BelongsToOffice` on `LeaveRequest`, rebuilt the tenant bundle and validated (typecheck, i18n, focused HR 31/181).
- 2026-10-27 — CLOSE: record updated; nothing committed in either worktree.

### 10. Completion summary

- **Delivered:** company off days and off-day requests are separate ledgers; attendance has three statuses with leave/off-day fractions and a missing-day policy; the employee allowance decides off days versus absences; salaries and payslips are attendance-derived with an absent-only automatic deduction; Payrolls replaces Staff Salary with generation, deductions, payments, advances and printing; self-service HR profile edits are permission-gated and field-restricted; the same source lives on the multi-tenancy branch with its own migration and bundle.
- **Acceptance criteria satisfied:** all of §1, verified per §7; the full-suite and SQLite gaps are declared there.
- **Tests/checks passed:** `main` typecheck, i18n, icons, build, `migrate:fresh`, focused HR 87/570, settings/audit 27/161, AuthFlow 6/26, browser verification; tenant typecheck, i18n (no missing keys), build, focused HR 31/181.
- **Not run / limitations:** full PHP suite (earlier attempt invalidated by concurrent schema rebuilds, second run deferred); SQLite fresh migration (no `pdo_sqlite`); physical print output; dark-theme visual check.
- **Follow-up:** when committing, commit `main` first, then port the ported `main` SHAs as their own tenant commit and rebuild the tenant bundle (`docs/facts/branch-mirroring.md`); record the SHAs in the tenant `docs/ai` mirror record.
