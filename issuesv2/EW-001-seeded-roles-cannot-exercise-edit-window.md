# EW-001 — No seeded non-admin role can edit employees; the only eligible role always overrides the window

- **Severity:** High
- **Area:** HR staff editing, role seeding, `override_edit_window`
- **Status:** Confirmed via role/permission query and live UI
- **Confidence:** High

## Summary

In the seeded role set, no non-admin role has `edit_employees`. The only role that has it — `admin` — also has `override_edit_window`, so it never experiences the one-hour restriction. `super_admin` bypasses the check unconditionally through `isSuperAdmin()`.

The same is true for self-service: only `admin` has `edit_own_employee_profile`; `staff` does not, so an ordinary employee cannot edit their own profile at all.

In practice the employee edit window is dormant for every seeded user. To test it at all, `edit_employees` had to be granted temporarily to the `manager` role.

## Permission matrix (seeded database, before the test grant)

| Role | `edit_employees` | `edit_own_employee_profile` | `override_edit_window` |
| --- | --- | --- | --- |
| `admin` | yes | yes | yes |
| `manager` | no | no | no |
| `staff` | no | no | no |
| `super_admin` | role grants do not matter; `isSuperAdmin()` bypasses | same | same |
| all other seeded roles | no | no | no |

- `override_edit_window` is declared in `database/seeders/PermissionSeeder.php` and categorized as `task_management`.
- The role grants are built in `database/seeders/RolePermissionSeeder.php`; `admin` receives the override, while `manager` and `staff` receive neither employee-edit grant.
- `app/Models/User.php::hasPermission()` returns true immediately for a super administrator, so the override permission is not even consulted for that account.

## Expected

Either:

1. A regular HR role should hold `edit_employees` **without** `override_edit_window`, so the one-hour rule is a real control that can be exercised and audited; or
2. The product should explicitly state that only `admin` edits staff records, in which case the employee-facing window is mostly ceremonial and should not be presented as a general policy.

## Actual

- A real `manager` account opened `/staff-list`; the employee was visible but there was no edit action until `edit_employees` was temporarily attached to the manager role.
- `admin` can edit staff records, but its `override_edit_window` grant means every such edit bypasses the 60-minute gate.
- Ordinary staff cannot update their own personal/contact/guarantor fields because `edit_own_employee_profile` is not granted.

## Impact

- The feature cannot be validated or used through normal seeded roles.
- Employee data is either uneditable (manager/staff) or always overridden (admin), which defeats the purpose of the window and its audit trail.
- If the intent is self-service employee profile maintenance, the missing `edit_own_employee_profile` grant blocks it entirely.
- The override permission is grouped under `task_management`, so an HR administrator looking for an employee-edit exception may not find it.

## Suggested direction (report only; no code changed)

- Grant `edit_employees` to the appropriate HR/operations role without granting the override; keep `override_edit_window` for a narrow exception role.
- If employees are expected to maintain their own contact/guarantor data, grant `edit_own_employee_profile` to `staff`.
- Move `override_edit_window` to a category that reflects its cross-module scope, or rename it to make that clear.
- Add a test that signs in as a role with `edit_employees` and no override and exercises both the open and expired paths.

## Evidence

- Role/permission query run on 2026-11-10:
  `admin edit_employees=1 own_profile=1 override=1`; `manager 0/0/0`; `staff 0/0/0`; all other seeded roles `0/0/0`.
- Playwright session: manager account; no Edit staff action before the temporary grant; `PUT /api/employees/2` → 422 after the grant exposed the form.
- Temporary grant was removed after the test; the permission matrix above reflects the original seed.
