## Task: HR payroll hardening — guarantor name, advances, off days, payslips and real-time audits

- **Task ID:** hr-payroll-off-days-audit-realtime
- **Created:** 2026-10-18
- **Last updated:** 2026-10-18
- **Status:** COMPLETED
- **Outcome:** Make sense of the jargoned HR/payroll notes and deliver them as one coherent slice: guarantor name on employees, a usable advances ledger with printable receipts, a fixed/immutable salary amount with advance recovery, modernized payslips that show off days and advances, monthly payroll visibility for finance officers, managed off days that flow into attendance/payroll/payslips, leave-day parity in the staff dialog, per-employee paid-leave allowance seeded from the office, a heartbeat-based real-time audit channel, PWA caching for the new assets, and loader-free forms.

### 1. Requirements and acceptance criteria

- [x] `notification-ding.mp3` is served from exactly one real location (`public/sounds/`); the stray repository-root copy is removed and the production path is documented.
- [x] Employees store a guarantee/guarantor **name** in migration, model, validation, API resource, factory/seeder and the staff form.
- [x] The salary-advances tab renders correctly (valid table structure, totals, no dangling markup) and every advance can be previewed and printed.
- [x] An advance prints as a formal two-copy receipt (employee copy + company copy) on one A5 page, with employee/company signature areas.
- [x] A salary's base amount is immutable after creation (API rejects changes; UI is read-only on edit).
- [x] Outstanding advances for the employee up to the salary period end are recovered from the salary, capped so net pay never goes negative, and released if the salary is deleted.
- [x] The payslip lists off days and recovered advances separately, and totals reconcile with the salary record.
- [x] Payroll is reachable and fully usable for the finance role; month filter and per-month printable payroll summary exist.
- [x] Off days can be managed from a dedicated HR ledger: date range, reason, approval by a holder of a dedicated permission; approved off days mark attendance rows, count as present in payroll and appear separately on the payslip.
- [x] The staff page leave dialog mirrors the self-service leave-request dialog (date range + reason + duration).
- [x] Audit logs get a notification-style real-time channel: a per-user flag, a heartbeat endpoint that returns-and-clears, and a frontend poller driving the audit badge.
- [x] PWA precache covers the notification sound so the chime works offline.
- [x] Paid leave allowance is per employee, auto-filled from the office base and falling back to the global setting.
- [x] Form loader animations are removed (service contract form and the HR payroll forms).

### 2. Scope and non-goals

- In scope: migrations, models, services, controllers, resources, routes, permissions/role grants, translations (en/fa/ps), API contracts, Vue pages/components, printing, PWA config, docs/AGENTS, feature tests.
- Out of scope: websocket/broadcasting (delivery stays HTTP heartbeat), restoring removed invoice/commission-company ledgers, redesigning unrelated pages, payroll tax/insurance engines, multi-office payroll splitting.

### 3. Project findings

- `notification-ding.mp3` exists at the repo root **and** at `public/sounds/notification-ding.mp3`; only the latter is referenced (`useNotificationHeartbeat.ts` → `/sounds/notification-ding.mp3`). The root file is a stray source copy.
- Advances already exist end to end (`EmployeeAdvance`, service, controller, API, salary page) but are not linked to payroll, have no receipt, and the advances table markup is malformed (total row nested in the empty-state `v-else`, no print action).
- `SalaryController` treats `amountPaid` as editable on update; `SalaryExpenseSynchronizer` derives `amount_due` from `total_amount - deductions`.
- Automatic deduction rows are supported (`salary_deductions.is_automatic`, `code`) but only one attendance-proration row is managed.
- Attendance statuses are `present`, `absent`, `paid-leave`, `unpaid-leave`; Fridays are skipped; monthly summaries persist paid/unpaid leave and present days.
- Paid leave allowance is a single global setting (`settings.paid_leave_days_per_year`); `employees.paid_leave_days` already exists as a nullable per-employee override.
- Audits use `AuditLog` + `Auditable`; audit notifications are only delivered to super admins and only for the few models that pass `$notify`; the navbar badge polls `pending-count` every 60 s. No per-user audit flag or heartbeat exists.
- Printing: `printDocument()` is the only print entry point, `printReceipt()`/`receipt_template.vue` implement 2-up copies with A5/A4 page rules.
- The service contract dialog shows a `VProgressLinear` while preparing and a spinner on Save; form loaders are a broad convention problem.

### 4. Graph and impact review

- Tools used: `graphify-out/` is present but no graph query CLI was needed; review used repository search (`code_searcher`, `rg`), direct file reads, and manual call/data-flow tracing (documented below).
- Call/data flows traced: Employee → EmployeeController/Resource → staff_list; EmployeeAdvance → EmployeeAdvanceService → Expense; Salary → SalaryController → SalaryExpenseSynchronizer → SalaryPayment/Expense; Attendance → MonthlyAttendanceProcessor → MonthlyAttendanceSummary → SalaryController::attendanceCounts; PaidTimeOff → PaidTimeOffController → Attendance; AuditLog ← Auditable → Notification; NavBarAuditLogs → `/audit-logs/pending-count`.
- Downstream effects: salary net math and expense sync, payslip props, salary API contract types, paidslip print styles, timesheet status rendering, leave allowance UI, report sections (`ReportService` salary columns), audit badge polling.
- Limitations: no automated coverage exists for the advance receipt or the audit heartbeat; browser print output is validated structurally and by typecheck, not by a physical printer.

### 5. Clarifications, decisions, and assumptions

- **Assumption (advance recovery):** "salary payments need to account for advances" means advances are recovered from the salary as an automatic deduction. Each advance is recovered at most once (full amount) by the first salary whose period covers it; if it cannot fit without making net pay negative it stays outstanding. Deleting the salary releases the advances.
- **Assumption (off-day approval):** creating a holiday with `approve_off_days` records the creator as approver and activates it immediately; without it the record is stored as `pending` and requires a later approval. Only `approved` records touch attendance.
- **Assumption (immutability):** the *base* salary amount is immutable after creation; the recorded payments remain the mutable part of payroll.
- **Assumption (PWA note):** "update PWA settings to contain the new setup" means the new notification sound asset is part of the precache/offline contract (plus docs), not a new settings screen.
- **Assumption (per-employee allowance):** employee override → office base → global setting. An office base is added to `offices`.

### 6. Implementation plan

- [x] Migration/model: `off_days` table + `OffDay`; `employees.guarantor_name`; `offices.paid_leave_days_per_year`; `attendances.off_day_id`; `salaries.off_days` + `salaries.advance_recovery`; `monthly_attendance_summaries.off_days`; `employee_advances.salary_id`; `users.unread_audit_logs` + `users.audit_logs_seen_at`.
- [x] Models: `OffDay`, `Employee`/`Office`/`Attendance`/`Salary`/`EmployeeAdvance`/`User` fillable+casts+relations; `AuditRegistry` entry.
- [x] `OffDayService` + `OffDayController`: range CRUD, approve/reject, attendance application/reversal, permissions.
- [x] `MonthlyAttendanceProcessor` and `SalaryController::attendanceCounts`: off-day materialization, present counting, `offDays` breakdown.
- [x] `SalaryAdvanceService`: outstanding query, apply/release recovery, automatic deduction row (`advance_recovery`), totals sync.
- [x] `SalaryController`: immutable base amount on update; advance apply/release; payslip snapshot fields.
- [x] `SalaryResource`/`PaidTimeOffResource`/`EmployeeResource`/`OfficeResource`: new fields.
- [x] Audit real-time: flag write in `AuditLog`, audience query, `AuditLogController::heartbeat`, route ordering, API contract + composable + navbar.
- [x] Permissions + role grants: `view_off_days`, `manage_off_days`, `approve_off_days`; payroll grants for `finance_officer`.
- [x] Frontend: off-days page + route + HR navigation; advance receipt template/dialog; payslip redesign with off days/advances; salary edit read-only base; staff leave dialog parity; guarantor field; loader removal; PWA precache.
- [x] Translations en/fa/ps for all new labels.
- [x] Docs: AGENTS sections (off days, payroll advances, audit heartbeat, sound asset) and this record.
- [x] Tests: off-day lifecycle/payroll effect, advance recovery, salary immutability, employee guarantor, audit heartbeat.

### 7. Validation plan and results

- [x] `php artisan test --filter=HrPayrollOffDaysTest` — 7 tests / 37 assertions, PASS.
- [x] Focused regression `--filter='Employee|Salary|Attendance|Audit|Leave|Paid|Office|Timesheet|Payroll|HrPayroll'` — 98 tests / 801 assertions, PASS.
- [x] `npm run typecheck` — PASS (no output).
- [x] `npm run build:icons` — PASS, 292 tabler icons bundled, no unavailable-icon warnings.
- [x] `npm run build` — PASS, emitted `public/sw.js` with the chime in the precache list (243 entries).
- [x] `DB_CONNECTION=sqlite php artisan migrate:fresh` — PASS; this exposed and fixed an ordering bug (see risks).
- Results: all of the above green.
- Checks not run: MySQL run of the suite (no MySQL service/credentials in this environment; the suite was run on SQLite in-memory), physical/PDF print verification of the A5 advance receipt and the redesigned payslip, and live browser validation of the advances tab (no database-backed dev server was available).

### 8. Risks and mitigations

- Salary net math drift — keep `SalaryExpenseSynchronizer` as the single writer of `amount_due`; always re-sync after advance apply/release.
- Double-expensing an advance — recovery is a deduction only; the advance's own expense remains the cash record.
- Off-day rows conflicting with worked attendance — never overwrite rows that have punches; only mark non-worked rows.
- Payroll periods and advance dates — only advances dated on/before the period end are eligible; a released salary restores them.
- Audit flag fan-out cost — set flags in one bulk `UPDATE` scoped to active permission holders; heartbeat returns a bounded batch.
- Fresh-install migration order — data migrations create audited models (roles/permissions/announcements) before `users.unread_audit_logs` exists. `AuditLog::created` therefore memoizes a `Schema::hasColumn` guard and skips the flag write until the column exists. `migrate:fresh` was verified on SQLite after this fix.

### 9. Progress log

- 2026-10-18 — DISCOVERY: reviewed HR payroll, advances, attendance/leave, payslip printing, permissions and audit subsystems; confirmed the sound asset duplication and the advances-table markup defect.
- 2026-10-18 — PLAN: assumptions recorded; implementation started.
- 2026-10-18 — PROGRESS: migrations (`off_days`, HR/payroll columns, audit heartbeat flags), `OffDay`/`OffDayService`/`OffDayController`, `SalaryAdvanceService`, salary immutability, attendance materialization and monthly summary off days, per-employee allowance resolution, audit heartbeat endpoint + composable + navbar badge, permissions/role grants, API contracts, off-days page/route/navigation, advance receipt, payslip redesign, salary-page advances fix, staff leave dialog parity and guarantor name are in place.
- 2026-10-18 — PROGRESS: removed the stray repository-root `notification-ding.mp3`; the only copy is `public/sounds/notification-ding.mp3`, served at `/sounds/notification-ding.mp3` and precached by the service worker; documented the production path in AGENTS and the PWA record.
- 2026-10-18 — PROGRESS: removed form loader animations (service contract dialog progress bar and save/cancel spinners; salary form bonus spinners). Advance preview now opens without auto-printing.
- 2026-10-18 — FIX: `SalaryController::store` now refreshes the salary after advance recovery so the payable balance and the response carry the recovered amount. `AuditLog::created` skips the flag write until the column exists, so fresh installs migrate cleanly.
- 2026-10-18 — PROGRESS: added ps-AF translations for the payslip template, leave days and audit navigation that were missing.
- 2026-10-18 — VALIDATION: `HrPayrollOffDaysTest` 7/7, focused regression 98/98, `npm run typecheck`, `npm run build:icons`, `npm run build` and `migrate:fresh` all green.

### 10. Completion summary

- Delivered: guarantor name end to end; fixed advances ledger with a printable two-copy A5 receipt; immutable salary base; automatic advance recovery that survives edits/deletes; redesigned payslip with off-day and advance lines, signatures and A5 print; monthly payroll filter/summary print for finance; a dedicated off-day ledger with approval permission that feeds attendance, payroll and payslips; staff leave dialog parity with the self-service range request; per-employee paid-leave allowance (office base, global fallback); a real-time audit heartbeat with badge/notice; PWA precache of the notification chime; loader-free forms; en/fa/ps translations and AGENTS documentation.
- Acceptance criteria satisfied/not satisfied: all criteria satisfied. Physical print quality is the only criterion verified structurally (A5 page rules, two-copy layout, print namespacing) rather than on paper.
- Tests/checks passed: `php artisan test --filter=HrPayrollOffDaysTest` (7), the focused HR/finance/audit regression filter (98), `npm run typecheck`, `npm run build:icons`, `npm run build`, and `migrate:fresh` on SQLite.
- Remaining limitations or follow-up work: re-run the suite against MySQL in an environment that provides it; verify the advance receipt and payslip on a real A5 printer/PDF; confirm the advances tab visually in a browser once a database-backed dev server is available. The root `notification-ding.mp3` deletion is staged in git (`git rm`).
