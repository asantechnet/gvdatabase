# HR-004 — Department manager picker offers employees who can never be managers

- Severity: Medium
- Area: Departments → Department manager
- Environment: 2026-10-31, super_admin

## Steps to reproduce

1. Open Departments → **Create Department**.
2. Open the **Department manager** combobox.
3. The list contains every employee: `Ahmad Karimi`, `Bilal Ahmadi`, `Fatima Noori`, `Omid Safi`, `Sana Rahimi`, `Zahra Aziz`.
4. Choose a counselor/staff member and save; the server rejects the choice (see HR-003).

## Expected

Only employees who can actually manage a department should be offered: an active, non-deleted employee whose linked user has `view_subordinate_records` (manager, team lead, admin, super admin). Employees without a user account cannot manage anything.

## Actual

The picker is populated from `EmployeeApi.getOptions()` with no eligibility filter. Four of six visible options are guaranteed to fail on save, and the user only learns this after filling and submitting the form.

## Constraint reference

- `DepartmentController::validated()` requires `$manager->user->hasPermission('view_subordinate_records')`.
- `RolePermissionSeeder::grants()` gives that permission only to `manager`, `team_lead`, `admin` (via the supervisory scope) and `super_admin`; `senior_counselor` deliberately receives only read scope; `counselor`, `staff`, `document_officer` receive neither.

## Impact

The eligibility rule exists but is invisible until failure. This is the main source of the HR-003 duplicate-toast error in normal use.

## Suggested direction

Return or derive `manager_eligible` from the server and filter the picker, with an empty-state explanation. Keep the server-side guard.

## Evidence

- Live combobox options listed above.
- Rule read from `app/Http/Controllers/DepartmentController.php` and `database/seeders/RolePermissionSeeder.php`.
