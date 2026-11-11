# Progress — Task-002

## Status snapshot
- Status: `Review`
- Owner: `Implementation agent`
- Last updated: `2026-11-10`
- Current focus: `Awaiting review`
- Blocker: `None`
- Next action: `Review the employee-only route mount, manager grant and policy suite; then Task-003 exposes the employee UI state.`

## Acceptance criteria progress
- [x] Given an employee record older than the window and an editor without override, when the editor saves, then the API returns 422 with business.edit_window_expired. — Evidence: `EditWindowPolicyTest::test_manager_edits_a_fresh_employee_but_is_blocked_after_the_window`.
- [x] Given a freshly created employee record and the same editor, when the editor saves, then the update succeeds. — Evidence: same test asserts 200 and the persisted name.
- [x] Given a non-employee record covered by the approved policy, when it is edited after the window, then the response matches the decided exemption or enforcement behavior. — Evidence: `EditWindowPolicyTest::test_task_and_contract_edits_are_exempt_from_the_employee_window` and `test_employee_document_routes_are_outside_the_window`.
- [x] Given a holder of override_edit_window, when an expired record is saved, then the write succeeds and an edit_window_overridden audit row is recorded. — Evidence: `EditWindowPolicyTest::test_override_holder_saves_an_expired_employee_and_the_override_is_audited` asserts the 200 response, audit row and `editable_until`.
- [x] Given a fresh seeded database, when roles are seeded, then at least one employee editor can exercise the window without also holding override_edit_window. — Evidence: manager has `edit_employees = true` and `override_edit_window = false` in the policy test; `RolePermissionSeeder` grants the former to `manager`.
- [x] Given the edited middleware and routes, when the policy regression suite runs, then it passes without relying on application-wide route mounting. — Evidence: focused suite passes 8 tests / 60 assertions; task and contract edits remain exempt.

## Work completed with evidence
- Removed `edit-window` from the authenticated route group in `routes/api.php` and mounted it only on the `employees` resource `update` action.
- Verified `PUT|PATCH api/employees/{employee}` middleware order is permission check followed by `EnforceEditWindow`; `PUT api/employees/{employee}/leave-requests/{leaveRequest}` has neither.
- Updated `EnforceEditWindow` documentation to describe the employee-only scope and removed the now-dead self-enforced-action carve-out list. Comment and subtask controllers continue to enforce their existing separate windows.
- Added `edit_employees` to the seeded `manager` role with no `override_edit_window`; `admin` keeps both `edit_employees` and the override.
- Added `tests/Feature/EditWindowPolicyTest.php` covering fresh edit, expired rejection, override audit, task/contract exemption and employee-document exemption.
- Left `PhaseFiveIntegrityTest` unchanged; its comment/subtask and administrative-override cases still pass.
- No migration, backfill, permission rename or new bypass path.

## Files changed and why
- `routes/api.php` — employee-only middleware mount; removed the authenticated-group mount.
- `app/Http/Middleware/EnforceEditWindow.php` — employee-only documentation; removed obsolete self-enforced action exceptions.
- `database/seeders/RolePermissionSeeder.php` — manager receives `edit_employees` without override.
- `tests/Feature/EditWindowPolicyTest.php` — new policy-level regression coverage.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-10 | `php -l` on the four touched PHP files | Local | PASS | — | No syntax errors. |
| 2026-11-10 | `php artisan test tests/Feature/EditWindowPolicyTest.php tests/Feature/PhaseFiveIntegrityTest.php --stop-on-failure` | Local | PASS | — | 8 tests, 60 assertions; 56.19s. |
| 2026-11-10 | `php artisan route:list --path=api/employees` | Local | PASS | `/tmp/employee-routes.json` | Update route has permission + edit-window; leave route has neither. |
| 2026-11-10 | `git diff --check` scoped to touched tracked files | Local | PASS | — | New test file checked separately for trailing whitespace. |
| 2026-11-10 | Global `git diff --check` | Local | FAIL (pre-existing, unrelated) | `In applications create a navigation butt.md:26` | Existing trailing whitespace in an already-dirty file; not touched by this task. |
| 2026-11-10 | `python3 Tasks/scripts/validate_tasks.py Tasks` | Local | PASS | — | 9 tasks; required files/sections, statuses, dependencies, cycles, ownership and INDEX valid. |

## Decisions and deviations
- `team_lead` inherits `edit_employees` because the existing matrix aliases `team_lead` to `manager`; this follows the existing supervisory-role design.
- `PermissionSeeder` was not changed because `override_edit_window` and `edit_employees` already exist; only the manager grant needed updating.
- Employee document upload is tested explicitly as a non-governed route because Task-001 names it as an exemption.
- No UI changes: Task-003 owns employee edit-window state and row actions.

## Blockers and open questions
- None.

## Handoff notes
- Server behavior is ready for review. Task-003 can consume the policy and expose `editable_until`, `edit_window_expired` and `can_edit`.
- Task-004 must treat contract updates as exempt and limit itself to extension integrity.
- The global whitespace failure is unrelated to this task and was left untouched to preserve prior work.

## Activity log (append-only)
- `2026-11-10` Read the approved employee-only decision and mapped the governed route.
- `2026-11-10` Implemented the route mount, manager grant, middleware cleanup and policy regression suite.
- `2026-11-10` Focused policy and PhaseFive suites passed; task moved to Review pending task validation.
- `2026-11-10` Task validator passed (9 tasks); handoff ready for review.
