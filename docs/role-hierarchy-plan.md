# Role hierarchy implementation plan

## Authority model

Authority has two separate dimensions. A role grant answers **what** a user
may do; the employee graph answers **whose** records that grant reaches.
Neither dimension grants the other.

1. `roles.level` is the authority rank. A supervisor must have a strictly
   higher rank and `view_subordinate_records`.
2. `employees.supervisor_id` is the direct reporting line. Descendants are
   included recursively.
3. A department manager reaches active members of that department and their
   descendants. Inactive departments do not create scope.
4. `view_subordinate_records` is read-only. `manage_subordinate_records` is
   required before changing a subordinate-owned record.
5. Global access is explicit (`view_all_*` / `manage_all_*`); it must never be
   inferred from a role name in a controller.

## Delivery checklist

### Completed

- [x] Persist role rank, departments, employee departments, and direct
  supervisors.
- [x] Reject self-reporting, cycles, equal-rank, and upward reporting lines.
- [x] Reject a department manager who cannot supervise its existing members.
- [x] Scope applicants, contracts, task boards/tasks, audit logs, search, and
  educational-service reads through `EmployeeScope`.
- [x] Scope educational-service writes separately through
  `manageableEmployeeIds`; a senior counselor cannot edit a junior's sale.
- [x] Scope educational-dashboard cases, sales, finance, pending approvals,
  attention items, employee metrics, and university totals as one team view.
- [x] Restrict staff-directory records and employee documents to own/team
  scope unless explicit all-employee permission is present.
- [x] Restrict manual notifications to own/team recipients; require
  `send_all_notifications` for a broadcast.
- [x] Provide department and direct-supervisor controls in the staff form,
  plus all hierarchy roles in user management. Both staff dialogs now render
  exactly one of each: the add dialog had the pair duplicated by a copy/paste
  slip and the edit dialog omitted it entirely, so the fields could not be
  changed after creation. Guarded by
  `scripts/staff-form-hierarchy-regression.mjs`.
- [x] Add English, Dari, and Pashto messages for hierarchy validation errors.

### Required acceptance tests

1. A senior counselor assigned as a counselor's supervisor sees the
   counselor's applicants, contracts, tasks, sales, and dashboard totals.
2. That senior counselor receives workflow notifications but receives `403`
   when editing the counselor's case or service sale.
3. A manager with `manage_subordinate_records` can edit direct/indirect team
   records; neither role reaches a peer or another department.
4. A sender without `send_all_notifications` cannot target a user outside
   their visible scope or omit recipients to create a broadcast.
5. A department manager cannot be a peer/junior of any department member,
   and a disabled department grants no access.
6. Re-run the same tests in a persistent worker environment to ensure scope
   caches do not cross requests.

## Operational setup

1. Run the hierarchy migration and the role/permission seeders.
2. Create departments and assign each department manager first.
3. Assign each counselor's direct supervisor in Staff Directory.
4. Verify each senior has `view_subordinate_records`; grant
   `manage_subordinate_records` only where edits are intended.
5. Use `send_all_notifications` only for organization-wide announcers.

## Guardrails for future modules

For any new employee-owned record, use `EmployeeScope::visibleEmployeeIds()`
for reads and `EmployeeScope::manageableEmployeeIds()` or
`canManageOwnedBy()` for writes. Do not test `admin`, `manager`, or another
role name directly in a controller.
