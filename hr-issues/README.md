# HR module logic review

- Review date: 2026-10-31
- Reviewer role: senior system architect / business-logic reviewer (read-only code + live UI)
- Environment: local Laravel app at `http://127.0.0.1:8000`, seeded demo database, signed in as `admin@example.test` (super_admin)
- Scope: HR global navigation group — Staff list, Departments, Employment contracts, Timesheet, Off Day Requests, Company Off Days, Payrolls, Bonus list, Positions, Announcements — plus the shared task-assignment surface for department managers and the commission ledger.
- Method: live UI interaction with Playwright (create department, failed saves, payroll generation, payment validation, bonus grant, timesheet logging) plus read-only tracing of the controllers/services that implement the matching rule.

## Verdict

The HR module is functional on the nominal path, but it fails the "professional, specific feedback" bar in several places, and two flows are objectively wrong: **granting a bonus writes a zero amount**, and **deleting a department with members returns a generic duplicated error instead of the business reason**. The commission trigger also contradicts the stated requirement: it fires on the applicant case's `completed` stage, not on a completed contract.

## Issues

| ID | Severity | Title |
| --- | --- | --- |
| [HR-001](issues/HR-001-bonus-grant-zero-amount.md) | Critical | Bonus grant confirmation shows 0.00 and grants 0, with no success or error feedback |
| [HR-002](issues/HR-002-department-delete-generic-duplicate-toast.md) | High | Deleting a non-empty department shows "Unable to save your changes." twice instead of the business reason |
| [HR-003](issues/HR-003-duplicate-toast-department-manager.md) | Medium | Every ineligible-manager rejection produces two identical error toasts |
| [HR-004](issues/HR-004-manager-dropdown-ineligible-employees.md) | Medium | Department manager picker offers employees who can never be managers |
| [HR-005](issues/HR-005-payroll-generate-toast-empty-counts.md) | Medium | "Generate payslips" success toast prints empty counts |
| [HR-006](issues/HR-006-commission-trigger-contract-vs-case.md) | High (needs product confirmation) | Commission accrual is keyed to case completion, not contract completion as required |
| [HR-007](issues/HR-007-timesheet-no-toast-feedback.md) | Medium | Timesheet attendance actions give no toast; errors are page-level text only |
| [HR-008](issues/HR-008-staff-form-stale-inline-errors.md) | Low | Staff form inline validation errors remain after the field is corrected; salary silently rewritten on position change |
| [HR-009](issues/HR-009-leave-and-offday-validation-coverage.md) | Needs verification | Leave/off-day edge cases not exercised end to end; risks identified from source |

## Behaviour documented (not defects)

### Role hierarchy / seniority

- Seniority is role-based through `roles.level` (`app/Support/RoleHierarchy.php`): `super_admin` 500, `admin` 400, `manager` 300, `team_lead` 260, `senior_counselor` 240, `finance_officer` 220, `counselor` 200, `case_officer` 160, `staff` 150, `document_officer` 100, `viewer` 50, `applicant` 0.
- There is no separate "senior employee" flag on an employee. Being senior is a consequence of the linked user's role. An employee without a user account is treated as `staff` (150) for supervision checks (`RoleHierarchy::canSupervise`).
- A department manager must be linked to a user that has `view_subordinate_records`. In the seeded roles that is `manager`, `team_lead`, `admin` and `super_admin`; `senior_counselor` has only the read scope, `counselor` and `staff` have neither.
- Supervision requires the supervisor to be **strictly** higher-ranked than the subordinate. Equal rank, self-supervision and cycles are rejected.
- Department membership is guarded by `department_manager_rank_invalid`: an employee cannot be assigned to a department whose current manager does not outrank them.
- `EmployeeScope` unions the supervisor chain and all members of departments the user manages. A department manager therefore sees and may assign work to their own department (including unlinked employees are excluded because assignment targets are users).

### Department manager task assignment

- `TaskController::assertAssigneesAssignable` allows unrestricted assignment only for holders of `assign_task_members`. A manager without that grant must be a department manager, and every assignee must be in `managedDepartmentUserIds(user) ∪ {self}`; anything else raises `business.task_assignee_outside_department`.
- The employee-scoped picker is expected to be limited to those users. The UI does not visibly label this restriction (see limitations).

## Coverage and limitations

Exercised through the UI:

- Departments: create (valid manager), empty-name validation, ineligible-manager rejection, delete non-empty rejection.
- Staff list: open create dialog, empty-form validation, create an employee (position, department, salary), automatic salary fill from position.
- Payrolls: generate missing payslips for the displayed month, open payment dialog, reject an overpayment, inspect register totals.
- Bonus list: open grant confirmation, confirm a grant, inspect the persisted bonus.
- Timesheet: load page, select employee, attempt a Present log and observe feedback.
- Navigation: HR group renders all items for a super admin.

Not fully exercised (time/state constraints): employment contracts, positions CRUD, announcements, company off-day approval/materialisation, leave request submission/approval, salary deductions and advances CRUD, commission settlement lifecycle, and a second login as a department manager or a non-HR employee. The corresponding code paths were read and are noted where they produced findings; they remain candidates for a follow-up session.

## Evidence

- `evidence/payroll-overpayment-error.png` — the specific overpayment toast observed from the payroll payment dialog.
- Additional live observations are quoted inline in each issue (toast text, dialog text, persisted rows). Toasts disappear quickly, so they are transcribed rather than screenshotted.
