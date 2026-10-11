# Employee active/inactive status — plan and progress

**Date opened:** 2026-10-07
**Scope:** backend-focused; frontend touched only where the new state must be visible/manageable.

## 1. Goal

1. Every employee record carries an explicit `active` / `inactive` state.
2. **GET requests never return inactive employees** — employee directories, option
   pickers, and staff lists are filtered to active employees by default.
3. Inactive employees remain fully intact (profile, history, audit trail) and can be
   viewed and reactivated by an authorized user through the staff management page.

## 2. Current state (survey)

`employees` table (`0001_04_01_000004_create_hr_tables.php`) has no status column; the
model has `SoftDeletes` only (deletion is a separate, destructive concept).

GET endpoints that return employee data:

| Endpoint | Method | Returns | Decision |
| --- | --- | --- | --- |
| `EmployeeController::index` (`GET /api/employees`) | GET | full employee directory | active only by default; explicit `status` param for management |
| `EmployeeController::marketerOptions` | GET | marketer pick list | active only |
| `AttendanceController::unmarkedStaff` | GET | staff to mark today | active only |
| `ReportService::options` (`employees` filter list) | GET | report filter options | active only |
| `EducationalServiceController::options` | GET | employee picker | active only |
| `PendingTransactionController::options` | GET | employee picker | active only |
| `EducationalDashboardController::staff()` | GET | staff widget (top 50) | active only |
| `EmployeeController::show`, `accountOptions` | GET | single record / user accounts | unchanged (see boundaries) |
| `WorthyEmployeeController`, `SalaryController`, `AttendanceController::index`, contracts, CRM, audits | GET | historical records that *reference* an employee | unchanged (nested/historical data) |

Write-path guards that reuse `Employee::selectable()` (salary, attendance, leave, bonus,
asset allocation stores) are **not** changed in this pass — see boundary B3.

## 3. Design decisions

### D1 — Data model

- New string column `employees.status`, length 20, DB default `'active'`, indexed.
- Values mirror the existing `positions.status` / `services.status` convention
  (`active` / `inactive`) rather than a boolean, so the UI and API follow the same
  vocabulary as the rest of the product.
- Existing rows are backfilled to `active` by the column default.
- Soft deletes keep their independent meaning: delete = remove the record,
  `inactive` = no longer part of the active workforce.

### D2 — API contract

- `EmployeeResource` (and any other employee payload) exposes `status`.
- `GET /api/employees` accepts an optional, validated `status` query parameter:
  - `active` (default) — only active employees, i.e. what every existing consumer
    gets with no change on their side.
  - `inactive` — only inactive employees (management/audit views).
  - `all` — both (management view).
  - any other value → `422`.
- All other employee-listing GET endpoints hard-filter to `active`; they have no
  opt-in parameter because they are selectors, not management surfaces.
- `PUT /api/employees/{id}` and `POST /api/employees` accept
  `status ∈ {active, inactive}` (`sometimes`), so the state is managed through the
  normal, permission-checked (`edit_employees` / `create_employees`) endpoints —
  no new route is introduced.

### D3 — Boundaries (deliberately unchanged, with reasons)

- **B1 — `show` stays unfiltered.** Deactivating and reactivating requires reading the
  record; blocking `GET /employees/{id}` for inactive staff would make the state a dead end.
- **B2 — Historical/nested payloads keep inactive employees.** Salaries, attendance
  history, leaves, bonuses, contracts, invoice commissions, CRM ownership, and audit
  rows are facts about the past; hiding the employee there would corrupt documents
  and reports.
- **B3 — `Employee::selectable()` write guards are unchanged.** They validate *writes*
  (creating a salary/attendance/leave/bonus/allocation). Tightening them would break
  legitimate flows (e.g. marking the final attendance of the month after deactivation)
  and is a product decision outside "only send active employees in GET requests".
  Revisit separately if desired.
- **B4 — `accountOptions` is a users endpoint.** It lists login accounts
  (`users.acc_status`), not employees; the nested `employee` block is an ownership
  hint used for linking. Unchanged.
- **B5 — Dashboard headcount KPIs (`total_employees`, `peopleOverview.employees`,
  `employee_count`) are aggregate counts, not employee payloads**, so they keep
  counting non-deleted employees. The one exception is the *unmarked attendance*
  action count, which must agree with the (now active-only) `unmarkedStaff` list it
  links to.
- **B6 — Announcement recipients and background jobs** (`AnnouncementService`,
  `MonthlyAttendanceProcessor`) are write/processing paths, not GET responses.
  Unchanged.


## 4. Implementation plan

### Backend

- [ ] `database/migrations/2026_10_07_000001_add_status_to_employees_table.php` — add indexed `status` column, default `active`.
- [ ] `app/Models/Employee.php` — `STATUS_ACTIVE` / `STATUS_INACTIVE`, `status` in `$fillable`, `scopeActive()`.
- [ ] `database/factories/EmployeeFactory.php` — default `status => active`.
- [ ] `app/Http/Resources/EmployeeResource.php` — expose `status`.
- [ ] `app/Http/Controllers/EmployeeController.php` — `index` status param (validated, default `active`), `marketerOptions` active-only, `store`/`update` validate `status`.
- [ ] `app/Http/Controllers/AttendanceController.php` — `unmarkedStaff` active-only.
- [ ] `app/Services/ReportService.php` — report filter options active-only.
- [ ] `app/Http/Controllers/EducationalServiceController.php` — options active-only.
- [ ] `app/Http/Controllers/PendingTransactionController.php` — options active-only.
- [ ] `app/Http/Controllers/EducationalDashboardController.php` — staff widget active-only.
- [ ] `app/Http/Controllers/DashboardController.php` — unmarked-attendance count active-only.

### Frontend

- [ ] `resources/ts/api/employee.ts` — `status` on `Employee`, `CreateEmployeeData`,
      `UpdateEmployeeData`, and `EmployeeParams.status`.
- [ ] `resources/ts/pages/staff_list.vue`:
  - fetch the directory with an explicit `status` filter (default `active`, server-side),
  - Status column with an Active/Inactive chip,
  - status select in the edit dialog,
  - row-level activate/deactivate toggle,
  - status filter option in the existing filter menu (options reuse
    `common.statuses.all/active/inactive`, label `common.fields.status`).

### Tests

- [ ] `tests/Feature/EmployeeActiveStatusTest.php` — default active-only listing,
  `status=inactive` / `status=all` opt-in, invalid value → 422, resource exposes
  `status`, `PUT` toggle round-trip, `marketer-options` and `unmarked-staff`
  exclude inactive employees.

### Docs

- [x] This file.

## 5. Verification plan

1. `vendor/bin/phpunit --filter '<employee-related tests>'` against a local MySQL test
   database (started in user space for this session: `mysqld --datadir=/tmp/ws-mysql`,
   databases `laravel_test` / `testing`).
2. `npm run typecheck` (`vue-tsc --noEmit`).
3. `npx eslint` on the touched frontend files.
4. PHP syntax pass on touched backend files.


## 6. Progress log

### 2026-10-07 — survey and plan

- Catalogued every backend GET surface that returns employee data (table in §2) and
  every frontend consumer of `EmployeeApi` (`staff_list`, `staff_salary`, `timesheet`,
  `bonuslist`, `commissions`, `asset`, `leads`, `all_customers`, `visitor`,
  `applicants*`, `ContractForm`, `departments`, `educational-*`).
- Confirmed the convention for status fields (`positions.status`, `services.status`)
  and the existing i18n keys `common.fields.status` + `common.statuses.*` in all three
  locales, so no new translation keys are required.
- Established the test environment: the repository's MySQL test database was not
  running; a user-space `mysqld` was started and `migrate:fresh` completed cleanly,
  unblocking feature tests (previously reported as blocked in
  `docs/CONTRACT_OPTIONS_VARIANTS_PROGRESS.md`).
- Plan approved by the requesting user as written above; implementation follows the
  file list in §4 exactly.

### 2026-10-07 — implementation, verification, and results

- [x] Migrated: `2026_10_07_000001_add_status_to_employees_table.php` applied to
      a local MySQL database (`mysqld --datadir=/tmp/ws-mysql`, user-space,
      databases `laravel_test` / `testing`, root/empty-password, started because
      the default MySQL service is not running in this environment).
- [x] Model: `STATUS_ACTIVE` / `STATUS_INACTIVE` constants, `status` in
      `$fillable`, `scopeActive()` added.
- [x] Factory: default `status => Employee::STATUS_ACTIVE`.
- [x] `EmployeeResource` exposes `status`.
- [x] Backend GET filtering applied per §3/D2:
      - `EmployeeController::index` — `status` query param (`active` default,
        validated `active|inactive|all`, otherwise 422).
      - `EmployeeController::marketerOptions` (active-only) and `store`/`update`
        (validate `status`).
      - `AttendanceController::unmarkedStaff`, `ReportService::options`,
        `EducationalServiceController::options`, `PendingTransactionController::options`,
        `EducationalDashboardController::staff()` — active-only.
- [x] Dashboard "unmarked employees" KPI switched to active-only to match the
      active-only `unmarked-staff` list (boundary B5).
- [x] Frontend:
      - `resources/ts/api/employee.ts` — `status` on `Employee`,
        `EmployeeParams.status` (`active|inactive|all` with backend note),
        `status?` on `CreateEmployeeData`/`UpdateEmployeeData`.
      - `staff_list.vue` — explicit `status` fetch (server-side, default `active`),
        Status chip column, status select in the edit dialog, activate/deactivate
        row toggle with `vue-toastification` feedback, status option in the filter
        menu (`common.statuses.all/active/inactive`, label `common.fields.status`),
        client-side `matchesStatus` so the displayed list always matches the
        selected filter. New employees default to active (`status` not sent →
        DB default `active`).
      - i18n: `activateStaffRecord` / `deactivateStaffRecord` / `staffStatusUpdated`
        added under `pages.settings.staffList.staffList` in `en`, `fa-AF`, `ps-AF`.
- [x] New test suite `tests/Feature/EmployeeActiveStatusTest.php` — 5 tests /
      30 assertions, all **passing** against the local MySQL test database.
- [x] Verification:
      - `vendor/bin/phpunit --filter EmployeeActiveStatusTest` → 5/5 green.
      - Backend PHP lint clean for all 9 touched files; new migration applies.
      - `npm run typecheck` — **no errors in any file I touched**
        (`resources/ts/api/employee.ts`, `resources/ts/pages/staff_list.vue`);
        the 20 remaining type errors are in pre-existing, untouched files
        (`home.vue`, `notifications.vue`, `applicant-stages.vue`, ...).
      - `npx eslint` — no new problems from my edits. The run is blocked by an
        environment-level npm/optional-dep resolution failure (`unrs-resolver`),
        which affects the whole workspace, plus pre-existing `vue/html-indent`
        warnings already present in `staff_list.vue`.
- [x] Pre-existing failures, confirmed unrelated to this change:
      - `tests/Feature/EducationalDashboardTest::test_overview_reports_pipeline_sales_staff_and_attention`
        — fixed-by-revert experiment: still fails with `->active()` removed
        (deterministic `excludePrimaryRecord()` on the single seeded employee).
      - `tests/Feature/RoleHierarchyScopeTest::test_notification_senders_...`
        `/test_senior_counselor_cannot_edit_a_subordinate_service_sale` —
        code paths untouched by this change (`NotificationController.php`,
        `EmployeeScope.php` unchanged vs HEAD; only `EducationalServiceController::options()`
        was modified, and it is not on the failing paths).

