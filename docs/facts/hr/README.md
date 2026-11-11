# HR, attendance, leave and payroll

Current behavior reviewed 2026-11-11. Source owners are the HR controllers, `app/Services/MonthlyAttendanceProcessor.php`, `app/Services/LeaveRequestService.php`, `app/Services/OffDayService.php`, `app/Services/SalaryAdvanceService.php`, and the HR routes in `routes/api.php`.

## Scope and data model

| Area | Tables / models | Key facts |
| --- | --- | --- |
| Employee directory | `employees` / `Employee` | Shared staff identity, contact, guarantor, bank, position, office, department, supervisor, employment status, join date, base salary and annual off-day allowance. `guarantor_name` is a first-class field alongside guarantor ID, phone, address and licence. |
| Attendance | `attendances` / `Attendance` | Exactly three statuses: `present`, `absent`, `off_day`. Rows carry `leave_days` and `off_day_days`, a `source`, punch times, calculated minutes and `missing_punch_state`. |
| Monthly summary | `monthly_attendance_summaries` | Employee/year/month totals for present, absent and off days plus worked, break, required, late, early and overtime minutes. |
| Leave | `leaves` / `LeaveRequest` | Employee day/half-day requests with `duration_days`, allowance-covered `off_day_days`, approval and rejection fields. |
| Company holidays | `off_days` / `OffDay` | Range-based company off days with pending/approved/rejected status and an explicit approver. |
| Payroll | `salaries`, `salary_payments`, `salary_deductions` | Payslip base, bonus, attendance-derived day counts, manual/automatic deductions, payments and recovery state. |
| Advances | `employee_advances`, `employee_advance_settlements` | Employee-level cash advance ledger with cached settled balance and payroll/cash settlement rows. |
| Bonuses | `worthy_employees` | Nominated/granted bonus records, including automatic task/case nominations that remain review-only until granted. |

## Employees and permissions

- Employee updates pass through the one-hour `edit-window` middleware because the approved window is mounted only on `PUT/PATCH /employees/{id}`.
- `edit_own_employee_profile` allows an employee to change only their own personal, contact, guarantor and bank fields. Pay, position, office, department, supervisor, status, join date, off-day allowance, commissions and account linkage still require `edit_employees`.
- `EmployeeController::updateOwnProfile` rejects any other submitted field instead of silently ignoring it.
- Employee records are soft-deleted. `Employee::selectable()` and the active status filter keep terminated/inactive staff out of new payroll, attendance materialization and assignment lists.
- The staff form and API expose the guarantor name, ID, phone, address and licence. Keep all of them in migration, model, validation, resource, factory and UI.
- Relevant permissions: `view_employees`, `create_employees`, `edit_employees`, `delete_employees`, `edit_own_employee_profile`.

## Attendance and the workday

### Statuses and fractions

- `present`: the employee worked or has a worked row. A half-day leave can still be `present` with `leave_days=0.5`; the worked part stays payable.
- `absent`: the employee did not work, or the uncovered part of a leave/half-day is an absence.
- `off_day`: the day is covered by an approved company holiday or the employee's allowance.
- `leave_days` is the approved absence fraction (0, 0.5, or 1). `off_day_days` is the part covered by an allowance or company holiday. The uncovered remainder is an absence.
- `source` is `manual`, `self_service`, `holiday`, `leave`, or `missing_policy`.
- Friday is the weekly day off and is never materialized as an attendance row.

### Self-service and manual entry

- `/workday/status`, `/workday/start`, `/workday/end`, `/workday/geofence` are under the `AttendanceBrowser` guard.
- `manage_own_workday` starts/observes a workday; `submit_own_daily_report` ends it. Both start and end are throttled.
- The geofence endpoint is observe-only; attendance policy is evaluated by the attendance services, not by the browser.
- The Timesheet page uses `GET /attendances` (`view_attendance`) and supports manual create (`record_attendance`), update, checkout correction, delete (`delete_attendance`), unmarked-staff lookup (`view_unmarked_attendance_staff`), company off-day lookup, and daily reports.
- `GET /my-work-history` and `GET /my-work-history/{attendance}/daily-report` expose a user's own records without an HR view grant.
- `attendance:finalize` recalculates changed rows and materializes missing rows; see [Schedules](../schedules/README.md).

### Missing attendance policy

- `settings.missing_attendance_status` is `present`, `absent`, or `off_day` (default `absent`). It is edited in Settings → Operations with `edit_settings`.
- It applies only to elapsed non-Friday workdays with no row. It never marks today or future days.
- An approved company holiday wins over the policy; an approved leave request also wins; recorded work is never overwritten.
- `MonthlyAttendanceProcessor` is the writer; `attendance:finalize` is the scheduled entry point.

## Leave and company off days

Company holidays and employee leave are separate ledgers.

### Company off days: `/off-days`

- `GET /off-days` requires `view_off_days`; create/update/delete require `manage_off_days`.
- Announcement may create a pending row. `approve_off_days` is required to approve/reject and to remove an already-approved holiday.
- `OffDayService` is the only writer of holiday attendance effects. An approved holiday materializes non-worked days as `attendance.status=off_day`, `off_day_id`, `off_day_days=1`, `source=holiday`.
- Worked rows and leave rows are never overwritten. Reverting or deleting the holiday deletes or restores the synthesized rows.
- A holder of `approve_off_days` may announce an already-approved holiday in one step.

### Employee leave: `/leave-requests`

- Employee requests are `leaves` rows written through `LeaveRequestController` and `LeaveRequestService`.
- Staff routes: `GET /leave-requests` (`view_leave_requests`), `GET/POST /my-leave-requests` (`request_own_leave`), `PATCH /leave-requests/grant` and `/reject` (`grant_leave_requests`), employee-scoped create/edit/delete (`create_leave_requests`, `edit_leave_requests`, `delete_leave_requests`).
- A request accepts a date range. Fridays are skipped, not rejected. The range is stored as one row per date.
- Approval splits each date by the employee's `employees.off_day_allowance_days`: the covered portion becomes `off_day`; the excess becomes `absent`.
- `grant_leave_requests` may force an excess day to remain `off_day` only with `force_off_day` and a written `approval_note`.
- `settings.off_day_allowance_days_per_year` is a creation-time catalogue value copied into a new employee's own allowance. It is not a runtime fallback. Only the employee's allowance governs approvals and usage.
- A worked half-day stays `present` with `leave_days`/`off_day_days`; a full-day request that conflicts with recorded work is rejected.
- A company holiday already owning the date is not overridden by a leave request.
- Removing a granted request releases the attendance claim and lets the missing policy rebuild the day; an existing company holiday remains.

## Payroll

### Workspace and endpoints

- `/payrolls` is the payroll workspace. `/staff-salary` redirects to `/payrolls`.
- `GET /salaries/register` returns a month's employee-driven register, including employees without a payslip, and whole-month KPI totals (net, paid, outstanding, generated, missing).
- `POST /salaries/generate` creates missing payslips for active employees with a base salary and a join date on/before month end. Existing payslips, zero-base employees and not-yet-joined employees are skipped; the response reports created/skipped/errors.
- `GET /salaries/attendance-breakdown` returns attendance-derived worked/total/off/absent days.
- `GET /salaries/granted-bonuses` lists reviewable granted bonuses for an employee/month.
- Salary CRUD uses `view_salaries`, `create_salaries`, `edit_salaries`, `delete_salaries`.
- Deductions use `view_salary_deductions`, `create_salary_deductions`, `edit_salary_deductions`, `delete_salary_deductions`.
- Payments use `create_salaries` for creation and `delete_salaries` for deletion.
- Advance routes use the salary permissions; see below.
- `view_bonuses` covers salary bonus lookup; granting is separate.

### Salary invariants

- The base `amount_paid` is an agreed employment snapshot and is immutable after creation. `SalaryController::update` rejects a changed base amount, employee, period or currency.
- `total_amount = amount_paid + bonus`. Bonus amounts are attached when the salary/payment is created and are not duplicated by partial-payment edits.
- Worked days, total days, off days and absent days are attendance-derived and read-only. The API ignores/refuses client-supplied day overrides.
- The automatic absence deduction is `amount_paid × absent_days ÷ total_days`. Present days and off days are never deducted. Manual deductions remain additive.
- Deductions cannot exceed the payable amount. `SalaryController` writes the attendance proration row under the `attendance_proration` code so it cannot collide with `advance_recovery`.
- Deleting a salary deletes its payroll expenses/payments as appropriate. Salary-linked expenses are managed by payroll and cannot be edited or deleted through the general expense endpoints.
- The payslip snapshots `worked_days`, `total_days`, `absent_days`, `off_days` and `advance_recovery`, and itemizes recovered advances.
- Payslips print on A5 through the namespaced print workflow. Advance receipts print employee and company copies on one A5 sheet.

## Salary advances

- An advance is a cash ledger, not a payroll deduction record. Its `status` is derived from `settled_amount`: `outstanding`, `partial`, or `settled`. It closes itself at zero.
- `SalaryAdvanceService::apply()` recovers open advances from a payroll period. It creates one automatic `advance_recovery` salary deduction and one `payroll` settlement per advance/payslip.
- Recovery is capped at `total_amount − other deductions`, so net pay never goes negative. An advance larger than the period's payable balance is recovered in instalments across periods.
- A recalculation (salary edit, new advance, toggle) first releases that payslip's allocations, so a balance is never counted twice.
- `PATCH /salaries/{salary}/advance-recovery` opts a payslip in/out while it is unpaid. Once a payment exists, recovery is locked.
- Deleting the salary releases its payroll allocations. The advance's original expense remains the cash record; recovery is never a second expense.
- `POST /employee-advances/{advance}/settlements` records a `cash` repayment. It shrinks the linked payroll expense, because the expense represents the net cash the company has out.
- An advance can only be recorded in a month whose payslip has not paid out, cannot be edited below what was already paid back, and cannot be deleted while a paid payslip withheld it.
- The advances ledger and receipts are part of `/payrolls`; `GET /employee-advances` uses `view_salaries`, create/settle uses `create_salaries`, edit uses `edit_salaries`, delete uses `delete_salaries`.

## Bonuses and payroll

- `worthy-employees` nominations use `view_bonuses` to read, `nominate_bonus_candidates` to create, `edit_bonus_nominations`/`grant_bonuses` to edit, and `delete_bonus_nominations` to delete.
- Automatic task/case nominations are suggestions until a holder of `grant_bonuses` grants them. They do not pay out by themselves.
- A granted bonus can be selected into a salary for the matching month. The salary stores the bonus snapshot and conversion.
- Payroll can include a granted bonus without changing the base salary amount.

## UI surfaces

| Page | Route | Purpose |
| --- | --- | --- |
| Timesheet | `/timesheet` | Attendance register, manual entry, unmarked staff, daily reports, geofence/status. |
| Payrolls | `/payrolls` | Monthly KPIs, generate missing payslips, payslip/deduction/payment dialogs, preview/print, advance ledger and receipts. |
| Leave requests | `/leave-requests` | Staff leave ledger and approvals; `/my-leave-requests` for self-service. |
| Off days | `/off-days` | Company holiday announcement and approval. |
| Bonus list | `/bonuslist` | Bonus nominations and grants. |
| Staff | `/staff_list` (staff directory) | Employee profiles, guarantor/bank/position/office/department data. |

## Source map

- `routes/api.php` (workday, leave, off-day, employee, salary, advance and bonus routes)
- `app/Http/Controllers/WorkdayController.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/LeaveRequestController.php`
- `app/Http/Controllers/OffDayController.php`
- `app/Http/Controllers/EmployeeController.php`
- `app/Http/Controllers/SalaryController.php`
- `app/Http/Controllers/SalaryPaymentController.php`
- `app/Http/Controllers/SalaryDeductionController.php`
- `app/Http/Controllers/EmployeeAdvanceController.php`
- `app/Http/Controllers/WorthyEmployeeController.php`
- `app/Services/MonthlyAttendanceProcessor.php`
- `app/Services/AttendanceCalculationService.php`
- `app/Services/LeaveRequestService.php`
- `app/Services/OffDayService.php`
- `app/Services/SalaryAdvanceService.php`
- `app/Services/SalaryExpenseSynchronizer.php`
- `app/Models/Employee.php`, `Attendance.php`, `MonthlyAttendanceSummary.php`, `LeaveRequest.php`, `OffDay.php`, `Salary.php`, `EmployeeAdvance.php`
- `resources/ts/pages/timesheet.vue`, `payrolls.vue`, `leave_requests.vue`, `off_days.vue`
