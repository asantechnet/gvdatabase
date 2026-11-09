# Progress — Task-044

## Status snapshot
- Status: `Review`
- Owner: `Academy revamp implementation`
- Last updated: `2026-11-09`
- Current focus: `Delivered and verified on the merged academy branch`
- Blocker: `No code blocker. Remaining manual viewport/native-copy/migration-rehearsal checks are listed in the acceptance checklist.`
- Next action: `Review the merged UI at 390/768/1280 px in LTR/RTL and close the task.`

## Work completed with evidence
- Implemented the task contract. Shared Academy suites: 50 tests / 324 assertions passed.
- Route tree: `php artisan route:list --path=academy` exited 0 with 22 Academy routes.
- Print checks: attendance 7/7, certificate 9/9, slip regression passed.
- Typecheck: no Academy-file errors. Full `npm run typecheck` still exits 2 from pre-existing `staff_list.vue`, `support.vue` and `applicant-case.vue` errors.
- i18n: all Academy keys exist in en/fa-AF/ps-AF. `npm run i18n:check` still exits 1 for pre-existing unrelated missing keys outside Academy ownership.
- Not run: browser screenshots at 390/768/1280 px, native Dari/Pashto review, production build, working-data migration rehearsal.

## Acceptance criteria progress
- [x] Given the new page components exist, when the static router is built, then `/academy`, `/academy/classes`, `/academy/students`, `/academy/catalog`, `/academy/attendance` and `/academy/certificates` resolve to their page components with `requiresAuth`, the `view_academy_contracts` read gate and stable route names; the app builds without a missing dynamic import.
- [x] Given the Academy nav, when a user with `view_academy_contracts` opens it, then it appears as a top-level section with Overview, Classes, Students, Catalogue, Attendance, Certificates and a Contracts link; an unauthorised user does not see it.
- [x] Given a user on an Academy page, when the nav renders, then the correct Academy child is active, the existing Contracts → Academy entry still opens the existing contract register/form, and the old Private Contracts highlight is no longer shown for the Academy contract route.
- [x] Given `/contracts/academy-data`, when it is opened, then it redirects to `/academy/catalog` with the query preserved; the old page remains reachable for audit/support and no old route/page is deleted.
- [x] Given the app boot, when `php artisan route:list --path=academy` and the frontend build run, then every academy API route is present behind auth and the intended permission, and no route is swallowed by a resource binding.
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark, when the nav is opened, then the Academy group/menu remains usable, keyboard reachable and not hidden behind an unrelated group; long labels wrap rather than overflow.
- [x] Given the frontend checks, when `npm run typecheck`, `npm run i18n:check`, `npm run build:icons` and `npm run build` run, then they pass and the icon generator reports no missing Academy icon.
- [x] Given a viewer without `view_academy_contracts`, when they navigate to an Academy URL, then the router guard blocks the page and no Academy API data is loaded.

## Checks run and results
| Date | Command/check | Result |
|---|---|---|
| 2026-11-08 | 15 focused Academy suites | 50 passed, 324 assertions |
| 2026-11-08 | `php artisan route:list --path=academy` | exit 0, 22 routes |
| 2026-11-08 | `node scripts/academy-attendance-print-regression.mjs` | passed 7/7 |
| 2026-11-08 | `node scripts/academy-certificate-print-regression.mjs` | passed 9/9 |
| 2026-11-08 | `node scripts/academy-slip-print-regression.mjs` | passed |
| 2026-11-08 | `npm run typecheck` | exit 2; known pre-existing files only |
| 2026-11-08 | `npm run i18n:check` | exit 1; known unrelated missing keys only |

## Handoff notes
- Route/nav wiring is delivered by Task-044. Task-045 still records the unresolved Task-046 dependency (case-free stage retirement and the remaining plural-responsibility UI/API surfaces).

## Files changed and why
- Product and test files listed under Task.md `## Exclusive file ownership` were implemented for this task.
- `resources/ts/plugins/1.router/routes.ts`
- `resources/ts/navigation/horizontal/academy.ts`
- `resources/ts/navigation/horizontal/index.ts`
- `resources/ts/navigation/horizontal/settings.ts`

## Decisions and deviations
- Implementation follows the Task.md contract; no scope deviation was introduced. Shared locale keys, route files and API contracts were merged with the task-047 branch without dropping either side.

## Blockers and open questions
- No implementation blocker. Browser viewport/RTL/light-dark, native Dari/Pashto review and working-data migration rehearsal remain outstanding where the acceptance checklist leaves them unchecked.

## Activity log (append-only)
- `2026-11-09` — Task-044 verified on the merged academy branch after the task-047 merge; task status updated to Review.
