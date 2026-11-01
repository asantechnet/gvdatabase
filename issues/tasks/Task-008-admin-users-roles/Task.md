# Task Task-008 — Show every account and describe roles truthfully

## Metadata
- Task ID: `Task-008`
- Title: `Show every account and describe roles truthfully`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P2`
- Owner role: `Full-stack Laravel/Vue implementer`
- Estimated size: `M`
- Source plan/version: `issues/review/issues/10-users-admin-hidden/issue.md`; `issues/review/REPORT.md` §4 P2-8 Users and Roles; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-10`

## Objective
User administration lists the current super administrator alongside every other account, labels verification state correctly, and roles show accurate membership and deletion eligibility.

## Context and problem
`UserController::index()` calls `excludePrimaryRecord()`. `User::primaryRecordId()` is 1, which is the seeded super administrator, so the only super-admin account is absent from `/setup/users`. The demo database has 9 users; the page returns 8 and its footer says "1–8 of 8". The table maps the `common.fields.email` header to the `email_verified` key, so the "Email" column actually shows Verified/Pending. No last-login or last-active information is shown.

The roles page displays `users_count`, but the report observed "Total 1 users" and no permission summary; in-use roles still offer delete. The underlying count must be checked against the actual role membership and pluralized.

## Scope
### Included
- Include the primary super-admin account in the users list while protecting it from deletion and from removal of its last super-admin role.
- Keep the existing rank/hierarchy protection for other accounts.
- Remove the mislabelled Email column or rename it to Verification and show the verification state there; keep the actual email under the user name.
- Add last login or last active when the data is available; if no such column exists, do not invent one, and record the limitation.
- Make search and role/status filters work across the full result set including the primary account.
- Correct the role member count, pluralization and permission summary; disable delete for roles that are assigned to users and explain why.
- Ensure user and role row actions have accessible names and remain reachable at 1280 px through Task-013 primitives.

### Excluded
- Role hierarchy semantics, permission catalogue changes or new roles.
- Account deactivation/reactivation workflow changes.
- Authentication, 2FA and session security (Task-009).
- Audit-log changes (Task-010).

## Preconditions and assumptions
- The primary record is intentionally excluded from employee-facing selectors; this task changes only the user-administration list, not every `excludePrimaryRecord()` call.
- Assumption to validate: `users.last_login_at` or an equivalent exists. If it does not, the acceptance falls back to "created at / last active" already available, and the missing field is recorded as a follow-up.
- The current user may see their own row; self-delete must remain impossible.
- Role membership counts use the same relation as the roles page.

## Dependencies
- `Task-013` — accessible row actions and responsive table primitives.

## Contracts and boundaries
- Input/output: `GET /users` includes the primary record; the shape stays compatible. If a `scope` or `include_primary` parameter is added, default behaviour must be explicitly decided and documented.
- Compatibility guarantee: employee/assignee pickers that rely on `excludePrimaryRecord()` are untouched; only the admin user endpoint changes.
- Error behavior: attempting to delete the primary account or remove its last super-admin role returns a clear 403/422, not a silent no-op.
- Permission/data scope: `view_users`, `edit_users`, `delete_users` and `assign_user_roles` remain the gates; no new permission.
- Integration seam: role membership count and permission summary come from the role resource, not a separate client-side count.
- Files this task must not modify: `app/Http/Controllers/AuthController.php`, `resources/ts/views/pages/account-settings/AccountSettingsSecurity.vue`, `resources/ts/components/responsive-data-table/**`.

## Exclusive file ownership
- `app/Http/Controllers/UserController.php` — admin list scope and self/primary protections.
- `app/Http/Resources/UserResource.php` — email, verification, last-active and role fields.
- `resources/ts/pages/setup/users.vue` — columns, filters, self-protection messaging and action names.
- `app/Http/Controllers/RoleController.php` — membership counts, permission summary and in-use delete guard.
- `app/Http/Resources/RoleResource.php` — count and permission fields.
- `resources/ts/pages/setup/roles.vue` — member count, pluralization, permission summary and delete state.

## Acceptance criteria
- [ ] Given the demo database has 9 users including the primary super admin, when `/setup/users` loads, then all 9 are listed, the footer total is 9, and searching by the admin email finds the account.
- [ ] Given the current super-admin row, when the user opens its actions, then self-delete and removal of the last super-admin role are unavailable with an explanation, and the API rejects a direct attempt.
- [ ] Given the users table, when it renders, then the email address is shown under the user name and the former "Email" column is either removed or renamed Verification with the correct Verified/Pending/Invited content.
- [ ] Given a role assigned to one or more users, when the roles page renders, then the member count is correct and pluralized and delete is disabled with an in-use explanation; the API also refuses the delete.
- [ ] Given a role with permissions, when it is viewed, then a permission summary or count is visible without opening a separate matrix.
- [ ] Given last-login or last-active data exists, when the users list renders, then it is shown; if it does not exist, the limitation is recorded and no fabricated value is displayed.
- [ ] Given user and role row actions at 1280 px, when reached by keyboard or screen reader, then every icon control has an accessible name and no action is hidden without a discoverable affordance.

## Verification plan
- Automated: feature tests for primary inclusion, self-delete rejection, role delete guard and membership count; run `php artisan test --filter=User` and `php artisan test --filter=Role`, then `npm run typecheck`.
- Manual: compare the users table row count with `SELECT COUNT(*) FROM users`; search for the super admin; open the roles page and compare each count with `user_roles`; attempt delete on an in-use role.
- Fixtures: one extra user, one role with two users and one unused role; non-production only.
- Regression: confirm employee assignee pickers still exclude the primary record, account deactivation still works, and role permission editing is unchanged.
- Evidence: DB counts, before/after screenshots, rejected delete response, accessible-name inspection.

## Implementation notes
Do not remove `excludePrimaryRecord()` globally; add an explicit inclusion path for the admin endpoint. Protect the primary account by identity, not only by role name. Use the existing role hierarchy helper for target-rank checks. For the column, the cleanest fix is to drop the duplicate email column and add a Verification chip next to status, but either solution is acceptable if the label matches the content.

## Risks and rollback
- Risk: exposing the primary account to assignee pickers if a shared scope is changed. Mitigation: change only the user-admin query and add a regression assertion for pickers.
- Risk: deleting an in-use role guard changes an existing API response. Mitigation: return a documented error and keep the route; no schema change.
- Data migration: none. Rollback is code-only.
- Stop condition: if last-login data does not exist and is required by the product, stop and create a separate schema task instead of faking it.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
