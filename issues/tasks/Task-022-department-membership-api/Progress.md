# Progress — Task-022

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned`
- Last updated: `2026-11-01`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Confirm the Task-021 writer contract, then implement the member diff service and extend the department update endpoint.`

## Acceptance criteria progress
- [ ] Given a department with two members, when `GET /departments?with_members=1` is requested, then `members` lists both with `is_primary` and `is_manager`, and `employees_count` matches the pivot count. — Evidence: not yet available
- [ ] Given a department with no members, when the same request runs, then `members` is an empty array and the default response without the parameter has no `members` key. — Evidence: not yet available
- [ ] Given an employee in Finance, when `PUT /departments/{operations}` sends that employee in `employee_ids`, then the employee is added to Operations, remains in Finance, and the response shows both memberships through the employee resource. — Evidence: not yet available
- [ ] Given an employee currently in a department, when the update omits them from `employee_ids`, then their membership is removed, their primary department is promoted to the oldest remaining membership or cleared, and `employees_count` decreases by one. — Evidence: not yet available
- [ ] Given the current manager appears in the member set, when the update omits them, then the request returns 422 `business.department_manager_cannot_be_removed`, the manager remains a member, and no other membership changed. — Evidence: not yet available
- [ ] Given the department manager cannot supervise an employee being added, when the update runs, then it returns 422 `business.department_member_rank_invalid` on that employee index and the transaction rolls back. — Evidence: not yet available
- [ ] Given an inactive employee who is not a current member, when the update tries to add them, then it returns 422 `business.employee_inactive`; an inactive current member can still be removed. — Evidence: not yet available
- [ ] Given an update sends an empty `employee_ids` array, when it is saved, then every member is detached and every affected employee's primary department is repaired. — Evidence: not yet available
- [ ] Given an update omits `employee_ids` but changes the name or manager, when it is saved, then the member set is unchanged. — Evidence: not yet available
- [ ] Given a caller without `manage_departments`, when they attempt the update, then it returns 403 and no pivot row changes. — Evidence: not yet available
- [ ] Given the existing department manager and delete-refusal suites, when they run after the relation change, then no assertion regresses. — Evidence: not yet available

## Work completed with evidence
- None yet. The task contract is approved for scheduling; implementation has not started.

## Files changed and why
- None yet. The exclusive file list is in `Task.md`.

## Checks run and results
| Date/time | Command/check | Result | Evidence path | Notes |
|---|---|---|---|---|
| — | Not run yet | Not run | — | — |

## Decisions and deviations
- None yet. Record the primary-department, rank and copy decisions here when they are settled.

## Blockers and open questions
- None yet. The product assumptions in `Task.md` must be settled during implementation.

## Handoff notes
- Not ready for handoff. Read `Task.md` and `INDEX.md` first; implementation has not started.

## Activity log (append-only)
- `2026-11-01 00:00 UTC` Created the task contract; implementation has not started.
