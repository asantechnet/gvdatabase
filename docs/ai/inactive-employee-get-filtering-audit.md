## Task: Inactive employees excluded from every staff list except the main staff directory

- **Task ID:** inactive-employee-get-filtering-audit
- **Created:** 2026-11-08
- **Last updated:** 2026-11-08
- **Status:** COMPLETED
- **Outcome:** The staff list page is the only GET surface that can return inactive employees; every other employee list, picker and staff report returns active employees only.

### 1. Requirements and acceptance criteria

- [x] `GET /api/employees` keeps returning the whole roster when the caller opts in (`status=all`), because the staff list page is the management surface.
- [x] Every other GET that returns an employee list or picker excludes inactive employees with no opt-in parameter.
- [x] Historical records that merely reference an employee (a payslip row, an attendance row, an audit entry) keep showing that employee regardless of status.
- [x] Covered by tests in `tests/Feature/EmployeeActiveStatusTest.php`.

### 2. Scope and non-goals

- In scope: employee-listing GET endpoints that still lack `Employee::active()`.
- Out of scope: write-path validation (`Employee::selectable()` guards), dashboard aggregate headcounts, announcement recipient resolution and other background jobs; those were deliberately separated in `docs/employee-active-status-plan.md` (boundaries B3, B5, B6) and are not staff lists returned to a page.

### 3. Project findings

Current behaviour: `employees.status` (`active` / `inactive`) exists and `Employee::scopeActive()` is applied to most pickers. `EmployeeController::index` defaults to `status=active` and accepts `inactive` / `all` for the staff list page.

Surfaces already filtered: `EmployeeController::index` (default active, opt-in for the staff page), `assignmentOptions` via `AgencyStaffAssignment`, `AttendanceController::unmarkedStaff`, `ReportService::catalog`, `EducationalDashboardController::staff`, `SalaryController::register` / `generate`, `OffDayService`, `MonthlyAttendanceProcessor`, `ContractController` employee list, `DepartmentController::eligibleManagerQuery`, `AssetController` write guard.

Gaps found (GET responses that list employees without `active()`):

| Surface | File | Notes |
| --- | --- | --- |
| Educational performance report rows | `app/Http/Controllers/EducationalReportController.php` (`performanceReport`) | Lists every non-primary employee as a report row. |
| Payroll report employee roster and company headcount | `app/Services/ReportService.php` (`employee_roster` section) | Roster query and `company_headcount` use `selectable()` only. |
| Task assignee picker | `app/Http/Controllers/TaskAssigneeController.php` | Filters `users.acc_status` but not the linked employee status, so a deactivated employee who still has an active login appears. |

Relevant paths: the four files above, plus the existing test suite.

Constraints: super-admin-linked employees are excluded from these lists by `selectable()` / `excludePrimaryRecord()`; that behaviour must not change.

### 4. Graph and impact review

- No `graphify` skill or fresh graph artifact was used; the review used repository-wide search for `Employee::` query sites and for every `EmployeeResource::collection` / employee listing, then direct reads of each hit. The stale `graphify-out/` snapshot was ignored as evidence.
- Callers examined: the Vue pages that consume each endpoint (`educational-reports.vue`, reports page, task board assignee picker).
- Downstream effects: report row counts drop by the number of inactive employees; no schema or contract change.
- Limitation: dynamic employee lists built inside Blade/print templates were not separately enumerated; none were found by search.

### 5. Clarifications, decisions, and assumptions

- Open questions: none.
- User decision: "only when GET requests are made from main staff list page the whole list of staff need to appear. other then that in all other pages and GET requests the staff list need to be filtered by active state."
- Assumption: "staff list" means an employee directory or picker, not a nested employee name inside a historical record. This follows the existing, documented boundary B2 and avoids corrupting payslips, attendance and audits.
- Assumption: dashboard KPI counts stay as total headcounts (boundary B5); they are aggregates, not employee payloads.

### 6. Implementation plan

- [x] `EducationalReportController::performanceReport` — add `->active()`.
- [x] `ReportService` payroll employee roster and `company_headcount` — add `->active()`.
- [x] `TaskAssigneeController` — exclude accounts whose linked employee is inactive, while still allowing accounts with no employee record.
- [x] Extend `EmployeeActiveStatusTest` with the newly filtered surfaces.

### 7. Validation plan and results

- [x] `php artisan test --filter=EmployeeActiveStatusTest` — **6 passed, 33 assertions**.
- [x] `php artisan test --filter='PhaseEightReportsTest|EducationalReportTest|EducationalOperationalReportingTest'` — **19 passed, 168 assertions** across the four suites run together with the status suite.
- Results: all green.

### 8. Risks and mitigations

- A report that is meant to show historical joiners would lose a deactivated employee — mitigation: the roster is a current-company report; the employee record itself remains reachable through the staff page and `GET /employees/{id}`.
- Excluding users without an employee record would hide valid assignees — mitigation: the task picker keeps users that have no linked employee.

### 9. Progress log

- 2026-11-08 — DISCOVERY: enumerated every `Employee::` query site and every employee-list response; confirmed most pickers already use `active()`; identified the three gaps above.
- 2026-11-08 — PLAN: recorded and saved before implementation.

### 10. Completion summary

- Delivered: `->active()` on the educational performance report and the payroll report roster/headcount, plus a workforce-state check in the task assignee picker; a regression test for each newly filtered surface.
- Acceptance criteria satisfied: the staff directory remains the only opt-in management listing; pickers and reports return active employees only.
- Tests/checks passed: `EmployeeActiveStatusTest` (6 tests), report suites (19 tests total in the combined run).
- Remaining limitations: dashboard KPI counts and announcement recipient resolution stay unfiltered by design (boundaries B5/B6); a deactivated employee remains visible inside historical records such as payslips, attendance rows and audits.
