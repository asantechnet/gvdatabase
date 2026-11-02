# Task Task-025 — Make each role's seniority level visible and editable

## Metadata
- Task ID: `Task-025`
- Title: `Make each role's seniority level visible and editable`
- Initial status: `Ready`
- Type: `Frontend`
- Priority: `P1`
- Owner role: `Vue 3 and Vuetify implementer with role-admin review`
- Estimated size: `M`
- Source plan/version: `User request 2026-11-01: seniority level must be editable for each role; repository inspection 2026-11-01`
- Requirement / behavior IDs: `ORG-ROLE-001`, `ORG-ROLE-002`, `ORG-UX-003`

## Objective
On `/setup/roles`, every role shows its seniority level and an authorized administrator can create or edit that level, with a clear explanation of what rank changes and what it does not.

## Context and problem
`roles.level` exists in the database, the role resource already returns it, and `RoleHierarchy` uses it for supervisor eligibility, department-manager eligibility, approval ceilings and role-assignment checks. The roles page shows role cards with no seniority value and its create/edit dialog only sends `name`, so the value is effectively read-only in the product. The user cannot promote or demote a role without editing the database.

The rule is also easy to over-read. A higher level outranks a lower one, but it does not grant permissions by itself; permissions remain on the role. The page must say that, show the current level next to each role, and keep the field disabled for protected roles and for users without `manage_role_hierarchy`.

The backend already exposes `level` and rejects unauthorized changes; this task is the missing frontend surface. If inspection shows the controller does not accept `level` on create, stop and record the gap rather than inventing a second hierarchy endpoint.

## Scope
### Included
- Add `level` to the frontend `Role` and `AppRole` types, confirm `is_protected` is available to the page, and add `level` to the create/update payload types.
- Show the numeric seniority level on each role card with a short rank explanation, and add the level field to the create/edit dialog.
- Add a number field to the create/edit dialog, integer 0 through 100000, with a hint that higher outranks lower and that it does not grant permissions.
- Send `level` on create and on update only when the user holds `manage_role_hierarchy`; preserve the current level on edit when the field is read-only.
- Disable the level field for protected roles (`super_admin`, `applicant` or the resource's `is_protected` flag) and explain why.
- Show server field errors for `level` inline; remove any page-level duplicate API error toast so the Task-018 notifier remains the one owner of failure feedback.
- Keep the existing permission editor and role-name rules unchanged.
- Keep the role-card grid and dialog responsive at 390, 768 and 1280 px, in LTR and RTL, light and dark.

### Excluded
- Changing `RoleHierarchy` levels, the seeded defaults, approval logic or permissions.
- Backend controllers, resources, migrations, seeders or tests for the existing API.
- User role assignment and the staff form; Task-024 owns the staff surface.
- Department manager eligibility; Task-022 owns the member rules.
- Locale files; Task-026 owns the copy.
- Reordering roles by drag-and-drop or adding a hierarchy chart.

## Preconditions and assumptions
- `RoleController` accepts `level`, calls `RoleHierarchy::canAssignLevel` on create and on a changed level, and requires `manage_role_hierarchy` for an update; `RoleResource` returns `level` and `is_protected`. Verify both before editing the page. If not, stop and record the contract gap.
- Existing roles may have level 0 because they were created before the hierarchy seed. The UI shows 0 as the real value and does not silently rewrite it.
- Assumption to validate: higher numeric value means more senior, with no gaps or equal-rank semantics beyond the existing `RoleHierarchy` rules. If equal rank has a special meaning, show that in the hint instead of inventing one.
- A user with `create_roles` but without `manage_role_hierarchy` can create a role; the level field is read-only and omitted, leaving the server default. Confirm the server default before relying on it.
- Protected roles cannot change level even for a super admin. The UI disables the field and the server remains the final gate.

## Dependencies
- `Task-026` — role seniority labels, hints and permission copy.

## Contracts and boundaries
- Input/output: `Role` gains `level: number`; `RoleCreateData` and `RoleUpdateData` gain `level?: number`; `is_protected` already exists in `Role` and is added to the page's `AppRole`. `RoleApi.create` and `RoleApi.update` pass the field through unchanged.
- UI behaviour: the level field shows the current value on edit and a sensible default on create; it is disabled when the user lacks `manage_role_hierarchy` or the role is protected. A disabled field is not sent, so the server keeps the stored value.
- Error behaviour: 422 `errors.level` maps inline to the number field; 403 from the hierarchy guard shows the Task-018 message once, with no page toast added. The dialog stays open on failure.
- Permission/data scope: `view_roles`/`view_role_permissions` continue to gate the page; `create_roles`/`edit_roles` gate the dialog; `manage_role_hierarchy` gates a level change. No new permission.
- Accessibility: the number field has a visible label, hint and error text; the disabled reason is exposed as helper text, not only a tooltip; each role card exposes a labelled seniority value.
- Responsive: at 390 px the level wraps under the role name without hiding the edit action; at 768 and 1280 px it sits in the card metadata. RTL keeps the number LTR within the field and card.
- Integration seam: the page uses Task-026 keys and the existing role API. It does not compute rank or authorization from role names on the client.
- Files this task must not modify: `app/**`, `database/**`, `resources/ts/plugins/i18n/locales/**`, `resources/ts/pages/staff_list.vue`, `resources/ts/pages/setup/departments.vue`.

## Exclusive file ownership
- `resources/ts/pages/setup/roles.vue` — role-card level value, dialog field, permission gating and error placement.
- `resources/ts/api/role.ts` — `level`/`is_protected` types and create/update payloads.
- `tests/Feature/RoleLevelTest.php` — API contract coverage for create/update/guard/protected roles.

## Acceptance criteria
- [ ] Given a role with level 60, when `/setup/roles` loads, then the role card shows 60 in a labelled Seniority level value and the create/edit dialog shows the same value.
- [ ] Given an administrator with `manage_role_hierarchy`, when they create a role with level 55, then the API stores 55 and the new row shows it without a reload.
- [ ] Given the same administrator edits a role from 40 to 65, when they save, then the level changes, the permission set is unchanged, and the row updates.
- [ ] Given a user without `manage_role_hierarchy`, when the dialog opens, then the level field is disabled with a short reason and the payload omits `level`; editing the name still works.
- [ ] Given a protected role, when the dialog opens, then the level field is disabled with a protected-role reason and a direct API attempt to change it is rejected.
- [ ] Given an invalid level such as -1, a decimal or 100001, when the form is submitted, then the field shows a validation message and no request is sent; a direct API attempt returns 422.
- [ ] Given the level hint, when it is read, then it states that higher outranks lower and that the level is used for supervision, department-manager eligibility and assignment ceilings, not as a permission grant.
- [ ] Given an API failure on save, when it returns, then the page shows the shared notifier message once and the field error inline, with no duplicate page toast.
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark, when the role-card grid and dialog are used with the keyboard, then the level is readable, the field is reachable, the RTL layout does not reverse the number, and no overflow hides the edit or save action.

## Verification plan
- Automated: `php artisan test --filter=RoleLevelTest`; `npm run typecheck`; `npm run i18n:check`; a source check for one API error toast owner on the roles page.
- API checks: create with level; update with level; update without `manage_role_hierarchy`; update a protected role; invalid level values. Confirm the permission set and role name are unchanged by a level-only update.
- Manual: open the roles page at 390, 768 and 1280 px; edit a role; create a role; open as a user without the hierarchy permission; open a protected role; trigger a server level error.
- Regression: role member count, permission summary, in-use delete guard and role name validation still work; no other page reads a cached role shape that now lacks the new fields.
- Evidence: test names and assertion counts, typecheck/locale results, before/after role rows, and viewport notes.

## Implementation notes
Add the field to the existing role dialog rather than a separate editor. Use `VTextField type="number"` with `min=0`, `max=100000`, `step=1` and a computed disabled reason; validate integer and range locally before the request. Keep the level out of the payload when the field is disabled. On edit, initialize from `role.level` and do not coerce null to 0 without showing it. Use `is_protected` when present; fall back to the two protected role names only if the resource does not expose the flag, and record that fallback. Remove any `toast.error(apiErrorMessage(...))` in this page that duplicates the Task-018 notifier, but keep local success toasts.

## Risks and rollback
- Risk: an administrator changes a system role's rank and changes who can supervise whom. Mitigation: require `manage_role_hierarchy`, show the current and new value, and keep protected roles locked; the page does not auto-recalculate existing assignments.
- Risk: a missing `is_protected` field makes protected roles editable. Mitigation: fall back to the known protected names and test a direct API refusal; record the resource gap.
- Risk: a read-only field is still sent by a shared payload builder. Mitigation: build the payload conditionally and assert the omitted key.
- Data migration: none. Rollback is code-only; stored levels remain valid.
- Stop condition: if the role API does not accept `level` on create/update or does not expose `is_protected`, stop and raise a backend contract task instead of faking the field.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs and the `docs/facts` role-hierarchy description are updated if behaviour changed.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
