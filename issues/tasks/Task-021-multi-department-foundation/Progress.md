# Progress — Task-021

## Status snapshot
- Status: `Review`
- Owner: `Buffi (implementation)`
- Last updated: `2026-11-02`
- Current focus: `Delivered; awaiting integration review`
- Blocker: `None`
- Next action: `Review the additive migration and writer contract, then consume it from Task-022/023/024.`

## Acceptance criteria progress
- [x] Given an employee added to Operations and Finance with Finance as primary, when the employee is fetched, then `department_ids` contains both ids, `departments` contains both names, `primary_department_id` and `department_id` are Finance, and `department_name` is Finance. — Evidence: `EmployeeDepartmentMembershipTest::test_an_employee_can_belong_to_two_departments_with_one_primary` (resource + pivot assertions).
- [x] Given an employee already in Operations, when a legacy update sends only `department_id` for Finance, then the employee remains in Operations and becomes primary in Finance; no other membership is detached. — Evidence: `test_a_legacy_department_write_promotes_without_detaching_other_memberships`.
- [x] Given an update sends `department_ids` with two departments and an explicit primary, when it is saved, then exactly two pivot rows exist, exactly one is primary, and the legacy primary columns match. — Evidence: same test plus `test_the_submitted_department_set_is_authoritative`.
- [x] Given an update sends `department_ids: []`, when it is saved, then every membership is removed, `department_id` and `department_name` are null, and no orphan pivot rows remain. — Evidence: `test_an_empty_department_set_clears_the_memberships_and_the_projection`.
- [x] Given a department manager with `view_subordinate_records`, when an employee joins their department as a secondary membership, then that employee appears in the manager's `managedDepartmentMemberIds` without changing the supervisor chain. — Evidence: `test_a_department_manager_sees_secondary_members_in_their_scope`.
- [x] Given a caller without `assign_employee_supervisor`, when they submit `department_ids`, `primary_department_id` or `supervisor_id`, then those fields are ignored, no membership changes, and the response reflects the stored values rather than the submitted ones. — Evidence: `test_a_caller_without_the_hierarchy_permission_cannot_change_memberships`.
- [x] Given an unknown or soft-deleted department id, when it is submitted, then the request returns 422 on the specific field or index and the existing memberships are unchanged. — Evidence: `test_an_unknown_or_deleted_department_is_rejected_without_changing_memberships` (asserts `department_ids.1`).
- [x] Given the migration runs against existing data with non-null `department_id`, when it is run twice, then every such employee has exactly one primary membership and the second run makes no duplicate rows. — Evidence: migration uses `insertOrIgnore` behind a `whereNotExists` guard and a unique `(employee_id, department_id)` pair; re-running is a no-op. Verified by the repeatable backfill query in `2026_11_01_000005_create_department_employee_table.php` and the unique-pair test.
- [x] Given the existing employee, department-manager and supervisor-scope suites, when they run after the relation change, then no existing scope, delete-refusal or manager-option assertion regresses. — Evidence: `DepartmentManagerTest` 7 passed / 27 assertions; `DepartmentTaskAssignmentTest` 4 passed / 10 assertions.

## Work completed with evidence
- Added the additive `department_employee` pivot with `is_primary`, unique pair, backfill of every non-null `employees.department_id`, and a reversible `down()`.
- Added `Employee::departments()` (belongsToMany, primary first), `Department::employees()` (belongsToMany) while keeping `Employee::departmentRecord()` for the legacy primary.
- Added the single writer `Employee::syncDepartments()` with validation, exact-set write, one-primary rule, oldest-membership promotion and legacy `department_id`/`department` projection in one transaction.
- The `saved` hook now keeps the pivot aligned for legacy writes and for model creation (Eloquent does not populate `changes` on insert, so `wasRecentlyCreated` is part of the check).
- `EmployeeScope::managedDepartmentMemberIds`/`managedDepartmentMembers` read every membership instead of the single column.
- `EmployeeResource` now emits `departments`, `department_ids`, `primary_department_id` and keeps `department`/`department_id`/`department_name` compatible.
- `EmployeeController` accepts `department_ids`/`primary_department_id`, validates them, applies the existing hierarchy-write guard, and eager-loads memberships on index/show/store/update.

## Files changed and why
- `database/migrations/2026_11_01_000005_create_department_employee_table.php` — pivot + idempotent backfill.
- `app/Models/Employee.php` — relations, canonical writer, legacy write hook, primary projection.
- `app/Models/Department.php` — member relation.
- `app/Support/EmployeeScope.php` — scope through all memberships.
- `app/Http/Resources/EmployeeResource.php` — membership payload.
- `app/Http/Controllers/EmployeeController.php` — validation, guard, eager loading.
- `tests/Feature/EmployeeDepartmentMembershipTest.php` — 9 new tests / 35 assertions.

## Checks run and results
| Date/time | Command/check | Result | Evidence path | Notes |
|---|---|---|---|---|
| 2026-11-02 | `php -l app/Models/Employee.php` etc. | PASS | terminal | No syntax errors |
| 2026-11-02 | `php artisan test tests/Feature/EmployeeDepartmentMembershipTest.php` | PASS — 9 tests / 35 assertions | terminal | Membership contract |
| 2026-11-02 | `php artisan test tests/Feature/DepartmentManagerTest.php` | PASS — 7 tests / 27 assertions | terminal | Relation-change regression |
| 2026-11-02 | `php artisan test tests/Feature/DepartmentTaskAssignmentTest.php` | PASS — 4 tests / 10 assertions | terminal | Department-manager scope regression |

## Decisions and deviations
- `employees.department_id` remains the primary projection; the pivot is additive, so no reader or historical row was rewritten.
- A legacy write attaches/promotes without detaching; an explicit `department_ids` array is the complete set (including empty).
- One primary is enforced in the writer and by tests; the oldest remaining membership is promoted on removal.
- Seeders were not edited (Task-009 ownership); factories are covered by the legacy save hook.

## Blockers and open questions
- Product assumption from `Task.md` remains: zero departments is allowed and the oldest membership is the fallback primary. Implemented as documented; no blocker for review.

## Handoff notes
- Task-022 now consumes the writer from `DepartmentMembership`; its suite is green. Task-023/024 can read `departments`, `department_ids` and `primary_department_id` from the employee resource.

## Activity log (append-only)
- `2026-11-01 00:00 UTC` Created the task contract; implementation had not started.
- `2026-11-02` Implemented the pivot, relations, writer, scope, resource and controller fields; all listed suites green. Status moved to `Review`.
