# Task: Unify concept navigation (global links + in-page header navigation)

## Task: Unify concept navigation (global links + in-page header navigation)

- **Task ID:** unify-concept-navigation-and-page-headers
- **Created:** 2026-10-14
- **Last updated:** 2026-10-14
- **Status:** COMPLETED
- **Outcome:** Each business concept (Contracts, CRM, Applications) has exactly one global navigation link and one in-page navigation strip shared by all of its pages. Every in-page navigation button carries its own icon and colour, and the Applications pages gained the breadcrumb + in-page navigation design already used by the Visitors and Contracts pages.

### 1. Requirements and acceptance criteria

- [x] AC1 The Contract global nav group is removed; a single link to the Contracts page (default `scholarship-contracts`) appears instead.
- [x] AC2 The CRM global nav group is removed; a single link labelled CRM pointing at the Visitor page appears instead.
- [x] AC3 The Applications pages have their own single global link (default `/applications`); `/applications`, `/search` and `/visa-reports` are no longer children of another group.
- [x] AC4 For each concept exactly one global link exists (no concept page appears twice, at the top level or nested).
- [x] AC5 `/applications`, `/search` and `/visa-reports` all render breadcrumbs plus the same in-page navigation (Applications / Search / Visa reports).
- [x] AC6 Contracts pages stay interconnected (6 links: private, scholarship, test-offer, academy, other, cancelled) and CRM pages stay interconnected (visitor, applicants).
- [x] AC7 Every in-page navigation button declares its own icon and its own colour, and the active page is filled and marked `aria-current="page"`.
- [x] AC8 Navigation stays usable in LTR and RTL, light and dark, and on mobile widths.

### 2. Scope and non-goals

- In scope: `resources/ts/navigation/horizontal/*`, `resources/ts/components/erp/ErpPageHeader.vue` + `workspace.scss`, the three Applications pages (`applicants.vue`, `search.vue`, `educational-reports.vue`), `applicant-pipeline.vue` section rename, `erp.applications` in en/fa-AF/ps-AF, and the affected regression scripts.
- Out of scope: backend/API changes, permission-model changes, route/path changes, redesigning table and card bodies, the Options/Finance/HR/Setup groups, and deleting the unused `navigation/horizontal/visitor.ts` snapshot.

### 3. Project findings

- Horizontal nav is authoritative; `navigation/vertical/index.ts` re-exports it, so both layouts share one list. A leaf entry (`home.ts`) is the established "single link" shape: `{ title, icon, to, action, subject }` with no `children`.
- `navigation/horizontal/contracts.ts` was a group (`to: 'scholarship-contracts'` + 8 children) that also carried the Applications pages, folded in by `docs/ai/contracts-applications-merge-and-per-service-stages.md` (its AC1). This task supersedes that placement.
- `navigation/horizontal/crm.ts` was a group with two children (`all-customers`, `visitor`). `navigation/horizontal/visitor.ts` is dead code (never imported by `index.ts`).
- `components/erp/ErpPageHeader.vue` is the shared breadcrumb + in-page-navigation header (`title`, `section`, `breadcrumbs`). Its links were `[routeName, labelKey, permission]` triples with no icon/colour, and the `applicants` list was empty.
- Consumers (grep + `graphify query` agree): `all_customers.vue`, `applicant-pipeline.vue`, `applicants.vue`, `cancelled_contracts.vue`, `contract_list.vue`, `educational-dashboard.vue`, `home.vue`, `service-contracts.vue`, `visitor.vue`.
- `applicants.vue` passed `:breadcrumbs="false"` and rendered no in-page nav; `search.vue` and `educational-reports.vue` had no `ErpPageHeader` at all (`educational-reports.vue` hand-rolled `.education-page-header` with its own namespaced print CSS).
- Theme colours available: `primary`, `secondary`, `tertiary`, `success`, `info`, `warning`, `error` (light and dark definitions both present in `plugins/vuetify/theme.ts`).
- Existing i18n keys covered every needed label; only `erp.applications` was missing.
- `scripts/applications-tabs-regression.mjs` asserted the opposite of AC5 (no breadcrumbs, no `.erp-subnav` on the applications page) — it had to change direction because the requested behaviour reverses that earlier decision.

### 4. Graph and impact review

- Tool used: `graphify query` against the existing `graphify-out/graph.json` (12149 nodes, built from commit `c4c97435`), plus repository search and direct file inspection. The graph already lists `ErpPageHeader.vue` and all ten consumers, so the impact set was confirmed rather than discovered from scratch.
- Impact traced: nav barrel → both layouts; `ErpPageHeader` → 10 pages (only the 4 concept pages change behaviour); Applications pages → `scripts/applications-tabs-regression.mjs`; `service-contract-regression.mjs`'s SFC compile list and `erp` locale-parity guard.
- No backend surface is touched (nav and header are frontend-only).
- Limitation: the graph does not model the icon-bundle generator, so icon availability was verified by running `npm run build:icons` and by asserting against the generated `icons.css`.

### 5. Clarifications, decisions, and assumptions

- **User decisions:**
  - Applications keeps its own top-level global link (default `/applications`) rather than being folded into the Contracts strip.
  - The CRM single link keeps the label **CRM** while pointing at the Visitor page.
- **Delegated choices (the request explicitly delegates the default page per concept):**
  - Contracts default → `scholarship-contracts` (the group's former `to`, and the most-used service).
  - CRM default → `visitor`.
  - Applications default → `applications`.
- **Low-risk assumptions:**
  - The header `section` was renamed `applicants` → `applications` so the breadcrumb matches the concept name; `erp.applications` was added to all three locales. `erp.applicants` is left in place (now unused, harmless).
  - The six contract destinations stay as they are; `employee-contracts` stays under HR and is not duplicated into the Contracts strip.
  - `navigation/horizontal/visitor.ts` stays untouched (dead, unimported) to keep the diff minimal.
  - The dropped `educational.decisionReports` eyebrow and `educational.reportsSubtitle` line on the visa reports page are intentional: the page now uses the same header shape as the other concept pages. Both keys remain in the catalogue.

### 6. Implementation plan

- [x] Step 1 — `navigation/horizontal/contracts.ts`: dropped `children`, keeping a single `scholarship-contracts` link gated by `view_contracts`.
- [x] Step 2 — `navigation/horizontal/crm.ts`: dropped `children`, single link labelled `common.navigation.crm` to `visitor`.
- [x] Step 3 — new `navigation/horizontal/applications.ts` (single link → `applications`), registered in `navigation/horizontal/index.ts`.
- [x] Step 4 — `components/erp/ErpPageHeader.vue`: per-link `icon` + `color` typed via `HeaderSection`/`HeaderLink`, an `applications` section (applications / search / visa-reports), and buttons that render the icon.
- [x] Step 5 — `components/erp/workspace.scss`: rounded, weighted sub-navigation buttons with a hover lift, a filled+shadowed active state, and a subtle themed container.
- [x] Step 6 — `pages/applicants.vue`: `section="applications"`, `:breadcrumbs="false"` removed.
- [x] Step 7 — `pages/applicant-pipeline.vue`: `section="applications"`.
- [x] Step 8 — `pages/search.vue`: wrapped in `erp-workspace` with `ErpPageHeader` (applications section).
- [x] Step 9 — `pages/educational-reports.vue`: header replaced by `ErpPageHeader` + its actions slot; the namespaced print rule that hid `.education-page-header` now hides `.erp-page-header`.
- [x] Step 10 — `erp.applications` added to `en`, `fa-AF`, `ps-AF`.
- [x] Step 11 — `scripts/applications-tabs-regression.mjs` updated to the new design and hardened (real app theme, icon/colour assertions).
- [x] Step 12 — `scripts/concept-navigation-regression.mjs` added; `scripts/service-contract-regression.mjs` extended to compile the two newly headed pages.

### 7. Validation plan and results

- [x] `npm run typecheck` (`vue-tsc --noEmit` via `scripts/typecheck.mjs`) — exit 0.
- [x] `node scripts/concept-navigation-regression.mjs` — exit 0; five negative controls failed as required (see §9). It also proves the rendered menu is duplicate-free (33 links, 33 unique destinations).
- [x] `node scripts/applications-tabs-regression.mjs` — exit 0 in real Chrome for `en` and `fa-AF`, light and dark, desktop and mobile.
- [x] `npm run build:icons` — exit 0, 838 files scanned, 292 references, 290 tabler icons bundled, no unresolved-token warning.
- [x] `npm run i18n:check` — exit 0 (275 source files, 3071 used keys, no missing keys).
- [x] `npm run i18n:test` — exit 0 (en, fa-AF, ps-AF; 4459 keys, parity and schema verified).
- [x] `node scripts/service-contract-regression.mjs` — exit 0 (SFC compile + `erp` locale parity).
- [x] `node scripts/educational-ui-regression.mjs` — exit 0 (compiles `service-contracts.vue` with the new header).
- [x] `eslint` over the changed files — could not be used as a gate: the install's import resolver is broken (`unrs-resolver` native binding missing), so `import/extensions` and `import/no-unresolved` fire on every import in every file and the run takes ~4 minutes for a single file. The one genuine issue it reported in new code (the section map placed above `defineProps`) was fixed and everything was re-validated. See §9.
- [x] Re-ran after the `ErpPageHeader` reorder: `npm run typecheck` → exit 0; `node scripts/concept-navigation-regression.mjs` → exit 0; `node scripts/applications-tabs-regression.mjs` → exit 0; `node scripts/service-contract-regression.mjs` → exit 0; `npm run build:icons` → exit 0 (292 references, unchanged output).
- Checks not run and why: `php artisan test` / Pint — no backend file changed. No browser mount of the global navigation (no layout/nav harness exists, and the dev server needs a seeded backend); the global links are verified at module level instead.

### 8. Risks and mitigations

- Removing the Contract group's children could orphan pages — mitigation: AC3 plus a regression assertion that each concept page is reachable exactly once and every in-page destination is a real route.
- A class-based icon absent from the bundle renders blank — mitigation: `build:icons` reports unresolved tokens, both new names were confirmed in `@iconify-json/tabler`, and the browser test asserts the rendered `tabler-*` classes.
- `educational-reports.vue` print CSS targeted `.education-page-header`; after the swap that selector would no longer hide the header on Ctrl+P — mitigated in the same edit (selector renamed, still namespaced by `body.printing-education-reports`).
- Rewriting a passing regression test could weaken it — mitigation: only the assertion *direction* changed (the requested behaviour); every other assertion in that script is untouched, and the harness was strengthened with the app's real theme.
- `tertiary` is not defined in Vuetify's built-in light theme; the harness now loads the app theme so the rendered colours are realistic.

### 9. Progress log

- 2026-10-14 — DISCOVERY: read the nav barrel and all horizontal nav modules, `ErpPageHeader.vue` + `workspace.scss`, the Contracts/CRM/Applications/Search/Visa-reports pages, the vuetify theme, the i18n keys and the existing regression scripts; used `graphify query` to confirm the `ErpPageHeader` consumer set. Found the `applications-tabs-regression.mjs` assertions that conflict with the request.
- 2026-10-14 — CLARIFIED: user chose an own top-level Applications link and the CRM label.
- 2026-10-14 — PLAN: plan saved before implementation.
- 2026-10-14 — PROGRESS: nav converted to single concept links (`contracts.ts`, `crm.ts`, new `applications.ts`, barrel); `ErpPageHeader` gained per-link icon/colour and the applications section; the sub-navigation styling added.
- 2026-10-14 — PROGRESS: `applicants.vue` (breadcrumbs on), `applicant-pipeline.vue`, `search.vue` (new header) and `educational-reports.vue` (header swap + print selector) brought onto the shared design; `erp.applications` added to the three locales.
- 2026-10-14 — PROGRESS: `applications-tabs-regression.mjs` assertions flipped to the new design; it is now mounted with the application's real Vuetify theme.
- 2026-10-14 — PROGRESS: added `concept-navigation-regression.mjs`. Its first draft had two bugs found by running it (a wrong path prefix and a wrong expectation that `search`/`visa-reports` appear in the global nav); both fixed.
- 2026-10-14 — VALIDATION: `npm run build:icons` → 838 files, 292 references, 290 tabler icons, no unresolved tokens. `npm run typecheck` → exit 0.
- 2026-10-14 — VALIDATION: `applications-tabs-regression.mjs` initially failed twice for the right reasons — the icon token extraction split on the wrong character (an escaping bug inside the generated harness) and the CRM `tertiary` colour needed the app theme in the harness. After both fixes → exit 0.
- 2026-10-14 — VALIDATION: `concept-navigation-regression.mjs` → exit 0. Negative controls: (A) reverting `contracts.ts` to a group → exit 1; (B) removing one destination's colour → exit 1 (`every in-page destination needs its own colour`); (C) nesting the Applications link in a group → exit 1 (`applications must be a single link, not a dropdown group`). Files restored and re-verified after each.
- 2026-10-14 — VALIDATION: `npm run i18n:check` → exit 0 (3071 used keys, none missing). `npm run i18n:test` → exit 0. `service-contract-regression.mjs` and `educational-ui-regression.mjs` → exit 0.
- 2026-10-14 — VALIDATION: `eslint` (combined run over the eleven changed files) finished with 940 problems, but the overwhelming majority are environmental: `import/extensions` + `import/no-unresolved` "Resolve error: Cannot find native binding" (`unrs-resolver`), which fire on every import including untouched ones (e.g. all ten imports of the 20-line nav barrel), plus pre-existing `padding-line-between-statements` debt (44 errors in the untouched body of `applicants.vue`). Real finding: `vue/define-macros-order` — my section map sat above `defineProps`. Fixed by moving `defineProps`/`useI18n`/`useRoute` back above the section map and trimming field comments, then re-running every affected check (all exit 0).
- 2026-10-14 — VALIDATION: re-ran after the `ErpPageHeader` reorder — `npm run typecheck`, `concept-navigation-regression.mjs`, `applications-tabs-regression.mjs`, `service-contract-regression.mjs`, `npm run build:icons` all exit 0.
- 2026-10-14 — PROGRESS: removed an `eslint-disable-line n/prefer-global/buffer` suppression I had briefly added to the new script (the repository's existing scripts use `Buffer.from` without one, and suppressing a lint rule to quiet output is the wrong trade); the check still exits 0.
- 2026-10-14 — PROGRESS: added a whole-menu guard (`no page may appear twice in the global navigation`). Verified the rendered navigation is now duplicate-free — 33 links, 33 unique destinations — which satisfies "for each concept only one link appears" beyond the three concepts in scope. Negative controls: (D) adding a second `visitor` link → exit 1; (E) duplicating the `tasks` page (not in the per-concept table) → exit 1 (`no page may appear twice in the global navigation: [["tasks",2]]`). Files restored and re-verified.

### 10. Completion summary

- Delivered:
  - Global navigation: Contracts, CRM and Applications are single links (no dropdown groups), each pointing at its default page (`scholarship-contracts`, `visitor`, `applications`).
  - `ErpPageHeader` now owns one in-page navigation per concept, with a declared icon and colour per destination, and renders them as icon+label buttons; the current page is filled, shadowed and marked `aria-current="page"`.
  - Applications, Search and Visa reports share that header (breadcrumbs + the Applications strip), matching the Visitors/Contracts pages.
  - `erp.applications` breadcrumb key added to en/fa-AF/ps-AF.
  - Two regression scripts updated/added, plus the SFC compile list extension.
- Acceptance criteria: AC1–AC8 satisfied.
- Tests/checks passed: `npm run typecheck`; `npm run build:icons`; `npm run i18n:check`; `npm run i18n:test`; `concept-navigation-regression.mjs` (+5 negative controls, and the rendered menu is duplicate-free: 33 links / 33 unique); `applications-tabs-regression.mjs` (Chrome, en/fa-AF, light/dark, mobile); `service-contract-regression.mjs`; `educational-ui-regression.mjs`.
- Remaining limitations / follow-up work:
  - `eslint` could not be used as a clean gate: this install's import resolver is broken (missing `unrs-resolver` native binding), so `import/extensions`/`import/no-unresolved` fail on every import in the repository, and the run is extremely slow on this filesystem (~4 minutes for one file). The single genuine finding in the new code was fixed; the remaining reported problems are pre-existing or environmental. `eslint` is not part of the project's documented verification (`npm run typecheck`).
  - The global navigation was verified at module level (the real nav modules are imported and asserted) and via `vue-tsc` template compilation, not in a live browser: there is no layout/navigation harness and the dev server needs a seeded backend. The rendered link behaviour uses the same leaf shape as the existing `home.ts` link.
  - Only the Applications page has a browser mount (`applications-tabs-regression.mjs`). Search and Visa reports are covered by SFC parse + script/template compilation (`service-contract-regression.mjs`) and by `vue-tsc` template type-checking, but neither was rendered in Chrome this session — so their breadcrumb/strip layout at real breakpoints is unverified visually.
  - `navigation/horizontal/visitor.ts` remains as unused dead code; `erp.applicants` remains as an unused locale key.
  - HR and Setup were intentionally left alone: the request named the Contracts and CRM groups, and the whole menu is now duplicate-free anyway, so no further regrouping was needed.
