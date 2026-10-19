## Task: Fix RTL layout flipping upside-down and other miscompiled `:global()` scoped selectors

- **Task ID:** fix-rtl-global-selector-scope-leaks
- **Created:** 2026-10-19
- **Last updated:** 2026-10-19
- **Status:** COMPLETED
- **Outcome:** Switching to Dari/Pashto no longer rotates the application 180°, and every frontend rule that was silently reduced to its `:global(...)` prefix is corrected so RTL and print styling apply to the intended elements.

### 1. Requirements and acceptance criteria
- [x] `/agency-overview` renders normally (not upside-down) in Dari/Pashto, both immediately and after visiting other pages.
- [x] The priority-link arrow still mirrors to point left in RTL.
- [x] Sweep all `.vue` style blocks for `:global(...) <descendant>` misuse; no rule may silently attach its declarations to the `:global()` element itself.
- [x] Timesheet and contract print styles target their intended elements (`.no-print`, `.a4-page`, `.contract-page-container`, cover sections, etc.), not `body`.
- [x] `npm run typecheck` passes and the production build emits the corrected selectors.

### 2. Scope and non-goals
- In scope: `resources/ts/pages/educational-dashboard.vue`, `resources/ts/pages/templates/timesheet_template.vue`, `resources/ts/pages/templates/contract_page_template.vue`, `resources/ts/pages/templates/contract_cover_template.vue`.
- Out of scope: redesigning RTL layouts, changing print workflows, adding new lint tooling, unrelated refactors.

### 3. Project findings
- `App.vue` wraps the routed application in `<VLocaleProvider :rtl="configStore.isAppRTL">`, so Vuetify renders a `.v-locale--is-rtl` root element around every page.
- `educational-dashboard.vue` line 166 contained:
  `:global(.v-locale--is-rtl) .priority-link :deep(.v-icon) { transform: rotate(180deg); }`
- Vue SFC scoped-style compilation **drops every selector after a `:global(...)` block**; verified with `@vue/compiler-sfc`:
  - `:global(A) B :deep(C)` → `A { ... }`
  - `:global(body.x) *` → `body.x { ... }`
- The built asset `public/build/assets/educational-dashboard-*.css` contains `.v-locale--is-rtl{transform:rotate(180deg)}`, which rotates the whole app root 180° in RTL. Lazy-loaded CSS persists after leaving the dashboard, so the flip can follow the user to other routes.
- Additional occurrences of the same pattern (24 selectors total):
  - `timesheet_template.vue` (5 rules) — compiled to `body:is(.printing-timesheet, ...)` rules, including `display:none` on `body`; print-specific hiding/sizing never reached `.no-print` / `.a4-page`.
  - `contract_page_template.vue` (14 rules) — all compiled onto `body.printing-contract-document`.
  - `contract_cover_template.vue` (4 rules) — all compiled onto `body.printing-contract-document`.
- Legitimate `:global(...)` usages ending at the closing parenthesis (account picker, viewer container, collapsed nav) are unaffected.

### 4. Graph and impact review
- Fallback used: repository-wide ripgrep searches plus a custom Node selector scanner over `resources/ts`, plus direct `@vue/compiler-sfc` compilation of the suspect selectors. No graph index (`graphify-out/`) exists in this repository.
- Impact: global `.v-locale--is-rtl` rotation affects every route once the dashboard chunk CSS is loaded; print rules affect timesheet, contract page and contract cover printouts.
- Limitations: no browser-authenticated end-to-end run of the SPA; verification relies on compiler output, emitted build CSS and typecheck.

### 5. Clarifications, decisions, and assumptions
- Open questions: none material.
- User decisions: none required; the defect and intended behavior are unambiguous.
- Assumptions:
  - RTL detection via `html[dir='rtl']` is reliable because `initCore.ts` sets it for Persian-script locales and `configStore` keeps it in sync.
  - Keeping `:global()` is correct for print rules because printable content can live in `#app-print-host` outside normal scoping.

### 6. Implementation plan
- [x] Replace the dashboard RTL rule with a single `:global(html[dir='rtl'] .agency-dashboard .priority-link .v-icon)` selector.
- [x] Rewrite the five timesheet print rules as single `:global(body:is(...) <descendant>)` selectors.
- [x] Rewrite the fourteen contract page print rules as single `:global(body.printing-contract-document <descendant>)` selectors, merging sibling selector groups.
- [x] Rewrite the four contract cover print rules the same way.
- [x] Rebuild and inspect the emitted CSS for the corrected selectors and absence of the leaked `.v-locale--is-rtl` rotation.

### 7. Validation plan and results
- [x] `npm run typecheck` — run: exit 0, no diagnostics.
- [x] `npm run build` — run: exit 0; emitted `html[dir=rtl] .agency-dashboard .priority-link .v-icon{transform:rotate(180deg)}`, no `.v-locale--is-rtl{transform:rotate(180deg)}` remains, timesheet/contract print selectors now target their real elements.
- [x] Selector scanner over `resources/ts` — zero trailing-selector `:global()` usages remain.
- [x] Headless Chrome check of emitted dashboard CSS — RTL: root transform `none`, icon `matrix(-1, 0, 0, -1, 0, 0)`; LTR: root `none`, icon `none`.
- [x] Headless Chrome check of emitted timesheet print CSS (print media forced to screen) — `.no-print` `display:none`, `.a4-page` width `793.688px` (210mm), body `display:block`.
- [x] Headless Chrome check of emitted contract print CSS (print media forced to screen) — `.contract-page-container` `793.688px × 1122.52px` (210×297mm), `.content-wrapper` `display:flex`, body width unaffected.
- [x] ESLint on the four changed files — could not complete; the command hung past 180 s and was stopped. Typecheck and build both parsed the changed files successfully.
- Checks not run and why: no automated CSS/unit suite exists in this repository; browser checks used the emitted production CSS in headless Chrome instead of an authenticated SPA session.

### 8. Risks and mitigations
- Print CSS behavior changes from "wrong but present" to "correct and scoped" — mitigation: preserve declaration values exactly, only fix selector structure, and inspect emitted CSS.
- `html[dir='rtl']` vs `.v-locale--is-rtl` mismatch — mitigation: `initCore.ts` sets the document `dir` attribute for Persian-script locales and `_setDirAttr` mirrors the store; verify in emitted CSS.

### 9. Progress log
- 2026-10-19 — DISCOVERY: Reproduced the bug from `public/build/assets/educational-dashboard-*.css` (`.v-locale--is-rtl{transform:rotate(180deg)}`); confirmed Vue's scoped compiler drops trailing selectors after `:global(...)`; scanned the whole frontend and found 24 affected selectors in 4 files.
- 2026-10-19 — PLAN: Plan saved before implementation.
- 2026-10-19 — PROGRESS: Fixed all 24 miscompiled selectors in `educational-dashboard.vue`, `timesheet_template.vue`, `contract_page_template.vue`, `contract_cover_template.vue`; rebuilt committed `public/build` assets, `public/sw.js` and `public/version.json`.
- 2026-10-19 — VALIDATION: `npm run typecheck` exit 0; `npm run build` exit 0; scanner clean; headless Chrome confirmed RTL root not transformed and print selectors applied to their intended elements.

### 10. Completion summary
- Delivered: RTL page rotation removed and all frontend `:global(...)`-prefixed scoped selectors corrected so RTL/print declarations reach their intended elements.
- Acceptance criteria satisfied/not satisfied: all satisfied.
- Tests/checks passed: `npm run typecheck`, `npm run build`, selector scan, headless Chrome RTL/LTR and print-selector checks.
- Remaining limitations or follow-up work: ESLint on the changed files timed out; no browser run against an authenticated backend session. `public/build` was regenerated and is part of the working-tree change, consistent with the repository's committed-build convention.
