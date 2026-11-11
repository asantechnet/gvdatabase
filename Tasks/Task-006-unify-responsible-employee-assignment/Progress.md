# Progress — Task-006

## Status snapshot
- Status: `Review`
- Owner: `Laravel and Vue case-access implementer`
- Last updated: `2026-11-10`
- Current focus: `Implementation and verification complete; awaiting review`
- Blocker: `None`
- Next action: `Review the pivot-first access/display change and the documented primary-mirror rule.`

## Acceptance criteria progress
- [x] Given a super admin assigns one responsible employee through the plural assignment field, when the Applications list loads, then the row names that employee instead of Unassigned. — Evidence: Playwright showed row `#12` with `Task006 Admission One, Task006 Admission Two` in the Employee cell; `resources/ts/pages/applicants.vue::employeeLabel()` joins the plural names.
- [x] Given that assigned employee signs in without Manager or view_all_applicants, when they open Applications and the case, then the case is visible. — Evidence: the assigned `staff` user saw row `#12` and opened `/applications/12`; `ResponsibleEmployeeVisibilityTest` asserts list and detail access.
- [x] Given multiple responsible employees on one case, when each signs in, then all assigned employees pass the case access scope and the row remains attributed. — Evidence: `ResponsibleEmployeeVisibilityTest` checks both assigned users, and Playwright confirmed the row attributes both names.
- [x] Given a legacy case with only responsible_employee_id populated and an empty pivot, when it is listed and viewed, then the legacy assignment still works. — Evidence: `ResponsibleEmployeeVisibilityTest::test_a_legacy_singular_owner_remains_visible_and_is_serialized_as_one_assignment` passes for list, detail and `ApplicantResource`.
- [x] Given contract visibility and commission attribution, when a case has plural responsibilities, then contract access uses the same assignment set and existing commission regression tests pass. — Evidence: the visibility suite checks `ContractAccess::scopeVisibleTo()`; `CommissionResponsibilityRegressionTest` passed 4/4.
- [x] Given the updated assignment write path, when responsibility is changed, then the singular field is maintained as a primary compatibility mirror without dropping additional pivot members. — Evidence: assignment tests cover create, re-save and removal; the database fixture showed singular `33` with pivot `[33, 34]`.

## Work completed with evidence
- Made `applicant_responsible_employees` authoritative for Applications list, detail, filter and access checks; the singular column is now a compatibility fallback and mirror.
- Centralized the deterministic mirror rule in `ContractGenerationService::syncResponsibleEmployees()` and `ApplicantController::syncResponsibleEmployees()`.
- Updated `assertCaseAccess()`, `assertCaseManageAccess()`, `scopeCasesToActor()`, the `responsible_employee_id` filter and the `unassigned` focus to use the pivot plus legacy singular fallback.
- Updated `ContractAccess` so contract visibility includes every pivot member and a pivot-only case is not treated as ownerless.
- Updated `ApplicantResource` and controller serialization so `responsible_employee`, `responsible_employee_id`, `responsible_employees` and `responsible_employee_ids` agree.
- Updated `applicants.vue` to display all assigned employee names, with singular and Unassigned fallbacks. Academy rows retain their existing `—` guard.
- Updated `ResponsibleEmployeeAssignmentTest` for the mirror and added removal coverage.
- Added `ResponsibleEmployeeVisibilityTest` for multiple assignees, removal, filters, contract visibility and legacy singular-only rows.
- No Academy-specific backend, route, navigation or commission file was changed by this task. The only Academy reference in the Task-006 diff is the preserved Applications display guard.

## Files changed and why
- `app/Http/Controllers/ApplicantController.php` — pivot-aware filters, list/detail serialization, access/manage scopes and the compatibility mirror.
- `app/Services/Contracts/ContractGenerationService.php` — deterministic pivot-to-singular mirror during contract create/update.
- `app/Support/ContractAccess.php` — contract visibility through all assigned employees and correct ownerless handling.
- `app/Http/Resources/ApplicantResource.php` — consistent singular/plural payloads with legacy fallback.
- `resources/ts/pages/applicants.vue` — multi-employee Applications attribution.
- `tests/Feature/ResponsibleEmployeeAssignmentTest.php` — mirror and removal regressions.
- `tests/Feature/ResponsibleEmployeeVisibilityTest.php` — new access, filter, contract and legacy coverage.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-10 | `php artisan test tests/Feature/ResponsibleEmployeeAssignmentTest.php tests/Feature/ResponsibleEmployeeVisibilityTest.php tests/Feature/CommissionResponsibilityRegressionTest.php` | Local MySQL test DB | PASS — 14 tests, 88 assertions, 59.65s | Terminal output | Includes all new write/access regressions and the commission suite. |
| 2026-11-10 | `php artisan test tests/Feature/ResponsibleEmployeesTest.php` | Local MySQL test DB | PASS — 3 tests, 15 assertions, 56.63s | Terminal output | Protects clearing and legacy singular ownership. |
| 2026-11-10 | `npm run typecheck` | Local Node/Vue | PASS — exit 0 | Terminal output | No TypeScript errors. |
| 2026-11-10 | `python3 Tasks/scripts/validate_tasks.py Tasks` | Local Python | PASS — 9 tasks, 0 errors | Terminal output | Structural ownership/dependency validation. |
| 2026-11-10 | `php -l` on the four changed PHP files | Local PHP 8.2 | PASS — no syntax errors | Terminal output | Includes the final `ContractAccess` grouping change. |
| 2026-11-10 | `git diff --check` on the seven owned files | Git | PASS — no output | Terminal output | Unrelated pre-existing whitespace remains outside task ownership. |
| 2026-11-10 | Playwright CLI super-admin Applications check | Local app at `127.0.0.1:8000` | PASS — row `#12`, Employee cell `Task006 Admission One, Task006 Admission Two` | `/tmp/task006-super-applications.png` | Confirms UI attribution. |
| 2026-11-10 | Playwright CLI assigned `staff` user check | Local app | PASS — row `#12` visible; `/applications/12` opened | `/tmp/task006-assigned-applications.png`, `/tmp/task006-assigned-detail.png` | No Manager or `view_all_applicants` used. |
| 2026-11-10 | Playwright CLI outsider `staff` user check | Local app | PASS — no matching case row | `/tmp/task006-outsider-applications.png` | Confirms assignment scope is required. |
| 2026-11-10 | Temporary DB inspection | Local MySQL | PASS — singular `33`, pivot `[33, 34]` | Terminal output | Confirms mirror and pivot together. |
| 2026-11-10 | Temporary fixture cleanup | Local MySQL | PASS — 0 applicants, users, employees and pivot rows remaining | Terminal output | Playwright sessions closed. |

## Decisions and deviations
- The plural pivot is authoritative. `responsible_employee_id` is a deterministic compatibility mirror, not an independent source of access.
- Mirror rule: an explicitly supplied singular employee is preserved; otherwise the current singular employee is kept while still assigned, and the lowest selected employee id becomes primary when it is not.
- Clearing the plural assignment deletes the pivot but intentionally retains an existing legacy singular owner. The immediate clear response suppresses the legacy fallback so the existing `ResponsibleEmployeesTest` contract still returns an empty assignment list; later reads continue to support legacy singular-only rows.
- `unassigned` now means both the singular field and the pivot are empty.
- A contract whose applicant has pivot assignments is not ownerless; this prevents a pivot-only case from becoming visible to every contract reader.
- Observed during the assigned-user Playwright pass: the case page opened, but two non-blocking toasts appeared while loading manager-only case options (`Unable to load case options` and `You do not have permission to perform this action`). The case access itself succeeded; the option-loading UI is outside this task’s owned files and was not changed.
- No Academy-specific files or Academy commission behavior were changed. `applicants.vue` still renders `—` for Academy rows.

## Blockers and open questions
- None.

## Handoff notes
- Status is `Review`; the seven owned files are uncommitted.
- Reviewer should focus on the primary-mirror rule and the intentional legacy-owner retention when the plural set is cleared.
- Screenshots are in `/tmp` and are temporary; the terminal evidence above is self-contained.
- Re-run the three focused PHPUnit files plus `npm run typecheck` if any of the seven files change.

## Activity log (append-only)
- `2026-11-10` Created task record; implementation has not started.
- `2026-11-10` Claimed Task-006 as Laravel and Vue case-access implementer; traced singular/pivot write paths, access checks, and existing regression coverage.
- `2026-11-10` Implemented pivot-first reads/access, deterministic singular mirroring, contract visibility, resource/controller payload agreement and Applications attribution.
- `2026-11-10` Added assignment-removal and visibility regressions; focused suites passed 14 tests / 88 assertions, compatibility suite passed 3 / 15, typecheck and task validator passed.
- `2026-11-10` Verified super-admin attribution, assigned employee list/detail access and outsider exclusion with Playwright; cleaned up temporary fixtures.
- `2026-11-10` Moved Task-006 to Review.
