# Task Task-002 — Enforce the approved edit-window policy

## Metadata
- Task ID: `Task-002`
- Title: `Enforce the approved edit-window policy`
- Initial status: `Ready`
- Type: `Security`
- Priority: `P0`
- Owner role: `Laravel backend implementer`
- Estimated size: `L`
- Source plan/version: `issuesv2/README.md`, 2026-11-10 audit; Task-001 decision
- Requirement / behavior IDs: `EW-001`, `EW-002`, `EW-005`

## Objective
Apply the policy approved in Task-001 on the server, make the window exercisable by a real seeded employee editor, and keep the override auditable. The observable result is that expired edits are rejected only where the approved policy says so, fresh edits succeed, and an override leaves a recorded audit trail.

## Context and problem
The current middleware is mounted on the whole authenticated group, so a one-hour rule intended for employee records also blocks old task edits. Meanwhile the only seeded role with `edit_employees` also has `override_edit_window`, which makes the window effectively dormant for every seeded editor. The implementation must follow the approved rule rather than adding another broad guard.

## Scope
### Included
- Mount or scope the edit-window middleware according to the Task-001 decision.
- Ensure the governed routes reach the middleware with the record needed for its age check.
- Remove the accidental application-wide gate if the decision is employee-only; if the decision is global, add explicit exemptions for every approved workflow.
- Adjust seeded role grants so at least one real employee editor can exercise the window without also holding `override_edit_window`.
- Preserve `422 business.edit_window_expired`, the translated UI message, and the `edit_window_overridden` audit row.
- Add policy regression coverage for employee edits and for the approved exempt or global behavior.

### Excluded
- Employee form rendering and row-level action state; Task-003 owns that UI.
- Contract edit payloads, extension parent preservation, and contract route binding; Task-004 owns those.
- Changing the override permission name or the 60-minute duration unless Task-001 explicitly approves it.
- Backfilling or editing existing business records.

## Preconditions and assumptions
- Task-001 has approved a decision record; its governed set is the only source of truth.
- The existing `EditWindow` calculation based on `created_at + 60 minutes` remains acceptable unless the decision says otherwise.
- Super administrators continue to bypass through the existing permission mechanism; this task must not create a second bypass path.

## Dependencies
- `Task-001` — requires the approved policy in `Tasks/Task-001-decide-edit-window-policy/decision.md`.

## Contracts and boundaries
- Error contract: expired writes keep `422` with code `business.edit_window_expired` and the existing translated message.
- Override contract: an allowed override still writes an `edit_window_overridden` audit row containing the editable window and override timestamp.
- Permission contract: `override_edit_window` remains the override grant; the task must not silently grant it to every editor.
- Route contract: only the resources named by Task-001 receive the middleware; unrelated task, comment, finance, or master-data routes must not acquire an accidental gate.
- Files/modules this task must not modify: `app/Http/Controllers/ContractController.php`, `app/Models/Contract.php`, employee/contract Vue forms, and all contract-extension tests.

## Exclusive file ownership
- `app/Http/Middleware/EnforceEditWindow.php` — middleware scope and record-age lookup.
- `app/Support/EditWindow.php` — shared window and override behavior.
- `routes/api.php` — mount the middleware only on the approved routes.
- `database/seeders/RolePermissionSeeder.php` — make the window exercisable by a seeded editor.
- `database/seeders/PermissionSeeder.php` — permission catalogue and category consistency.
- `tests/Feature/PhaseFiveIntegrityTest.php` — existing edit-window regression updates.
- `tests/Feature/EditWindowPolicyTest.php` — new policy-level regression coverage.

## Acceptance criteria
- [ ] Given an employee record older than the window and an editor without override, when the editor saves, then the API returns 422 with business.edit_window_expired.
- [ ] Given a freshly created employee record and the same editor, when the editor saves, then the update succeeds.
- [ ] Given a non-employee record covered by the approved policy, when it is edited after the window, then the response matches the decided exemption or enforcement behavior.
- [ ] Given a holder of override_edit_window, when an expired record is saved, then the write succeeds and an edit_window_overridden audit row is recorded.
- [ ] Given a fresh seeded database, when roles are seeded, then at least one employee editor can exercise the window without also holding override_edit_window.
- [ ] Given the edited middleware and routes, when the policy regression suite runs, then it passes without relying on application-wide route mounting.

## Verification plan
- Automated checks: `php artisan test tests/Feature/EditWindowPolicyTest.php tests/Feature/PhaseFiveIntegrityTest.php`.
- Seed check: assert the role/permission matrix in a test or a rolled-back script; do not leave test grants behind.
- Manual/UI scenario: sign in as the seeded non-override editor, edit an old employee record, confirm the translated toast, then use an override holder and confirm success plus audit evidence.
- Expected result: the selected policy branch is observable, no unrelated old record is rejected, and the override leaves an audit row.
- Regression: run the existing comment/subtask window test and confirm it still passes.
- Required evidence: test output, seeded role matrix, expired 422 body, override 200 response, and audit row.

## Implementation notes
- Prefer route-level middleware over controller-side checks unless Task-001 names a route that cannot express the rule there.
- Keep the override logic in one shared place; do not duplicate age arithmetic in controllers.
- The seeded editor should be a realistic operational role, not a new test-only role that exists solely to make the test pass.
- If the decision is global, this task must explicitly list and test each exemption rather than silently exempting whole modules.

## Risks and rollback
- Risk: route middleware order changes could expose governed routes without the window.
- Risk: granting `edit_employees` to a role may broaden data access beyond the intended editor group; scope the role deliberately.
- Risk: an override path could write success without the audit row if the transaction is refactored.
- Rollback: restore the previous route mounting and role grants, remove the new test file, and keep the audit history append-only.

## Handoff checklist
- [ ] Task-001 decision is referenced and followed exactly.
- [ ] Middleware is mounted only on the approved routes.
- [ ] Seeded editor and override holder are both realistic and tested.
- [ ] Expired 422, fresh success, and override audit evidence are recorded.
- [ ] Existing PhaseFive behavior remains green.
