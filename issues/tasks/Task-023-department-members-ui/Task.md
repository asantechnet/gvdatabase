# Task Task-023 — Make department membership visible and editable on the departments page

## Metadata
- Task ID: `Task-023`
- Title: `Make department membership visible and editable on the departments page`
- Initial status: `Ready`
- Type: `UI`
- Priority: `P1`
- Owner role: `Vue 3 and Vuetify implementer`
- Estimated size: `M`
- Source plan/version: `User request 2026-11-01: add/remove employees for a specific department from the departments page; repository inspection 2026-11-01`
- Requirement / behavior IDs: `ORG-DEPT-005`, `ORG-DEPT-006`, `ORG-UX-001`

## Objective
On `/setup/departments`, an authorized user can open a department, see every member, add employees who may already belong to other departments, remove members, and understand every refusal without leaving the dialog.

## Context and problem
The departments page currently supports create, edit and delete only. The employee count is read-only, and there is no member list. Assigning a department is possible only from the staff form, one employee at a time, so an HR user cannot answer "who is in this department?" or move a group of people from the place where departments are managed.

The underlying rules are also invisible. An employee can now belong to several departments, adding them here must not remove them elsewhere, the manager must outrank members, the current manager cannot be removed, and a primary department may need a fallback. If the page only shows a disabled button after a rejected save, the user learns the rule by failure. The dialog must state the rules before the save and name the exact employee when the API refuses.

This task uses the API from Task-022 and the copy from Task-026. It does not add a second department write path.

## Scope
### Included
- Add `members` and `employee_ids` to the department API types, plus a `DepartmentMember` type with the fields the API returns.
- Request the member list for the departments page with `with_members=1`; keep the default list call available for any other consumer.
- Add a members action per department row, visible when the user has `manage_departments`, that opens a `DepartmentMembersDialog`.
- Show current members with name, position, role, status, primary badge and manager badge; show the manager separately when the manager is not a member.
- Allow adding active employees from the employee options list and removing current members; save the complete selected set through `DepartmentApi.update(id, { employee_ids })`.
- Disable removal of the current manager with an inline explanation, and let the API remain the final guard.
- Explain in the dialog that an employee may belong to several departments, that this save only changes this department, that the manager must outrank added members, and that removing a primary member promotes another membership or leaves the employee without a primary.
- Show API field errors on the matching employee row or on the add field; keep the dialog open and do not add a second error toast when the global notifier handles it.
- Update the row count and member list after a successful save without a full page reload.
- Add an empty state when the department has no members and when no active employee is available to add.
- Keep the page responsive at 390, 768 and 1280 px, in LTR and RTL, light and dark, and keyboard operable.

### Excluded
- Backend validation, routes, the member payload and the sync service; Task-022 owns them.
- Staff form department multi-select and the Reports-to disclosure; Task-024 owns them.
- Role seniority editing; Task-025 owns it.
- Locale strings; Task-026 owns them. Do not add fallback English strings to the page except the existing key conventions.
- Bulk department reassignment across many departments in one action.
- Deleting a department, changing the manager picker, or changing department counts on the staff page.

## Preconditions and assumptions
- Task-022 returns `members` on `GET /departments?with_members=1` and on the update response, and accepts `employee_ids` on update.
- The employee options source can return all active employees, not only the current table page. If it cannot, the task adds a local fetch with a documented limit rather than silently showing a partial list.
- The current manager may not be a member. The dialog must handle that state without forcing a save or inventing a member row.
- A user without `manage_departments` can still view departments if they hold `view_departments`; the members action is hidden or read-only for them. The route/API remains the final gate.
- Assumption to validate: the departments page is the intended home for membership management, not a separate "members" page. If the product prefers a dedicated page, stop before adding the dialog and record the decision.
- The API returns employee labels and roles; the page does not resolve role names from separate requests per row.

## Dependencies
- `Task-022` — member list, `employee_ids` update and refusal codes.
- `Task-026` — English, Dari and Pashto dialog, rule and error copy.

## Contracts and boundaries
- Input/output: `DepartmentApi.getAll(includeInactive, withMembers)` calls `/departments` with `with_members=1` when requested; `DepartmentApi.update(id, payload)` accepts `employee_ids` and returns the department with `members`.
- UI state: the dialog owns a working copy of the member ids. Cancel discards it; Save sends it once. The page only replaces its row after the API succeeds.
- Error behaviour: a 422 code is mapped to the matching member row or the add control, the dialog stays open, and the page does not show a separate API error toast. A success shows one toast naming the department.
- Permission/data scope: the members action requires `manage_departments`; the page's existing `view_departments` gate remains. No new permission.
- Accessibility: the dialog has a labelled title, the member list is a real list or table with named remove buttons, the add control has a label and a searchable list, and focus returns to the trigger on close.
- Responsive: at 390 px the dialog is full-screen or near full-screen with a scrollable member list and a sticky save bar; at 768 and 1280 px it is a bounded dialog. RTL mirrors the layout without reversing names or counts.
- Integration seam: all copy comes from Task-026 keys; all member writes go through Task-022. The page does not write `department_id` or `employee_ids` anywhere else.
- Files this task must not modify: `app/**`, `routes/api.php`, `resources/ts/pages/staff_list.vue`, `resources/ts/pages/setup/roles.vue`, `resources/ts/plugins/i18n/locales/**`.

## Exclusive file ownership
- `resources/ts/pages/setup/departments.vue` — member action, row state and dialog integration.
- `resources/ts/api/department.ts` — member types, `with_members` parameter and `employee_ids` update.
- `resources/ts/components/DepartmentMembersDialog.vue` — member list, add/remove working copy, inline errors and save/cancel.

## Acceptance criteria
- [ ] Given a user with `manage_departments`, when the departments table renders, then every row has a members action that shows the current member count and opens the dialog.
- [ ] Given a user without `manage_departments`, when the table renders, then the members action is hidden or read-only and no save control is offered; a direct API attempt still returns 403.
- [ ] Given a department with members, when the dialog opens, then it lists every member with name, position, role, status, primary badge and manager badge, and it shows the manager separately when the manager is not a member.
- [ ] Given an employee already in Finance, when they are added to Operations and the dialog saves, then the success response shows both departments and the employee is not removed from Finance.
- [ ] Given a non-manager member, when the user removes them and saves, then the row count decreases and the member disappears from the list without a page reload.
- [ ] Given the current manager, when the dialog renders that member, then removal is disabled with a short reason; if a crafted request still omits the manager, the API refusal appears inline and the dialog stays open.
- [ ] Given an added employee the manager cannot supervise, when the save is refused, then the specific member row shows the translated rank reason and no second toast appears.
- [ ] Given a primary member is removed and the employee has another department, when the save succeeds, then the dialog or success copy states that the other department became primary; when no other department exists, it states that the employee now has no primary department.
- [ ] Given a department with no members, when the dialog opens, then an empty state explains how to add the first employee; given no active employees are available, the add control shows a distinct empty state.
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark, when the dialog is used with the keyboard, then the member list scrolls, the save/cancel controls remain reachable, names are not mirrored, and focus returns to the trigger on close.

## Verification plan
- Automated: `npm run typecheck`; `npm run i18n:check` after Task-026 keys land; a source check for `employee_ids` writes only in the department API/page and for one API error toast owner.
- Manual: open a populated department and a manager-not-member department; add a dual-department employee; remove a primary member and verify the promotion copy; attempt to remove the manager; attempt to add an employee the manager cannot supervise; inspect at 390, 768 and 1280 px in LTR and RTL, light and dark.
- API/DB: confirm the update payload contains the full selected set; confirm the response count and the pivot rows; confirm a refused save leaves the department and pivots unchanged.
- Accessibility: keyboard traversal through trigger, dialog, add list, remove buttons and save/cancel; visible focus; accessible names on icon-only remove buttons.
- Regression: department create/edit/delete still work; the row count matches the member list; the staff page still loads; no global `@page` or print CSS is touched.
- Evidence: screenshots or recorded viewport notes, request/response payloads, before/after counts and the typecheck/locale results.

## Implementation notes
Keep the dialog's working set as `number[]` and derive added/removed counts for the footer. Load employee options once when the dialog opens; filter out current members from the add list but keep them visible in the current list. Use a searchable `VAutocomplete` or `VCombobox` with `chips`, not a raw `<select>`. Map `errors.employee_ids.*` to the employee id at that index; if the error is on the array, show it above the list. Do not optimistically mutate the page row: replace it with the update response. Use `canAccess('manage_departments')` for the action and `canAccess('view_departments')` for the page. Keep all new copy in Task-026 keys, including the manager-not-member note and the primary-fallback note.

## Risks and rollback
- Risk: the dialog sends a stale member set after another user changes the department. Mitigation: show the server refusal and refetch the department; do not merge partial lists.
- Risk: a long member list makes the dialog unusable on mobile. Mitigation: bounded height with internal scroll and a sticky footer; verify at 390 px.
- Risk: role or position fields are missing from the member payload and produce blank cells. Mitigation: treat them as optional labels and show a neutral "not assigned" copy, not a raw id.
- Data migration: none. Rollback is code-only; the page can return to the count-only view.
- Stop condition: if Task-022 does not return stable member ids or an update response, stop and fix the contract rather than reconstructing membership from the staff list.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs and the `docs/facts` HR description are updated if behaviour changed.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
