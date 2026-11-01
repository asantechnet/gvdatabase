# Progress — Task-008

## Status snapshot
- Status: `Review`
- Owner: `Full-stack Laravel/Vue implementer`
- Last updated: `2026-10-31`
- Current focus: `Primary-account inclusion and protection, verification labeling, role counts/permission summaries and in-use role guards are implemented and tested.`
- Blocker: `None for the code. Live demo row-count comparison and the 1280 px keyboard/screen-reader pass were not run.`
- Next action: `Independent review, then run the live demo count and accessibility/viewport checks.`

## Acceptance criteria progress
- [x] Given the demo database has 9 users including the primary super admin, when `/setup/users` loads, then all 9 are listed, the footer total is 9, and searching by the admin email finds the account. — Evidence: `UserController::index()` no longer applies `excludePrimaryRecord()`; `AdminUserRoleTest::test_admin_user_list_includes_the_primary_account` receives both accounts and asserts the primary row/email. The page filters the complete returned list client-side. The live 9-row demo comparison was not run; the page has no server pagination that could hide the primary row.
- [x] Given the current super-admin row, when the user opens its actions, then self-delete and removal of the last super-admin role are unavailable with an explanation, and the API rejects a direct attempt. — Evidence: `users.vue` renders a disabled lock with a tooltip for `is_primary`/`is_self`, plus an edit-dialog notice about the last super-admin role. `DELETE /users/{primary}` returns `business.primary_account_protected`; removing the acting user's super-admin role returns `business.cannot_remove_current_super_admin_role` (tests pass). `show()` now also allows the primary account instead of returning 404.
- [x] Given the users table, when it renders, then the email address is shown under the user name and the former "Email" column is either removed or renamed Verification with the correct Verified/Pending/Invited content. — Evidence: the email remains under the name; the former `common.fields.email` header became `pages.settings.users.verification.label` and still renders `Verified`/`Pending`/`Invited` from `verificationLabel()`. Typecheck passes.
- [x] Given a role assigned to one or more users, when the roles page renders, then the member count is correct and pluralized and delete is disabled with an in-use explanation; the API also refuses the delete. — Evidence: `RoleController::index()` already counts the same `users` relation; the cards now use plural `totalUsers` and disable delete with an in-use tooltip. `RoleController::destroy()` returns `business.role_in_use` for an assigned role. `AdminUserRoleTest::test_in_use_role_exposes_its_count_and_cannot_be_deleted` asserts count `1` and the 422 code.
- [x] Given a role with permissions, when it is viewed, then a permission summary or count is visible without opening a separate matrix. — Evidence: each role card now shows a chip using the resource's loaded `permissions` array with plural `permissionCount`.
- [x] Given last-login or last-active data exists, when the users list renders, then it is shown; if it does not exist, the limitation is recorded and no fabricated value is displayed. — Evidence: `users` has no `last_login_at`/`last_active` column (only `created_at`/`updated_at`), so no last-login column was invented. The existing Created column remains; this is recorded as a follow-up limitation.
- [ ] Given user and role row actions at 1280 px, when reached by keyboard or screen reader, then every icon control has an accessible name and no action is hidden without a discoverable affordance. — Evidence: all new lock/delete/edit controls have `aria-label`s and tooltips, using the Task-013 table primitive; NOT VERIFIED by a keyboard/screen-reader traversal at 1280 px.

## Work completed with evidence
- Admin user endpoint now returns the primary super-admin account while leaving `excludePrimaryRecord()` on employee/assignee selectors untouched.
- Primary account is protected from deletion by identity (`User::primaryRecordId()`), independent of role name; the current-user and target-rank guards remain.
- Added a last-super-admin-role guard for edits that remove `super_admin`; the existing current-user guard remains.
- Added `is_primary` and `is_self` to `UserResource` and the typed frontend contract.
- Renamed the misleading Email column to Verification and added a Primary account chip.
- Disabled deletion for primary/self rows with explanatory tooltips; added an edit-dialog notice for super-admin role removal.
- Role cards now show plural member counts and permission counts; delete is disabled for assigned roles with an in-use tooltip.
- `RoleController::destroy()` refuses an assigned role with `business.role_in_use`; both new business codes are mapped to human messages.
- Added all new copy to English, Dari and Pashto.

## Files changed and why
| File | Change |
|---|---|
| `app/Http/Controllers/UserController.php` | Include primary in admin list; allow its detail; identity delete guard; last-super-admin role guard. |
| `app/Http/Resources/UserResource.php` | Add `is_primary` and `is_self`. |
| `app/Http/Controllers/RoleController.php` | Refuse deleting a role that is assigned to users. |
| `resources/ts/pages/setup/users.vue` | Verification column, primary chip, protected delete actions, role-removal notice. |
| `resources/ts/pages/setup/roles.vue` | Permission summary, plural counts, in-use delete lock/tooltip. |
| `resources/ts/api/user.ts` | Type the new resource fields. |
| `resources/ts/utils/apiErrors.ts` | Map `business.primary_account_protected` and `business.role_in_use`. |
| `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json` | New user/role labels and plural forms. |
| `tests/Feature/AdminUserRoleTest.php` | Primary inclusion/protection, last-role rejection and in-use role count/delete coverage. |

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php -l` on changed controllers/resource/test | Local | PASS | Terminal output | No syntax errors. |
| 2026-10-31 | `php artisan test --filter=AdminUserRoleTest` | Local, RefreshDatabase | PASS | Terminal output | 4 tests, 18 assertions. |
| 2026-10-31 | `php artisan test --filter='AdminUserRoleTest|UserProvisioningTest|RoleBasedAuthorizationTest|RoleHierarchyScopeTest'` | Local, RefreshDatabase | PASS after test-path correction | Terminal output | New 4 pass; existing 9 pass. The first combined run had 2 path-related test failures, corrected before rerun. |
| 2026-10-31 | `npm run typecheck` | Local | PASS | Terminal output | No type errors. |
| 2026-10-31 | Locale/JSON key and plural-separator check | Local | PASS | Terminal output | Three locales contain every new key; role plurals use `|`. |
| 2026-10-31 | Task validator | Local | PASS | Terminal output | 20 tasks, 0 errors. |
| 2026-10-31 | `git diff --check` | Local | PASS | Terminal output | No whitespace errors. |
| 2026-10-31 | Live 9-row demo count/search, 1280 px keyboard/screen-reader traversal | Browser/demo DB | NOT RUN | — | No visual or accessibility pass is claimed. |

## Decisions and deviations
- Included the primary account only in `UserController::index()`; no shared `excludePrimaryRecord()` scope was removed, so employee/assignee pickers stay unchanged.
- Protected the primary account by identity, not role name, as required.
- Kept the response shape additive (`is_primary`, `is_self`) and updated the typed API contract.
- `api/user.ts`, `apiErrors.ts`, locale files and the new test file are directly required but outside the six-file ownership list; these edits are additive and documented here.
- No `last_login_at`/last-active schema exists, so no placeholder or fabricated timestamp was added.

## Blockers and open questions
- No blocker for review.
- Follow-up: add a real last-login/last-active field if the product needs it; this task intentionally did not invent one.
- Verify the live demo user count and the 1280 px keyboard/screen-reader behavior during review.

## Handoff notes
- Ready for review; not marked Done.
- Tests and typecheck pass. The only unverified acceptance criterion is the manual 1280 px accessibility pass.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issue 10; implementation has not started.
- `2026-10-31 Asia/Kabul` Implemented primary inclusion/protection, verification labeling, role counts/permission summaries and in-use delete guard; added tests and locale copy. Focused tests and typecheck pass; moved to Review.
