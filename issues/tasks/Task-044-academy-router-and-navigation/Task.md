# Task Task-044 — Wire the Academy section into the router and navigation

## Metadata
- Task ID: `Task-044`
- Title: `Wire the Academy section into the router and navigation`
- Initial status: `Ready`
- Type: `Integration`
- Priority: `P1`
- Owner role: `Vue router and navigation integration implementer`
- Estimated size: `M`
- Source plan/version: `Academy revamp planning session 2026-11-07; client decision: make a separate Academy section and localize all academy-related data to this navigation, while keeping Contracts → Academy as the contract creation entry. Repository inspection of the static router, horizontal navigation, settings navigation and the route guards on 2026-11-07.`
- Requirement / behavior IDs: `ACADEMY-NAV-001`, `ACADEMY-NAV-002`, `ACADEMY-NAV-003`

## Objective
Make Academy a first-class top-level section with its own routes and menu, containing Overview, Classes, Students, Catalogue, Attendance, Certificates and a link to the existing Academy contracts page. The old Academy data item moves out of Options → Contracts, and the old path redirects to the new catalogue. Existing contract creation and all unrelated navigation stay where they are.

## Context and problem
The static router has a single `/contracts/academy` route whose `navActiveLink` points at `private-contracts`, and `/contracts/academy-data` lives under Options → Contracts with a `view_country` gate. The new pages exist as components but have no routes, component-map entries, permissions or menu items. Without this integration the Academy section is unreachable in the browser, and the old catalogue remains the only discoverable Academy data page.

This task is the single owner of the static router and the Academy navigation. It deliberately does not create pages, controllers, APIs or copy; it wires the outputs of Tasks 036-043. It is also the owner of the `/contracts/academy` active-link correction.

## Scope
### Included
- Component-map entries for the overview, classes, students, catalogue, attendance and certificates pages.
- Routes `/academy`, `/academy/classes`, `/academy/students`, `/academy/catalog`, `/academy/attendance`, `/academy/certificates` with stable names, `requiresAuth` and the `view_academy_contracts` read gate.
- A redirect from `/contracts/academy-data` to `/academy/catalog` that preserves the query; the old page remains reachable for audit but is no longer the menu entry.
- A top-level Academy navigation group with Overview, Classes, Students, Catalogue, Attendance, Certificates and Contracts (links to the existing `/contracts/academy` route).
- Remove the old "Academy data" child from Options → Contracts and add the new group to the horizontal navigation.
- Correct the `/contracts/academy` `navActiveLink` so the Academy group/contract entry is highlighted consistently instead of Private Contracts.
- Permission-aware navigation: the group and children show only to a viewer with `view_academy_contracts`; route guards enforce the same.
- Route/component smoke checks and the final browser route wiring evidence deferred from Tasks 039-041.

### Excluded
- Creating or editing any page, component, API client, controller, schema or copy. Those belong to Tasks 036-043.
- Changing the existing Contracts → Academy page or its form; the creation entry stays exactly where it is.
- Changing unrelated navigation groups, the applicant portal, the vertical nav layout or the settings menu beyond removing the old Academy data child.
- Deleting the old `academy-data.vue` page or its route; only the menu entry moves and the path redirects.
- A second contract creation form or a new sales page.
- Locale file edits; Task-043 owns the copy.

## Preconditions and assumptions
- Tasks 039-041 have created the page components at the exact paths in their ownership sections.
- Task-042 does not add a page; Task-043 has added the nav labels under `common.navigation.academy`.
- The static router in `resources/ts/plugins/1.router/routes.ts` is the current routing convention; no auto-routing is reintroduced.
- `view_academy_contracts` is the read gate used by the Academy APIs; the super-admin bypass exists in the same way as the other contract categories.
- The horizontal and vertical nav both consume `resources/ts/navigation/horizontal`; updating the horizontal index is enough for both.
- The old `/contracts/academy-data` page is still registered for audit/support; the redirect must not remove its component or route record unexpectedly.

## Dependencies
- `Task-028` — owns `resources/ts/plugins/1.router/routes.ts`; forward ownership of that file moves to this task on 2026-11-07.
- `Task-039` — overview, classes, students and catalogue page components and clients.
- `Task-040` — attendance page and API route.
- `Task-041` — certificates page and API route.
- `Task-043` — frozen nav/page labels in all three locales.

## Contracts and boundaries
- Route names: `academy-overview`, `academy-classes`, `academy-students`, `academy-catalog`, `academy-attendance`, `academy-certificates`. The existing `academy-contracts` name stays unchanged.
- Paths: `/academy`, `/academy/classes`, `/academy/students`, `/academy/catalog`, `/academy/attendance`, `/academy/certificates`; the existing `/contracts/academy` remains the sales page.
- Meta: `requiresAuth: true`, `action: 'read'`, `subject: 'view_academy_contracts'` on every new route; the page itself may add a manage gate for write controls. Do not use `view_country` for any Academy route.
- Nav: one top-level group with a stable title key, a school icon, and child items whose `to` values are the route names above; the Contracts child uses `academy-contracts`. The group is permission-gated with `action: 'read', subject: 'view_academy_contracts'`.
- Redirect: `/contracts/academy-data` points to `{ name: 'academy-catalog', query: to.query }`; keep the old page component/route reachable under its own record or a support-only route if the redirect replaces it. Do not silently delete the old path.
- Active link: `/contracts/academy` uses the Academy contracts child name (`academy-contracts`) so the new group is highlighted; the Contracts group may still contain its own generic entry.
- API wiring: Task-038's `routes/api.php` already requires `routes/academy.php`; Task-040/041 add their route files. This task verifies the app boots and every academy route is present; it does not edit API routes.
- Compatibility: existing routes, guards, component-map keys and navigation order outside Academy are unchanged; the Academy group is inserted after Contracts and before Applications.
- Files this task must not modify: any page/component/API file from Tasks 036-043, `routes/api.php`, `routes/academy.php`, `routes/academy/**`, `resources/ts/plugins/i18n/locales/**`, `lang/**`, `resources/ts/pages/academy-data.vue`, `resources/ts/pages/service-contracts.vue`.

## Exclusive file ownership
- `resources/ts/plugins/1.router/routes.ts` — component-map entries, six academy routes, the old catalogue redirect and the `/contracts/academy` active link; forward ownership from Task-028.
- `resources/ts/navigation/horizontal/academy.ts` — new top-level Academy group and child items.
- `resources/ts/navigation/horizontal/index.ts` — import and register the Academy group in the ordered list.
- `resources/ts/navigation/horizontal/settings.ts` — remove the old Academy data child from Options → Contracts.

## Acceptance criteria
- [ ] Given the new page components exist, when the static router is built, then `/academy`, `/academy/classes`, `/academy/students`, `/academy/catalog`, `/academy/attendance` and `/academy/certificates` resolve to their page components with `requiresAuth`, the `view_academy_contracts` read gate and stable route names; the app builds without a missing dynamic import.
- [ ] Given the Academy nav, when a user with `view_academy_contracts` opens it, then it appears as a top-level section with Overview, Classes, Students, Catalogue, Attendance, Certificates and a Contracts link; an unauthorised user does not see it.
- [ ] Given a user on an Academy page, when the nav renders, then the correct Academy child is active, the existing Contracts → Academy entry still opens the existing contract register/form, and the old Private Contracts highlight is no longer shown for the Academy contract route.
- [ ] Given `/contracts/academy-data`, when it is opened, then it redirects to `/academy/catalog` with the query preserved; the old page remains reachable for audit/support and no old route/page is deleted.
- [ ] Given the app boot, when `php artisan route:list --path=academy` and the frontend build run, then every academy API route is present behind auth and the intended permission, and no route is swallowed by a resource binding.
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark, when the nav is opened, then the Academy group/menu remains usable, keyboard reachable and not hidden behind an unrelated group; long labels wrap rather than overflow.
- [ ] Given the frontend checks, when `npm run typecheck`, `npm run i18n:check`, `npm run build:icons` and `npm run build` run, then they pass and the icon generator reports no missing Academy icon.
- [ ] Given a viewer without `view_academy_contracts`, when they navigate to an Academy URL, then the router guard blocks the page and no Academy API data is loaded.

## Verification plan
- Automated: `npm run typecheck`, `npm run i18n:check`, `npm run build:icons`, `npm run build`; `php artisan route:list --path=academy`; a static-router source check that every component-map key used by an academy route exists.
- Route checks: open each of the six paths directly and via the menu as a permitted user; assert the page renders, not a 404 or a blank layout.
- Redirect check: open `/contracts/academy-data?catalog=durations`; assert the new catalogue opens with the query preserved and the old page remains registered.
- Permission check: a viewer without `view_academy_contracts` sees no Academy menu and is blocked at the route; a manager sees it; the Contracts → Academy entry still works for a contract viewer.
- Viewport checks: 390/768/1280 px, LTR and RTL, light and dark; keyboard traversal of the dropdown; focus return after navigation.
- Print/page regression: the Academy pages from Tasks 039-041 load with their data; no unrelated route or nav item changed.
- Evidence: build output, route-list output, six screenshots (one per page) and the permission/redirect checks.

## Implementation notes
Follow the existing `pageComponents` map + `routeDefinitions` array pattern; do not add a second router. Add lazy imports next to the other academy/contract entries. Use flat routes with the shared layout, not a nested router-view shell, so each page owns its header and the top nav can highlight a child directly.

For the nav, create a dedicated `academy.ts` that follows the shape of `contracts.ts`/`settings.ts`. Insert it in `horizontal/index.ts` after `contracts`. Keep the child labels as translation keys under `common.navigation.academy.*` and the group title `common.navigation.academy` (or the frozen key Task-043 added). Do not hard-code English.

Remove only the old Academy data item from `settings.ts`; leave the Educational data, contract stages, finance and WhatsApp items untouched. Keep the `/contracts/academy-data` route record as a redirect; if the router needs a separate support route for the old component, name it explicitly and document it.

## Risks and rollback
- Risk: a missing component path breaks the Vite build. Mitigation: verify every import path before adding the route; build in CI before handoff.
- Risk: the new group appears for users without permission. Mitigation: gate the nav item and the route with the same permission and test both.
- Risk: the old academy-data deep link is lost. Mitigation: redirect with query preservation and keep the old page reachable; record the exact new path.
- Risk: the Academy group crowds the horizontal nav. Mitigation: test 1280/768/390 px and let the existing overflow/menu behavior handle it; do not add a second row.
- Rollback: revert the four files; the pages/APIs remain but are unreachable, which is the previous state. No data/schema change.
- Stop condition: if a page import path from Task-039/040/041 is missing or named differently, stop and return the dependency instead of creating a duplicate page.

## Handoff checklist
- [ ] Contract and file ownership respected, including the Task-028 handoff.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] All six routes and the redirect are listed with their names/paths.
- [ ] Browser evidence deferred from Tasks 039-041 is recorded here.
- [ ] Remaining limitations and follow-up tasks are explicit.
