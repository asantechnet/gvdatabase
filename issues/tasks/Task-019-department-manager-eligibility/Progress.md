# Progress — Task-019

## Status snapshot
- Status: `Review`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-10-31`
- Current focus: `Shared eligibility query, rank-aware manager options, field-level guard errors and the delete-refusal dialog are implemented; focused tests and typecheck pass.`
- Blocker: `None`
- Next action: `Reviewer confirms the supervisor-bypass inclusion and the inline-only toast deviation, then runs the manual picker/delete walkthrough.`

## Acceptance criteria progress
- [x] Given the manager picker opens, when it loads, then it contains only active employees linked to a user whose role holds the supervisory permission; employees without a user account or with an ineligible role are absent. — Evidence: `DepartmentController::managerOptions()` uses `eligibleManagerQuery()` (active employee, active linked user, `view_subordinate_records` or the super-admin runtime bypass). `DepartmentManagerTest::test_manager_options_include_only_eligible_employees` asserts the manager is present and the no-user, counselor, inactive-employee and inactive-account employees are absent.
- [x] Given a department with existing members, when the manager picker opens for edit, then it excludes candidates who cannot outrank the members (or shows the fixed saved manager with an explanation if the rule forbids changes). — Evidence: `managerOptions` accepts `department_id`, eager-loads members and filters candidates whose loaded-rank is not greater than every member's. Test `test_manager_options_exclude_candidates_who_cannot_outrank_current_members` proves a manager (300) and an equal-ranked admin (400) are excluded against an admin member, while a super admin (500) remains. The page appends an existing manager who is no longer eligible as a `disabled` option with `common.ui.currentManagerNoLongerEligible` rather than dropping the value.
- [x] Given an ineligible manager submitted directly to the API, when it is validated, then it is rejected with a field-level `manager_id` error and the department is unchanged. — Evidence: the guard throws `ValidationException::withMessages(['manager_id' => ['business.department_manager_not_supervisory']])`; `test_ineligible_manager_is_rejected_with_a_field_error_and_no_department_is_created` asserts `errors.manager_id.0`, the localized `messages.manager_id.0` and that no department row was created. The rank case is covered by `test_manager_who_does_not_outrank_members_is_rejected_and_the_department_is_unchanged`.
- [x] Given the manager field has a server error, when the response returns, then the message is attached to the field inline and exactly one toast appears. — Evidence: the page removes its `toast.error(apiErrorMessage(...))` from `save()`/`remove()`/`load()`, binds `:error-messages="managerError"` (`fieldErrorMessages('manager_id')`) to the `VSelect`, and clears the field error on change. **Deviation:** Task-018's upstream contract is inline-first, so a matched 422 field shows inline with **no** toast; the "exactly one toast" wording is superseded. The page itself never raises a second API toast, and `ApiErrorNotifier` was verified one-event/one-toast in Task-018. No browser capture.
- [x] Given a non-empty department, when delete is attempted, then the dialog stays open and states the specific blocking reason and employee count, with exactly one toast. — Evidence: `destroy()` returns 422 with `code`, localized `message`, `blocking_employee_count` and up to five `blocking_employees`; the page stores that payload in `deleteRefusal` and renders it in the still-open dialog without closing it. Test `test_delete_refusal_returns_the_specific_reason_and_blocking_employees` asserts the payload and that the department remains. The single toast comes from `ApiErrorNotifier`.
- [x] Given an empty department, when delete is confirmed, then the row disappears and a success toast names the department. — Evidence: test `test_empty_department_is_deleted` proves the response and soft delete; the page filters the row out and toasts `${target.name}: ${departmentRemoved}`.
- [x] Given a manager candidate list of several hundred employees, when the dialog opens, then the query is a single request with no per-row user/role lookups. — Evidence: `managerOptions` eager-loads `user.roles` and `departmentRecord`; ranks are read from loaded roles (`rankForEmployee`) rather than `RoleHierarchy::rank()` per row. `test_manager_options_do_not_issue_a_per_candidate_role_query` creates 12 candidates and asserts fewer than 10 total request queries (passed).

## Work completed with evidence
- `DepartmentController`:
  - Added `eligibleManagerQuery()`, the one rule shared by the picker and the save guard: active employee, active linked user, and `view_subordinate_records` **or** the `super_admin` role (mirrors `User::hasPermission()`'s runtime bypass).
  - Added `managerOptions()` with optional `department_id` rank filtering and a compact option payload (`id`, `name`, `employeeId`, `rank`, `departmentId`, `departmentName`).
  - Replaced the per-member `canSupervise()` walk with loaded-rank comparisons, avoiding N×M role queries.
  - Replaced the manager `abort_if`/`abort_unless` guard with field-level `ValidationException`s on `manager_id`.
  - `destroy()` now returns the blocking count/names plus the existing business code and localized message.
  - Added `rankForEmployee()`; deleted nothing else.
- `routes/api.php`: registered `GET departments/manager-options` before the departments routes with `view_departments,manage_departments`.
- `resources/ts/api/department.ts`: added `DepartmentManagerOption`, `DepartmentDeleteRefusal` and `getManagerOptions()`.
- `resources/ts/pages/setup/departments.vue`: loads scoped manager options per dialog, shows a loading/empty state, keeps an ineligible saved manager as a disabled annotated option, binds the manager error inline, leaves the dialog open on failure, renders the blocking count/names, removes all page-level API error toasts, and names the deleted department in the success toast.
- Locale JSON (`en`, `fa-AF`, `ps-AF`): added `common.ui.noEligibleDepartmentManagers` and `common.ui.currentManagerNoLongerEligible` to all three.
- `tests/Feature/DepartmentManagerTest.php`: seven feature tests covering eligibility, rank filtering, both field-level refusals, the delete payload, the empty delete and the query-count bound.

## Files changed and why
- `app/Http/Controllers/DepartmentController.php` — shared eligibility, rank-aware options, field-level guard, delete payload.
- `routes/api.php` — the manager-options route (required for the new contract).
- `resources/ts/api/department.ts` — typed options and refusal payload.
- `resources/ts/pages/setup/departments.vue` — picker, inline error, delete refusal, single-toast adoption.
- `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json` — two new keys in each locale.
- `tests/Feature/DepartmentManagerTest.php` — new coverage.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php -l app/Http/Controllers/DepartmentController.php` | Local PHP 8.2 | PASS | terminal output | |
| 2026-10-31 | `php -l tests/Feature/DepartmentManagerTest.php` | Local PHP 8.2 | PASS | terminal output | |
| 2026-10-31 | `npm run typecheck` | Local | PASS — exit 0 | terminal output | After the page/API/locale changes |
| 2026-10-31 | Locale JSON key assertions | Local Node 24 | PASS | terminal output | Both new keys in all three locales |
| 2026-10-31 | `php artisan test --filter=DepartmentManagerTest` | Local | PASS — 7 tests, 27 assertions | terminal output | Final run |
| 2026-10-31 | `git diff --check` | Local | Pending final check | — | Run with the final bookkeeping |

Two failures were found and fixed during verification:
1. All seven requests returned 403 because `Sanctum::actingAs($actor)` omitted the `access` ability used by the route middleware. Fixed to `Sanctum::actingAs($actor, ['access'])`, matching every existing feature test. No production code changed.
2. The rank-filter test could not find the super-admin candidate because super admins have no explicit `role_permissions` rows (the runtime bypass lives in `User::hasPermission()`). The shared SQL query now mirrors that bypass with `orWhereHas('roles', name = super_admin)`. This was a real defect in the new eligibility query.

## Decisions and deviations
- **Eligibility includes active user accounts.** The task says "active, linked to a user"; the query requires `users.acc_status = active` and the guard reuses the same query, so an inactive account cannot be assigned even through a direct API call. This is stricter than the old guard, which only checked the role permission.
- **Field key is `manager_id`**, as the task contract specifies, even though the request payload field is `manager_employee_id`. The page binds that exact key.
- **Inline-first wins over "one toast"** for a matched manager field (Task-018 contract). The page raises no API toast; the inline message is the single visible feedback in that case.
- **Existing ineligible manager stays visible but disabled**, not silently dropped. Saving with that value unchanged will still fail the shared eligibility guard, so the user must choose an eligible replacement.
- **Delete payload returns at most five names** plus the full count. The dialog can state the count even when many employees block the delete.
- **No browser walkthrough**: the picker, inline rendering and single-toast count were not captured in a live session; evidence is the feature tests, typecheck and source inspection.

## Blockers and open questions
- None functional. A reviewer should confirm the stricter inactive-account rule is acceptable and that excluding every candidate when a department contains a super admin (nobody outranks 500) is the intended fixed-manager case.

## Handoff notes
- Keep `eligibleManagerQuery()` as the only eligibility rule; do not add a second join in the page or in another controller.
- If a saved manager must remain assignable after losing eligibility, that requires an explicit product decision and a change to the shared guard—not a UI-only exception.
- `managerOptions` returns the full eligible set in one response; if the roster grows very large, add search/pagination rather than silently capping it.
- The page no longer toasts API failures; Task-018 owns that feedback. Success and local validation toasts remain.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated HR-002, HR-003 and HR-004; implementation has not started.
- `2026-10-31` Claimed by `Buffy (erp-task-implementer)`, status → `In Progress`.
- `2026-10-31` Implemented the shared eligibility query, manager options, field-level guard errors, delete payload, page adoption and seven tests. Final focused run: 7 passed, 27 assertions. Status → `Review`.
