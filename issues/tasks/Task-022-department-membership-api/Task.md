# Task Task-022 — Let the department API add and remove members safely

## Metadata
- Task ID: `Task-022`
- Title: `Let the department API add and remove members safely`
- Initial status: `Ready`
- Type: `API`
- Priority: `P1`
- Owner role: `Laravel API implementer with HR rule review`
- Estimated size: `M`
- Source plan/version: `User request 2026-11-01: manage department membership from the departments page; repository inspection 2026-11-01`
- Requirement / behavior IDs: `ORG-DEPT-003`, `ORG-DEPT-004`, `ORG-RANK-001`

## Objective
The existing department update endpoint can add and remove employees for one department while preserving each employee's other departments, enforcing ranking and manager rules, and returning named refusals instead of silent no-ops.

## Context and problem
`DepartmentController` currently manages the department name, manager, active flag and delete refusal. It has no member input and no member response, so assigning a department is only possible from the staff form, one employee at a time. The departments page shows an employee count but no way to inspect or change the set. The user's request is to add and remove employees for a department from that page.

The page and the API must also explain the hidden rules. A department manager must outrank every member they supervise, the current manager cannot be removed before a replacement is chosen, and removing a primary department must promote another membership or clear the primary value. If those rules only exist in validation, the UI cannot tell the user why a save is refused.

The task deliberately extends the existing `PUT /departments/{id}` rather than adding routes. That keeps the surface small, reuses the existing `manage_departments` permission, and avoids a second department write path.

## Scope
### Included
- Extend `GET /departments?with_members=1` to include a `members` array with id, name, position, role, status, supervisor name, `is_primary` and `is_manager`; keep the default response unchanged when the parameter is absent.
- Accept `employee_ids` on `PUT /departments/{id}` as the complete member set for that department; when omitted, leave members untouched.
- Add `DepartmentMembership` as the single sync service: compute added and removed employees, validate inactive additions and manager rank, call `Employee::syncDepartments` for each affected employee, and return attached/detached counts.
- Refuse removing the employee who is the current department manager, with a specific business code and no partial write.
- Repair the primary department when a primary membership is removed: promote the oldest remaining membership of that employee or clear the legacy primary columns when none remain.
- Return the updated department with members after a successful update so the page does not need an immediate refetch.
- Update `manager-options` and `employees_count` to use the membership relation from Task-021, not the legacy column only.
- Cover add, remove, manager refusal, rank refusal, inactive addition, empty member set, omitted member set and permission refusal.

### Excluded
- New routes, a new permission, or a separate member controller.
- The departments page dialog and all Vue copy; Task-023 owns the UI.
- Staff form multi-select and the Reports-to disclosure; Task-024 owns those.
- Rewriting existing invalid member/manager pairs. Existing data is reported, not silently repaired.
- Changing the manager eligibility rule, role hierarchy or `EmployeeScope`.
- Removing the department manager automatically when the manager row is removed from members.

## Preconditions and assumptions
- Task-021 has added the pivot, the `departments`/`employees` relations and `Employee::syncDepartments`.
- The existing route and permission remain `PUT /departments/{id}` behind `manage_departments`; `GET /departments` is behind `view_departments`.
- The department manager is not required to be a member today. The API must not force manager membership; it reports `is_manager` only when the manager also appears in the member set.
- Rank validation applies to employees being added. Existing members that already violate the rule are not rejected merely because an unrelated field is updated; this avoids blocking a name or manager fix on legacy data.
- Inactive employees may remain members and may be removed, but a newly added employee must have `status = active`. If the product wants inactive additions, stop and change the rule first.
- Assumption to validate: `employee_ids` is the complete set for this department, not an append-only patch. The page always sends the full selected set, so removals are explicit.
- The update must run in one database transaction. A validation failure returns 422 and leaves both the department and every pivot row unchanged.

## Dependencies
- `Task-021` — membership schema, relations and the canonical writer.

## Contracts and boundaries
- Request: `PUT /departments/{id}` accepts `employee_ids?: number[]`, distinct, each an existing non-deleted employee id, maximum 500 entries. Existing `name`, `manager_employee_id` and `is_active` fields keep their current rules.
- Response: a department includes `id`, `name`, `manager_employee_id`, `manager_name`, `employees_count`, `is_active`, and, when members are loaded, `members: [{id, name, position, role, status, supervisor_id, supervisor_name, is_primary, is_manager}]`. The update response always includes `members`.
- List: `GET /departments?with_members=1` includes `members`; without the parameter the payload stays as it is today. A caller without `view_departments` still receives 403.
- Errors: 422 with `errors.employee_ids` or `errors.employee_ids.{index}` and one of `business.department_member_rank_invalid`, `business.department_manager_cannot_be_removed`, `business.employee_inactive`, or the existing `business.department_manager_rank_invalid` where the manager change is the cause. The response includes enough context for the page to name the employee.
- Compatibility: an update without `employee_ids` keeps the current members and behaves exactly like the old endpoint. `employees_count` now counts distinct memberships, which equals the old count for single-department data.
- Permission/data scope: no new permission. The service does not widen `EmployeeScope`; it writes memberships only. A manager who cannot supervise a member is refused even if the actor is a super admin, because the invariant protects the manager's queue.
- Integration seam: Task-023 consumes `members` and `employee_ids`. The service calls `Employee::syncDepartments`; it does not write the pivot directly except to read the current set.
- Files this task must not modify: `resources/ts/pages/setup/departments.vue`, `resources/ts/api/department.ts`, `resources/ts/pages/staff_list.vue`, `app/Models/Employee.php`, `app/Support/EmployeeScope.php`, `routes/api.php`.

## Exclusive file ownership
- `app/Http/Controllers/DepartmentController.php` — `with_members`, `employee_ids`, member presentation and manager-options membership query.
- `app/Support/DepartmentMembership.php` — member diff, rank/manager/inactive validation and primary repair orchestration.
- `tests/Feature/DepartmentManagerTest.php` — manager-options and rank regression after the relation change.
- `tests/Feature/DepartmentMembershipTest.php` — add/remove/refusal/permission coverage.

## Acceptance criteria
- [ ] Given a department with two members, when `GET /departments?with_members=1` is requested, then `members` lists both with `is_primary` and `is_manager`, and `employees_count` matches the pivot count.
- [ ] Given a department with no members, when the same request runs, then `members` is an empty array and the default response without the parameter has no `members` key.
- [ ] Given an employee in Finance, when `PUT /departments/{operations}` sends that employee in `employee_ids`, then the employee is added to Operations, remains in Finance, and the response shows both memberships through the employee resource.
- [ ] Given an employee currently in a department, when the update omits them from `employee_ids`, then their membership is removed, their primary department is promoted to the oldest remaining membership or cleared, and `employees_count` decreases by one.
- [ ] Given the current manager appears in the member set, when the update omits them, then the request returns 422 `business.department_manager_cannot_be_removed`, the manager remains a member, and no other membership changed.
- [ ] Given the department manager cannot supervise an employee being added, when the update runs, then it returns 422 `business.department_member_rank_invalid` on that employee index and the transaction rolls back.
- [ ] Given an inactive employee who is not a current member, when the update tries to add them, then it returns 422 `business.employee_inactive`; an inactive current member can still be removed.
- [ ] Given an update sends an empty `employee_ids` array, when it is saved, then every member is detached and every affected employee's primary department is repaired.
- [ ] Given an update omits `employee_ids` but changes the name or manager, when it is saved, then the member set is unchanged.
- [ ] Given a caller without `manage_departments`, when they attempt the update, then it returns 403 and no pivot row changes.
- [ ] Given the existing department manager and delete-refusal suites, when they run after the relation change, then no assertion regresses.

## Verification plan
- Automated: `php artisan test --filter=DepartmentMembershipTest`; `php artisan test --filter=DepartmentManagerTest`; `php artisan test --filter=EmployeeDepartmentMembershipTest` as a cross-contract check; `php artisan route:list --path=departments` to confirm no new route.
- API checks: add an employee already in another department; remove a primary member; attempt to remove the manager; add an employee the manager cannot supervise; add an inactive employee; send an empty set; omit `employee_ids`; call as a user without `manage_departments`.
- Database assertions: attached/detached counts, one primary per affected employee, no orphan rows, and an unchanged department row after each refusal.
- Manual: confirm a secondary-department member appears in the manager's scope and in the employee's `departments` response.
- Fixtures: one department with an eligible manager, one member with a primary elsewhere, one inactive employee, one employee ranked above the manager. Non-production only.
- Evidence: request/response pairs, row counts before and after each refusal, test names and assertion counts, and the route-list output.

## Implementation notes
Keep validation in the service so the controller stays a transport layer. Load the department and its current member ids once, compute `added = submitted - current` and `removed = current - submitted`, then validate the additions and manager removal before writing anything. For each affected employee, read their full membership set, add or remove the target department, and call `Employee::syncDepartments($ids, $primary)`; that method owns the primary fallback. Wrap the loop in `DB::transaction`. Resolve `is_manager` from `manager_employee_id`, not from a role name. Sort members by name and then id for stable output. Avoid N+1 by loading `user.roles`, `position` and `supervisor` with the member query.

## Risks and rollback
- Risk: removing a member cascades into an unexpected primary change for an employee in several departments. Mitigation: centralize the fallback in Task-021's writer and assert one primary across all memberships.
- Risk: a large `employee_ids` payload produces many queries. Mitigation: load all affected employees and memberships in batches, cap the request at 500 and assert a bounded query count for the normal case.
- Risk: a department update by an older client omits `employee_ids` and is misread as "remove everyone". Mitigation: omitted means unchanged; only an explicit array (including empty) syncs.
- Data migration: none in this task. Rollback is code-only; pivot data remains valid.
- Stop condition: if the department manager is required to be a member by a product rule that is not encoded today, stop and get that rule approved before adding the refusal, because it changes existing manager creation.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs and the `docs/facts` HR description are updated if behaviour changed.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
