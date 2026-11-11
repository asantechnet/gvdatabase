# Progress — Task-007

## Status snapshot
- Status: `Review`
- Owner: `Vue Academy implementer`
- Last updated: `2026-11-10`
- Current focus: `Implementation and local acceptance evidence complete; independent review pending`
- Blocker: `None`
- Next action: Reviewer independently checks the route/navigation permission alignment and the teacher/manager completion paths.

## Acceptance criteria progress
- [x] Given an assigned teacher opens Academy Classes, when the class is not completed, then the Complete class action is available. — Evidence: Playwright session `task007assigned` opened `/academy/classes` as `teacher.commission@example.test`; the page listed only the two assigned classes, exposed one `Complete class` button for the temporary incomplete class, showed no alerts, and logged no console errors.
- [x] Given an unassigned teacher opens the same page, when the class is not completed, then the Complete class action is not available. — Evidence: Playwright session `task007unassigned` opened `/academy/classes` as a Staff + Teacher user; the page loaded four visible classes, exposed zero Complete class buttons, and showed no alerts.
- [x] Given a super admin opens Users, when choosing roles, then Teacher is present and can be saved on an account. — Evidence: Playwright session `task007` opened Users → Edit user for Test Teacher; the role list contained Teacher, the selection was saved, and the refreshed row rendered the Teacher chip.
- [x] Given the assigned teacher completes the class, when the request finishes, then the class is completed and the existing teacher commission path still runs. — Evidence: The assigned session clicked Complete class; the row changed to Completed and the button disappeared. Database check found `completed_at = 2026-11-10 10:54:15` and one pending `commission_accruals` row for employee 10 with base amount `100.000000`.
- [x] Given the role option is added, when the three locales render, then the Teacher label is translated and the page passes typecheck. — Evidence: targeted extraction returned `Teacher`, `معلم`, and `ښوونکی`; `npm run typecheck` exited 0 after the route/navigation follow-up.

## Work completed with evidence
- Completion availability now follows `canManage || row.teachers contains the signed-in employee`; the completed guard remains unchanged.
- Added the typed `teacher` role and labels to all three locales.
- Added focused backend coverage for assigned and unassigned teachers; both cases pass.
- Scope deviation: the classes route and navigation item were gated by `view_academy_contracts`, while the class-list API and Teacher role use `view_academy_attendance`. They were aligned so a teacher-only account can reach the page; this was added to Task.md ownership.
- The page now requests manager-only program and teacher options only when `canManage` is true. Without this, a teacher session's expected 403s from those unrelated endpoints made `Promise.all` mark the whole page as failed.
- Temporary UI fixtures were cleaned up: user roles and password hash restored, the temporary class deleted, and its commission accrual removed. Post-cleanup checks confirmed `user11_roles=staff`, `user11_hash_restored=yes`, `user5_roles=staff`, `class4_exists=no`, `class4_accruals=0`.

## Files changed and why
- `resources/ts/pages/academy/classes.vue` — per-row completion condition and manager-only option loading.
- `resources/ts/plugins/1.router/routes.ts` — classes route now accepts the attendance permission the API already requires.
- `resources/ts/navigation/horizontal/academy.ts` — classes navigation follows the same permission.
- `resources/ts/pages/setup/users.vue` — Teacher role option.
- `resources/ts/api/user.ts` — typed Teacher role.
- `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json` — Teacher labels.
- `tests/Feature/TeacherClassCompletionTest.php` — assigned/unassigned completion regression.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-10 | `php artisan test tests/Feature/TeacherClassCompletionTest.php tests/Feature/AcademyPermissionTest.php` | local PHP/Laravel | PASS | terminal output | 4 tests, 21 assertions; assigned completion succeeds and unassigned completion is 403. |
| 2026-11-10 | `npm run typecheck` | local Node/Vue | PASS | terminal output | Exit 0 after the route/navigation and option-loading follow-up. |
| 2026-11-10 | `npm run i18n:check` | local Node | PRE-EXISTING FAIL | terminal output | Missing keys across en/fa/ps remain, including pre-existing Academy and settings keys. The added `pages.settings.users.roleOptions.teacher` key is present in all three locales. |
| 2026-11-10 | Playwright: unassigned teacher on `/academy/classes` | local app at 127.0.0.1:8000 | PASS | session `task007unassigned` | Four rows visible, zero Complete class buttons, zero alerts. |
| 2026-11-10 | Playwright: assigned teacher completion on `/academy/classes` | local app at 127.0.0.1:8000 | PASS | session `task007assigned` | One Complete class button before completion; row Completed and zero complete buttons after; no alerts or console errors. |
| 2026-11-10 | Playwright: super admin Users role save | local app at 127.0.0.1:8000 | PASS | session `task007` | Teacher was present in the role list, saved, and rendered in the refreshed row. |
| 2026-11-10 | Database assertion for completion and commission | local database | PASS | terminal output | Class 4 had `completed_at`; one pending Academy accrual for employee 10 with base `100.000000`. Test fixture was then removed. |
| 2026-11-10 | `python3 Tasks/scripts/validate_tasks.py Tasks` | task root | PASS | terminal output | 9 tasks, 0 errors, 0 warnings after this Review update. |
| 2026-11-10 | `git diff --check` on owned files | git | PASS | terminal output | No whitespace errors in the owned files. |

## Decisions and deviations
- Route/navigation permission is part of the required path, not a new policy: `GET /api/academy/classes` already uses `view_academy_attendance` and scopes teacher results to assigned classes. The Teacher role intentionally does not hold `view_academy_contracts`; leaving the frontend gate on that permission made the acceptance path unreachable.
- Program and teacher option lists are only needed by the create/edit dialog, which is manager-only. They are skipped for teacher sessions so their expected 403s cannot turn the class page into a failed load.
- The existing i18n audit failure is broader than this task and includes Academy keys that predate this change; no cleanup was attempted because it is outside Task-007's ownership.

## Blockers and open questions
- None for this task.
- Pre-existing `npm run i18n:check` failures remain in the repository; they are not attributed to this change.

## Handoff notes
- Ready for independent review.
- Review the route/navigation permission alignment first: both now use `view_academy_attendance`, matching the API and the Teacher role.
- Re-run the focused backend test and typecheck after any review edit. The i18n audit remains a pre-existing repository failure, not a clean gate.
- No backend completion rules, commission logic, student enrollment behavior, or contract screens changed.

## Activity log (append-only)
- `2026-11-10` Created task record; implementation has not started.
- `2026-11-10` Claimed Task-007 as Vue Academy implementer; checked backend route, teacher role grants, and existing userData cookie shape.
- `2026-11-10` Implemented completion visibility, Teacher role UI/locales, route/navigation alignment, and focused backend coverage.
- `2026-11-10` Verified assigned and unassigned teacher sessions, saved the Teacher role through Users, completed a temporary class, confirmed the commission accrual, and restored all temporary data; moved task to Review.
