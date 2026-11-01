# HR-002 — Deleting a department with members returns a generic error, twice

- Severity: High
- Area: Departments (`/setup/departments`) → Delete
- Environment: 2026-10-31, super_admin

## Requirement

Deleting a department that still has employees must be refused with the domain reason, so the user knows to move the employees first. The delete confirmation already states `Only empty departments can be deleted. Move its employees first.`; the server has a dedicated message key `business.department_has_employees`. One action must produce one toast.

## Steps to reproduce

1. Create a department and an employee in it (or use the seeded state after HR-004 reproduction: `Counseling` with `Test Counselor`).
2. On the Departments table, click the trash icon for `Counseling`.
3. Confirm **Delete** in the dialog.
4. Observe the toast area.

## Expected

- One error toast explaining that the department has employees and cannot be removed (the same wording as the dialog guidance).
- The dialog may stay open so the user can correct the situation.

## Actual

- Two identical generic toasts: `Unable to save your changes. ×` and `Unable to save your changes. ×`.
- No mention of employees, no mention of the department, no actionable next step.
- The confirmation dialog remains open with only `Cancel`/`Delete`, inviting the user to retry the same failing action.

## Root cause

- `resources/ts/pages/setup/departments.vue` `remove()` falls back to `translate('common.ui.aDepartmentWithMembersCannotBeRemoved')`, but the resolved message that reaches the toast is the generic API fallback.
- The duplicate is consistent with both the page-level `catch` and the global API error notifier raising a toast for the same rejected request.

## Impact

Every failed delete looks like a system fault rather than a rule. Users retry, and support cannot distinguish "has employees" from a server error.

## Suggested direction

Map `business.department_has_employees` to the existing specific translation, suppress the page-level duplicate when the global notifier already handled the request, and keep the dialog open with the specific reason.

## Evidence

- Live toast capture: `"Unable to save your changes. × || Unable to save your changes. ×"`.
- Dialog copy: `Delete department / Only empty departments can be deleted. Move its employees first. / Cancel / Delete`.
- Server guard: `app/Http/Controllers/DepartmentController.php` `destroy()` aborts with `business.department_has_employees`.
