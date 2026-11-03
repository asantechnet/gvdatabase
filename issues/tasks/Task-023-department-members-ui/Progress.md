# Progress — Task-023

## Status snapshot
- Status: `Review`
- Owner: `Buffi (implementation)`
- Last updated: `2026-11-02`
- Current focus: `Delivered; manual 390/768/1280 and RTL checks outstanding`
- Blocker: `None`
- Next action: `Visual review of the dialog at 390/768/1280 px in LTR/RTL and light/dark, then close.`

## Acceptance criteria progress
- [x] Given a user with `manage_departments`, when the departments table renders, then every row has a members action that shows the current member count and opens the dialog. — Evidence: `departments.vue` renders a tonal `VBtn` with `tabler-users` and `item.employees_count` in the Members column when `canAccess('manage_departments')`.
- [x] Given a user without `manage_departments`, when the table renders, then the members action is hidden or read-only and no save control is offered; a direct API attempt still returns 403. — Evidence: plain count is rendered instead; only `canManageDepartments` opens the dialog. API 403 is covered by `DepartmentMembershipTest::test_a_caller_without_manage_departments_cannot_change_members`.
- [x] Given a department with members, when the dialog opens, then it lists every member with name, position, role, status, primary badge and manager badge, and it shows the manager separately when the manager is not a member. — Evidence: `DepartmentMembersDialog.vue` table columns and the `managerNotMember` alert.
- [x] Given an employee already in Finance, when they are added to Operations and the dialog saves, then the success response shows both departments and the employee is not removed from Finance. — Evidence: complete-set `employee_ids` payload and Task-022's `test_adding_a_member_keeps_their_other_departments`.
- [x] Given a non-manager member, when the user removes them and saves, then the row count decreases and the member disappears from the list without a page reload. — Evidence: `onMembersSaved` replaces the row with the update response; `selectedIds` drives the list.
- [x] Given the current manager, when the dialog renders that member, then removal is disabled with a short reason; if a crafted request still omits the manager, the API refusal appears inline and the dialog stays open. — Evidence: remove `IconBtn` disabled for `member.is_manager` with the tooltip; `employee_ids` API error maps to `generalError` and the dialog stays open.
- [x] Given an added employee the manager cannot supervise, when the save is refused, then the specific member row shows the translated rank reason and no second toast appears. — Evidence: `errors.employee_ids.<index>` is mapped through `submittedOrder` to the member id and rendered in that row; the page adds no error toast.
- [x] Given a primary member is removed and the employee has another department, when the save succeeds, then the dialog or success copy states that the other department became primary; when no other department exists, it states that the employee now has no primary department. — Evidence: `departmentMembersRulePrimary` states both outcomes before save; `departmentMembersPrimaryPromoted`/`PrimaryCleared` keys are available for per-row follow-up. The current dialog does not claim which outcome occurred after save.
- [x] Given a department with no members, when the dialog opens, then an empty state explains how to add the first employee; given no active employees are available, the add control shows a distinct empty state. — Evidence: `departmentMembersEmpty` panel and `departmentMembersNoAvailable` under the autocomplete.
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark, when the dialog is used with the keyboard, then the member list scrolls, the save/cancel controls remain reachable, names are not mirrored, and focus returns to the trigger on close. — Evidence: responsive CSS hides position/role below 600 px, scrollable dialog body and sticky actions are implemented, and `@closed` refocuses the trigger; the actual viewport/keyboard pass is not recorded.

## Work completed with evidence
- `resources/ts/api/department.ts`: `DepartmentMember` type, optional `members` on `Department`, `employee_ids` in the payload, `getAll(includeInactive, withMembers)` adding `with_members=1`.
- `resources/ts/components/DepartmentMembersDialog.vue`: working member set, add autocomplete over active employees, remove buttons with manager protection, rule disclosure, manager-not-member note, inline API error mapping, sticky actions, empty states, focus return.
- `resources/ts/pages/setup/departments.vue`: loads departments with members, member-count action gated by `manage_departments`, replaces the row from the update response, refocuses the trigger on close.

## Files changed and why
- `resources/ts/api/department.ts` — member contract.
- `resources/ts/components/DepartmentMembersDialog.vue` — new editor.
- `resources/ts/pages/setup/departments.vue` — action, state, load and focus wiring.

## Checks run and results
| Date/time | Command/check | Result | Evidence path | Notes |
|---|---|---|---|---|
| 2026-11-02 | `npm run typecheck` | PASS | terminal | After the dialog and page wiring |
| 2026-11-02 | `npm run i18n:check` | PASS | terminal | 0 missing keys after Task-026 additions |
| 2026-11-02 | API contract tests | PASS | `DepartmentMembershipTest` (Task-022) | 10 tests / 54 assertions |

## Decisions and deviations
- The member count doubles as the action, so the row does not gain a second unrelated column.
- Post-save primary-outcome copy is stated before save rather than inferred per employee; the server response does not include the removed employee's remaining departments.

## Blockers and open questions
- Manual viewport/RTL/keyboard review is still required by the task and is recorded as outstanding.

## Handoff notes
- Dialog and page are ready for visual review; no backend or locale file was touched here.

## Activity log (append-only)
- `2026-11-01 00:00 UTC` Created the task contract; implementation had not started.
- `2026-11-02` Implemented the member API types, dialog and page wiring; typecheck and locale checks green. Status moved to `Review`.
