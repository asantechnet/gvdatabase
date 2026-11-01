# HR-003 — One rejected department save produces two identical error toasts

- Severity: Medium
- Area: Departments → create/edit manager
- Environment: 2026-10-31, super_admin

## Steps to reproduce

1. Open Departments → **Create Department**.
2. Enter a name, e.g. `Counseling`.
3. Open **Department manager** and choose `Ahmad Karimi` (role `counselor`, no supervisory access).
4. Click **Save**.
5. Observe the toast stack.

## Expected

- One error toast: the employee cannot manage a department because they lack supervisory access.
- The form stays open, the manager field is marked invalid or focused, and no second identical toast appears.

## Actual

- Two identical toasts: `Choose an employee with supervisory access to manage this department. ×` twice.
- The message is specific and translated, but duplicated.

## Root cause

Both the page-level `catch` (`toast.error(apiErrorMessage(...))` in `departments.vue`) and the global Axios/API error notifier surface the same failure. The same pattern appears in HR-002, so this is systemic rather than department-specific.

## Impact

A single rule violation looks like two separate failures. Under rapid clicking or retries the stack grows and obscures other feedback.

## Suggested direction

Choose one owner for API error toasts (page or global interceptor) per request, or mark the error as already-notified before the page-level handler runs.

## Evidence

- Live capture after one Save click: `"Choose an employee with supervisory access to manage this department. × || Choose an employee with supervisory access to manage this department. ×"`.
- Server rule: `DepartmentController::validated()` aborts with `business.department_manager_not_supervisory`.
