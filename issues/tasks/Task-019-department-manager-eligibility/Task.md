# Task Task-019 — Restrict department managers to eligible employees and explain refusals

## Metadata
- Task ID: `Task-019`
- Title: `Restrict department managers to eligible employees and explain refusals`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P2`
- Owner role: `HR backend owner with Vue form support`
- Estimated size: `M`
- Source plan/version: `hr-issues/issues/HR-002-department-delete-generic-duplicate-toast.md`; `hr-issues/issues/HR-003-duplicate-toast-department-manager.md`; `hr-issues/issues/HR-004-manager-dropdown-ineligible-employees.md`; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `HR-002`, `HR-003`, `HR-004`

## Objective
The department manager picker offers only employees who can actually supervise, and a refused save or delete states the real reason exactly once.

## Context and problem
`resources/ts/pages/setup/departments.vue` loads managers through `EmployeeApi.getOptions()`, which calls `/employees` with no eligibility filter. The picker therefore includes employees with no linked user account and employees whose role lacks `view_subordinate_records`, while `DepartmentController` rejects them server-side. The user discovers the rule only after saving, through a generic toast.

Deleting a non-empty department is also refused server-side, but the page shows a generic message and the global `ApiErrorNotifier` shows another toast for the same 422, so the user sees two toasts and neither names the blocking employees. Department forms have no inline field mapping, so the notifier's attempt to place a field error fails and it falls back to a toast.

## Scope
### Included
- Add a department-scoped manager options endpoint that returns only eligible employees: active, linked to a user, and holding a role with `view_subordinate_records`.
- Keep the server-side guard: ineligible input is still rejected even if the client is bypassed.
- Filter options for an existing department so the chosen manager outranks current members, or explain why a saved manager is fixed.
- Bind the manager field to the server's validation error and show it inline; avoid a duplicate toast (Task-018 owns the single-toast rule).
- On delete refusal, show the specific reason and the blocking employee count/names if the API returns them; keep the dialog open.
- Add tests for the options set, the ineligible save, and the non-empty delete refusal.

### Excluded
- Changing role definitions, permissions or the rank hierarchy.
- Bulk reassigning employees when a department is deleted.
- Redesigning the departments page or its table.
- Employee CRUD or the staff form (Task-006).

## Preconditions and assumptions
- The eligibility rule is `users` linked to the employee and `view_subordinate_records` on one of the user's roles. Confirm the exact permission name used by `DepartmentController` and reuse it; do not introduce a second rule.
- Rank eligibility is assumed to be "manager rank is higher than every current member's rank." If the rule is equality-based or unconstrained, follow the controller and record the deviation.
- Employee options may be paginated: the endpoint must return the full eligible set for the picker or support search; do not silently cap the list.
- Department delete refusal is assumed to already be enforced by the controller; the task changes copy and toast ownership, not the data rule.

## Dependencies
- `Task-018` — single API error toast owner; without it the duplicate toast remains.

## Contracts and boundaries
- Input/output: `GET /departments/manager-options` returns `{ data: [{ id, name, employeeId, rank, departmentId }] }`; `GET /departments` and save/delete routes keep their shapes.
- Compatibility guarantee: an existing department whose manager is no longer eligible still loads; the edit form shows the saved manager as a disabled/annotated option rather than dropping it silently.
- Error behavior: an ineligible manager save returns 422 with `errors.manager_id` and a business code; a non-empty delete returns 422 with the blocking count and a business code. The page shows the message once.
- Permission/data scope: existing `view_departments`, `manage_departments` (or the actual permission names) remain unchanged.
- Integration seam: the picker's eligibility query and the controller's guard must share one scope/rule; do not duplicate the joins in two places without a shared method.
- Ownership handoff: this task is complete. Forward department controller and manager-test changes belong to Task-022; the departments page and API contract belong to Task-023 from 2026-11-01.
- Files this task must not modify: `resources/ts/api/axios.ts`, `resources/ts/utils/apiErrors.ts`, `app/Http/Controllers/EmployeeController.php`, `resources/ts/pages/staff_list.vue`.

## Exclusive file ownership
- None — this task is complete. Forward department controller, page, API and manager-test work is owned by Task-022 and Task-023 from 2026-11-01.

## Acceptance criteria
- [ ] Given the manager picker opens, when it loads, then it contains only active employees linked to a user whose role holds the supervisory permission; employees without a user account or with an ineligible role are absent.
- [ ] Given a department with existing members, when the manager picker opens for edit, then it excludes candidates who cannot outrank the members (or shows the fixed saved manager with an explanation if the rule forbids changes).
- [ ] Given an ineligible manager submitted directly to the API, when it is validated, then it is rejected with a field-level `manager_id` error and the department is unchanged.
- [ ] Given the manager field has a server error, when the response returns, then the message is attached to the field inline and exactly one toast appears.
- [ ] Given a non-empty department, when delete is attempted, then the dialog stays open and states the specific blocking reason and employee count, with exactly one toast.
- [ ] Given an empty department, when delete is confirmed, then the row disappears and a success toast names the department.
- [ ] Given a manager candidate list of several hundred employees, when the dialog opens, then the query is a single request with no per-row user/role lookups.

## Verification plan
- Automated: `php artisan test --filter=DepartmentManagerTest`; add a query-count assertion for the options endpoint; run `npm run typecheck`.
- Manual: create an employee without a user and one with a non-supervisory role, open the picker and confirm both are absent; force an ineligible id through the API and confirm the field error; attempt to delete a populated department and inspect the toast count and copy.
- Fixtures: two departments, one populated, one empty; one eligible manager, one employee without a user, one employee with an ineligible role. Non-production only.
- Regression: verify department create/edit, employee assignment and the staff form's department filter still work; verify the eligible manager can actually view subordinates after assignment.
- Evidence: options response, guard response, delete refusal response, screenshot of inline error and single toast.

## Implementation notes
Extract the controller's existing eligibility check into a reusable query/scope and have both the guard and `manager-options` use it; do not reimplement the joins in Vue or duplicate them in the controller. Return a compact option shape with id, name, employee id, rank and current department, eager-loading the user and roles in one query. Keep a saved manager visible as an annotated, non-selectable option when they no longer qualify. The page binds `errors.manager_id` to the field and leaves the delete dialog open on refusal.

## Risks and rollback
- Risk: the picker becomes empty because no role currently holds the permission. Mitigation: show an explicit empty-state explanation and verify the seeded roles first.
- Risk: excluding a saved manager hides the current assignment. Mitigation: annotate and keep the saved value visible, but do not offer ineligible alternatives.
- Data migration: none.
- Stop condition: if the eligibility rule is not implemented consistently in the controller today, stop and define it with the HR owner before filtering the UI.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
