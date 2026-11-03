# Progress — Task-025

## Status snapshot
- Status: `Review`
- Owner: `Buffi (implementation)`
- Last updated: `2026-11-02`
- Current focus: `Delivered; manual viewport checks outstanding`
- Blocker: `None`
- Next action: `Visual review of the role cards/dialog at 390/768/1280 px, then close.`

## Acceptance criteria progress
- [x] Given a role with level 60, when `/setup/roles` loads, then the role card shows 60 in a labelled Seniority level value and the create/edit dialog shows the same value. — Evidence: card chip `{{ tr('seniorityLevel') }}: {{ role.level ?? 0 }}`; `openEditRole` seeds `roleForm.level`.
- [x] Given an administrator with `manage_role_hierarchy`, when they create a role with level 55, then the API stores 55 and the new row shows it without a reload. — Evidence: `RoleLevelTest::test_a_role_can_be_created_with_a_seniority_level`; `saveRole` reloads roles after create.
- [x] Given the same administrator edits a role from 40 to 65, when they save, then the level changes, the permission set is unchanged, and the row updates. — Evidence: `RoleLevelTest::test_a_level_can_be_raised_without_touching_permissions`.
- [x] Given a user without `manage_role_hierarchy`, when the dialog opens, then the level field is disabled with a short reason and the payload omits `level`; editing the name still works. — Evidence: `levelDisabled` + `seniorityPermissionRequired`; payload adds `level` only when enabled; `RoleLevelTest::test_a_level_change_requires_the_hierarchy_permission`.
- [x] Given a protected role, when the dialog opens, then the level field is disabled with a protected-role reason and a direct API attempt to change it is rejected. — Evidence: `is_protected`/protected names disable the field with `seniorityProtected`; `test_a_protected_role_level_cannot_be_changed` returns 422.
- [x] Given an invalid level such as -1, a decimal or 100001, when the form is submitted, then the field shows a validation message and no request is sent; a direct API attempt returns 422. — Evidence: local integer/range guard sets `seniorityInvalid`; `test_invalid_levels_are_rejected`.
- [x] Given the level hint, when it is read, then it states that higher outranks lower and that the level is used for supervision, department-manager eligibility and assignment ceilings, not as a permission grant. — Evidence: `rolecards.seniorityHint` in all three locales.
- [x] Given an API failure on save, when it returns, then the page shows the shared notifier message once and the field error inline, with no duplicate page toast. — Evidence: `saveRole` no longer calls `toast.error(apiErrorMessage(...))`; `apiFieldErrors(error).level` goes to the field.
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark, when the role-card grid and dialog are used with the keyboard, then the level is readable, the field is reachable, the RTL layout does not reverse the number, and no overflow hides the edit or save action. — Evidence: the chip wraps with the existing flex row and the field uses the existing dialog grid; the actual viewport pass is not recorded.

## Work completed with evidence
- `resources/ts/api/role.ts`: `Role.level`, optional `level` on create/update payloads.
- `resources/ts/pages/setup/roles.vue`: card-level chip, dialog number field with local validation, `manage_role_hierarchy` gating, protected-role gating, inline API field error, page-level API toast removed from save.
- `tests/Feature/RoleLevelTest.php`: 5 tests / 23 assertions covering create, update, hierarchy guard, protected role and invalid values.

## Files changed and why
- `resources/ts/api/role.ts` — type and payload contract.
- `resources/ts/pages/setup/roles.vue` — visible/editable level and gating.
- `tests/Feature/RoleLevelTest.php` — API contract coverage.

## Checks run and results
| Date/time | Command/check | Result | Evidence path | Notes |
|---|---|---|---|---|
| 2026-11-02 | `php artisan test tests/Feature/RoleLevelTest.php` | PASS — 5 tests / 23 assertions | terminal | API contract |
| 2026-11-02 | `npm run typecheck` | PASS | terminal | Page and API types |
| 2026-11-02 | `npm run i18n:check` | PASS | terminal | Seniority keys resolve |

## Decisions and deviations
- The backend already accepted `level` and exposed `is_protected`; no backend change was needed.
- Protected roles remain locked even for a super admin, matching `RoleController`.

## Blockers and open questions
- Manual viewport/RTL/keyboard verification remains outstanding.

## Handoff notes
- Ready for review; no locale or backend file was changed for this task.

## Activity log (append-only)
- `2026-11-01 00:00 UTC` Created the task contract; implementation had not started.
- `2026-11-02` Implemented the role card value, dialog field, permission/protected gating and tests; all checks green. Status moved to `Review`.
