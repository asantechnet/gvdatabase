## Task: Reusable table states and consistent form controls

- **Task ID:** reusable-table-states-and-form-controls
- **Created:** 2026-10-25
- **Last updated:** 2026-10-25
- **Status:** COMPLETED
- **Outcome:** Provide reusable wave-table-skeleton and table-empty-state building blocks, plus consistent solid primary buttons and shared outlined form-field treatment without prematurely changing existing table states.

### 1. Requirements and acceptance criteria

- [x] A reusable, accessible table-loading component renders a five-column, five-row semantic table with wave-animated header and cell placeholders.
- [x] The table-loading component is responsive, theme-aware, RTL-safe, and disables its animation for reduced-motion users.
- [x] A reusable empty-table state offers a configurable bundled icon, optional title, required contextual description, and a generous responsive minimum height.
- [x] Neither new table-state component is wired into an existing page or table in this task.
- [x] Default primary `VBtn` instances render as solid primary actions, use the theme's `on-primary` foreground token, and retain an intentional hover/active treatment. Explicit non-primary/text/outlined variants remain under caller control.
- [x] Standard Vuetify text-like field families retain one outlined/comfortable shared baseline, have consistent inline padding at desktop/mobile widths, use the configured primary colour for focus, and have no focus shadow.
- [x] Type checking, icon generation, and a production build pass; the final diff has no whitespace errors.

### 2. Scope and non-goals

- In scope: two new presentational components under `resources/ts/components`, Vuetify default props, global field/button styling, and this plan/progress record.
- Out of scope: installing either table-state component in existing tables, rewriting every individual field template, changing APIs/data loading, changing translations, changing generated icon CSS, or modifying unrelated in-progress theme/settings work.

### 3. Project findings

- Current behavior: `VDataTable` and `VDataTableServer` are globally replaced by the responsive wrappers in `resources/ts/plugins/vuetify/index.ts`. Their desktop body delegates loading/no-data handling to Vuetify; mobile cards currently display a compact textual fallback. The request explicitly defers replacement, so this integration remains untouched.
- Relevant architecture and conventions: `unplugin-vue-components` auto-imports descendants of `resources/ts/components`; Vuetify global defaults live in `resources/ts/plugins/vuetify/defaults.ts`; component-level Vuetify SCSS is imported from `resources/styles/@core/template/libs/vuetify/components/index.scss`.
- Relevant paths/files: `resources/ts/components/responsive-data-table/responsiveDataTable.ts`, `resources/ts/plugins/vuetify/defaults.ts`, `resources/styles/@core/template/libs/vuetify/components/_field.scss`, `resources/styles/@core/template/libs/vuetify/components/_button.scss`, and `resources/ts/plugins/vuetify/theme.ts`.
- Constraints: use bundled static `tabler-*` icon names only; do not hand-edit generated icon CSS. Preserve RTL logical properties, theme CSS tokens, explicit caller variants/densities, and the existing dirty settings/theme worktree files.

### 4. Graph and impact review

- Graph skills/tools or fallback methods actually used: no graph skill is available; used `rg` reference search and manual import/call tracing.
- Dependencies, callers, data flows, schemas, and integrations examined: `main.ts` -> registered plugins -> Vuetify plugin -> global `VDataTable` aliases and defaults; SCSS import chain -> `components/index.scss` -> field/button styles; searches found 93 Vue/TS files using standard text-like field components and 119 using `VBtn`.
- Potential downstream effects and affected tests: changing defaults/style affects all standard Vuetify controls, including page filters and dialog forms; explicit field density/variant props remain intentional local exceptions. `on-primary` comes from the active light/dark palette, including setup-driven palette updates, so button foreground adapts without hard-coded colours.
- Tool/review limitations: static review cannot replace authenticated light/dark, RTL/LTR, mobile, and focus-state browser inspection.

### 5. Clarifications, decisions, and assumptions

- Open questions: none material after project review.
- User decisions: create the two table primitives now, but do not use them anywhere yet; improve all standard primary controls at their shared parent rather than one page at a time.
- Assumptions and rationale: “five tables” is implemented as a conventional table skeleton with five header columns and five body rows; this gives future tables a credible, configurable default without imposing their data shape. “Use one reusable input field” is best served by the existing shared Vuetify field family/default layer, which already covers text fields, selects, autocompletes, comboboxes, file inputs, and textareas. Replacing 93 templates with a wrapper would alter public field/slot behaviour and is unnecessary for visual consistency.
- Alternatives considered: wiring skeleton/no-data slots into `ResponsiveDataTable` would instantly change all tables, directly contradicting the deferred-use requirement. A hard-coded button text colour would fail user-configured palettes, so the theme `on-primary` token is retained.

### 6. Implementation plan

- [x] Step 1 — Add `TableLoadingSkeleton.vue` with configurable column/row counts (default five each), native table semantics, screen-reader loading text, wave placeholders in `th`/`td`, responsive overflow, CSS theme tokens, and reduced-motion handling.
- [x] Step 2 — Add `TableEmptyState.vue` with an icon prop/default, optional title, required contextual description, configurable minimum height, and responsive centered layout. Do not add call sites.
- [x] Step 3 — Set the global `VBtn` default variant to flat so default primary actions receive a solid primary surface while explicit variants retain their declared behavior; refine the shared hover treatment only as necessary.
- [x] Step 4 — Consolidate the existing shared outlined-field treatment around the global Vuetify field families: keep comfortable density/default props, apply consistent logical inline padding, primary focus border without elevation/shadow, and compact mobile padding.
- [x] Step 5 — Update this record with implementation outcomes; run `npm run typecheck`, `npm run build:icons`, `npm run build`, and `git diff --check`; review the final scoped diff.

### 7. Validation plan and results

- [x] `npm run typecheck` — passed (exit 0).
- [x] `npm run build:icons` — passed: 891 files scanned, 294 references found; 292 Tabler and 2 MDI icons bundled, with no unavailable-icon warnings.
- [x] `npm run build` — passed: 1,345 client modules and 82 service-worker modules transformed; injectManifest produced 246 precache entries.
- [x] `git diff --check` — passed (exit 0; only pre-existing CRLF warnings in unrelated files).
- [x] Source review — no existing page/table imports or renders `TableLoadingSkeleton` or `TableEmptyState`; global styles/defaults are the only existing UI surfaces changed.
- Results: all planned automated checks passed. The production build refreshed committed `public/build`, `public/sw.js`, and `public/version.json` as required by the project convention.
- Checks not run and why: authenticated browser visual checks were not run because no local authenticated application session was supplied; retain a light/dark, RTL/LTR, mobile/desktop visual pass before release.

### 8. Risks and mitigations

- Global visual change can affect dense filters — mitigation: retain existing explicit density/variant overrides and limit shared changes to common text-like fields.
- User-configured theme palettes may differ from static theme values — mitigation: use Vuetify CSS variables and `on-primary`, never a hard-coded foreground/background.
- Table skeleton can overwhelm narrow layouts — mitigation: semantic table is wrapped in responsive horizontal overflow and default cell widths can shrink.
- Future empty states need context-specific wording — mitigation: description is supplied by the caller instead of embedding an untranslated generic message.

### 9. Progress log

- 2026-10-25 — DISCOVERY: Inspected repository status and protected unrelated dirty settings/theme changes. Traced the responsive global table wrapper, Vuetify defaults, component auto-import settings, and shared field/button SCSS.
- 2026-10-25 — DISCOVERY: Confirmed no graph skill is available; completed fallback `rg` dependency/caller searches. Found the table wrapper is globally registered, which makes immediate integration incompatible with the requested deferred rollout.
- 2026-10-25 — PLAN: Saved the complete implementation and validation plan before code edits.
- 2026-10-25 — PROGRESS: Added standalone `TableLoadingSkeleton` (five columns/rows by default, semantic `table`, wave cells, responsive overflow, and reduced-motion support) and `TableEmptyState` (context supplied by future callers). Search confirms neither is rendered by an existing page/table.
- 2026-10-25 — PROGRESS: Made default primary buttons flat/solid at the Vuetify parent and explicitly consume `on-primary`. Centralized outlined-field inline padding; replaced the focus elevation mixin with a two-pixel, primary-coloured border and no shadow. Existing explicit variants, errors, success fields, and local densities remain intact.
- 2026-10-25 — VALIDATION: `npm run typecheck` passed (exit 0).
- 2026-10-25 — VALIDATION: `npm run build:icons` passed; `tabler-database-off` was accepted and generated icon CSS refreshed without an unavailable-token warning.
- 2026-10-25 — VALIDATION: `npm run build` passed; Vite compiled the client and service worker, then refreshed generated PWA assets as expected.
- 2026-10-25 — VALIDATION: `git diff --check` passed. Manual source review confirms no premature table-state rollout.
- 2026-10-25 — COMPLETE: All requested shared primitives and parent-layer styling changes are implemented. Browser appearance verification remains the stated follow-up only.

### 10. Completion summary

- Delivered: standalone `TableLoadingSkeleton` and `TableEmptyState`; global solid primary-button defaults; global outlined field spacing and non-shadow primary focus treatment.
- Acceptance criteria satisfied/not satisfied: all requested criteria satisfied. Existing tables intentionally remain unchanged for the later, page-by-page rollout.
- Tests/checks passed: `npm run typecheck`, `npm run build:icons`, `npm run build`, and `git diff --check`.
- Remaining limitations or follow-up work: future table/page-specific rollout will choose the appropriate loading/empty-state props and pass translated content; perform a live authenticated visual check across themes, directions, and responsive breakpoints before release.
