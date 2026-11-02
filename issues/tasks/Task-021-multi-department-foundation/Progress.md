# Progress — Task-021

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned`
- Last updated: `2026-11-01`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Approve the primary-department projection and the additive pivot schema, then implement the migration and canonical membership writer.`

## Acceptance criteria progress
- [ ] Given an employee added to Operations and Finance with Finance as primary, when the employee is fetched, then `department_ids` contains both ids, `departments` contains both names, `primary_department_id` and `department_id` are Finance, and `department_name` is Finance. — Evidence: not yet available
- [ ] Given an employee already in Operations, when a legacy update sends only `department_id` for Finance, then the employee remains in Operations and becomes primary in Finance; no other membership is detached. — Evidence: not yet available
- [ ] Given an update sends `department_ids` with two departments and an explicit primary, when it is saved, then exactly two pivot rows exist, exactly one is primary, and the legacy primary columns match. — Evidence: not yet available
- [ ] Given an update sends `department_ids: []`, when it is saved, then every membership is removed, `department_id` and `department_name` are null, and no orphan pivot rows remain. — Evidence: not yet available
- [ ] Given a department manager with `view_subordinate_records`, when an employee joins their department as a secondary membership, then that employee appears in the manager's `managedDepartmentMemberIds` without changing the supervisor chain. — Evidence: not yet available
- [ ] Given a caller without `assign_employee_supervisor`, when they submit `department_ids`, `primary_department_id` or `supervisor_id`, then those fields are ignored, no membership changes, and the response reflects the stored values rather than the submitted ones. — Evidence: not yet available
- [ ] Given an unknown or soft-deleted department id, when it is submitted, then the request returns 422 on the specific field or index and the existing memberships are unchanged. — Evidence: not yet available
- [ ] Given the migration runs against existing data with non-null `department_id`, when it is run twice, then every such employee has exactly one primary membership and the second run makes no duplicate rows. — Evidence: not yet available
- [ ] Given the existing employee, department-manager and supervisor-scope suites, when they run after the relation change, then no existing scope, delete-refusal or manager-option assertion regresses. — Evidence: not yet available

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
