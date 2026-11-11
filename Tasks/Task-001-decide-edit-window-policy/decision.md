# Edit-Window Policy Decision

- **Status:** Approved
- **Decision ID:** `EW-001` through `EW-005`
- **Approver:** Product owner (in-session approval, 2026-11-10)
- **Chosen scope:** **Employee-only**

## Decision

The one-hour edit window applies only to employee profile updates. It does not apply to tasks, contracts, applicants, visitors, attendance, leave/off-day workflows, finance, master data, comments, or subtasks.

The existing 60-minute duration and `created_at + 60 minutes` calculation remain unchanged.

## Governed resources

- `PUT/PATCH /api/employees/{employee}` through `EmployeeController::update`.
- The same endpoint when used by an employee holding `edit_own_employee_profile` for their own profile.
- Employee fields changed through that endpoint, including personal, contact, guarantor, bank, position, office, department, supervisor, status, join date, allowance, commission and account-linkage fields subject to the existing permission split.

Not governed:

- Employee document upload/download/delete routes.
- Leave requests under `/employees/{employee}/leave-requests`.
- Task, subtask, and comment updates. The existing feature-specific comment/subtask windows remain separate and unchanged.
- Generic employment/other contracts and built-in service contracts, including update, cancel, and extension actions.
- Applicant, visitor, attendance, off-day, finance, and master-data updates.
- Any other route not listed under **Governed resources**.

## Override policy

- `override_edit_window` remains the exact override permission.
- A holder may save an expired employee update; the write succeeds and records an `edit_window_overridden` audit row with `editable_until` and `override_used_at`.
- `super_admin` continues to bypass through the existing `isSuperAdmin()` mechanism; no second bypass path is introduced.
- `admin` retains `edit_employees` and `override_edit_window`.
- `manager` becomes the seeded operational employee editor with `edit_employees` but **without** `override_edit_window`, so the window is exercisable through a real role.
- Default self-service profile editing remains unchanged. `staff` does not receive `edit_own_employee_profile` as part of this decision; enabling it is a separate product choice.
- Permission checks still run before the window check.

## Server and UI contract

- Expired writes return `422` with `business.edit_window_expired` and the existing translated message.
- A fresh employee update succeeds.
- The employee resource exposes enough state for the UI to show:
  - `editable_until`
  - `edit_window_expired`
  - `can_edit`
- The employee edit action is disabled with the deadline/reason when the window is closed, unless the user holds `override_edit_window`.
- A direct API attempt must still fail even if the UI control is bypassed.
- Other modules must not display an employee-window warning or acquire the middleware.

## Rollout and existing records

- No migration or backfill.
- Existing employee records older than 60 minutes become closed immediately on deployment.
- No existing business record is rewritten or deleted.
- Remove the application-wide middleware mount and apply the policy only to the employee update route.
- Add regression coverage for:
  - fresh employee edit success;
  - expired employee edit `422`;
  - override success plus audit row;
  - task and contract edits remaining exempt;
  - a seeded manager with `edit_employees` and no override.
- Task-004 must not enforce the employee window on contracts; its scope is extension integrity only.

## Consequences

Task-002 implements the server-side employee-only mount, the manager grant and regression tests. Task-003 implements the employee UI state. Task-004 remains limited to contract-extension integrity and treats contract edits as exempt from this policy.
