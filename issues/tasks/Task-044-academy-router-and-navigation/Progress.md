# Progress — Task-044

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (Vue router and navigation integration implementer)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Wire the six routes/nav after the pages exist, then run the build and browser checks.`

## Acceptance criteria progress
- [ ] Given the new page components exist, when the static router is built, then `/academy`, `/academy/classes`, `/academy/students`, `/academy/catalog`, `/academy/attendance` and `/academy/certificates` resolve to their page components with `requiresAuth`, the `view_academy_contracts` read gate and stable route names; the app builds without a missing dynamic import. — Evidence: `not yet available`
- [ ] Given the Academy nav, when a user with `view_academy_contracts` opens it, then it appears as a top-level section with Overview, Classes, Students, Catalogue, Attendance, Certificates and a Contracts link; an unauthorised user does not see it. — Evidence: `not yet available`
- [ ] Given a user on an Academy page, when the nav renders, then the correct Academy child is active, the existing Contracts → Academy entry still opens the existing contract register/form, and the old Private Contracts highlight is no longer shown for the Academy contract route. — Evidence: `not yet available`
- [ ] Given `/contracts/academy-data`, when it is opened, then it redirects to `/academy/catalog` with the query preserved; the old page remains reachable for audit/support and no old route/page is deleted. — Evidence: `not yet available`
- [ ] Given the app boot, when `php artisan route:list --path=academy` and the frontend build run, then every academy API route is present behind auth and the intended permission, and no route is swallowed by a resource binding. — Evidence: `not yet available`
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark, when the nav is opened, then the Academy group/menu remains usable, keyboard reachable and not hidden behind an unrelated group; long labels wrap rather than overflow. — Evidence: `not yet available`
- [ ] Given the frontend checks, when `npm run typecheck`, `npm run i18n:check`, `npm run build:icons` and `npm run build` run, then they pass and the icon generator reports no missing Academy icon. — Evidence: `not yet available`
- [ ] Given a viewer without `view_academy_contracts`, when they navigate to an Academy URL, then the router guard blocks the page and no Academy API data is loaded. — Evidence: `not yet available`

## Work completed with evidence
- No implementation work has started. The task contract was created from the 2026-11-07 Academy revamp planning session after repository inspection named the real paths and constraints.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Not run yet | — | — | — | Task not started. |

## Decisions and deviations
- Flat routes plus one top-level nav group; the old academy-data path redirects and its page remains reachable.

## Blockers and open questions
- None.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the Academy revamp plan and repository inspection.
