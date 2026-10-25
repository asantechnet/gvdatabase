## Task: Applications workspace and applicant-pipeline UI refinement

- **Task ID:** applications-workspace-and-pipeline-ui
- **Created:** 2026-10-25
- **Last updated:** 2026-10-25
- **Status:** COMPLETED
- **Outcome:** Make Applications and its case workspace cleaner, mobile-ready, theme-safe, and printable; move pipeline filters behind the shared filter menu and match its loading state to the board cards.

### 1. Requirements and acceptance criteria

- [x] Every Applications stage tab has a responsive list with a reusable loading skeleton, reusable icon-and-description empty state, and a final actions column/card action sheet.
- [x] The list no longer displays Applicant with a primary-colour treatment, File, or Details columns; file and service-detail context is available in the opened applicant case.
- [x] The Applications print action prints a clean, isolated, localized A4-landscape report rather than the live page chrome.
- [x] Opening a case presents related information in distinct, readable sections; Timeline, Tasks, University applications, Finance, and Documents have padded theme-safe surfaces.
- [x] Applicant Documents does not use the primary-colour background and remains legible in light and dark themes.
- [x] Applicant Pipeline keeps its main canvas clear: filters live in the shared filter dropdown, its skeleton matches the pipeline columns/cards, and controls remain responsive and accessible.
- [x] RTL/LTR, light/dark, mobile/desktop, permissions, drag/move behaviour, existing service/stage filtering, and print isolation are preserved by source/build validation; browser visual execution remains environment-blocked.

### 2. Scope and non-goals

- In scope: Applications list/table rendering and print host, applicant-case information architecture and styling, Applicant Pipeline filter/loading UI, translations only if a required label is missing, regression coverage, and this progress record.
- Out of scope: changing applicant API contracts, pipeline stage/transition rules, attachment storage, permissions, database schema, or the existing CRM Applicants page.

### 3. Project findings

- Current behavior: `pages/applicants.vue` uses a manually authored `VTable` for every service/stage tab. It shows File and Details cells, colours the applicant CRM link primary, has a text-only empty row, and calls `printDocument()` without a `.list-print-report` source. That makes the isolated print helper fall back to printing the live page.
- Current behavior: `pages/applicant-case.vue` already loads applicant attachments separately and exposes service details through `guardianSummary`, but its workflow, timeline, tasks, university applications, finance, documents, and notes are rendered as one long stack. Applicant Documents is a primary-tonal card.
- Current behavior: `pages/applicant-pipeline.vue` renders all filter fields above the board and only an indeterminate progress bar while loading. Each normal board column contains tonal applicant cards.
- Relevant architecture and conventions: `ResponsiveDataTableServer` supplies the existing reusable mobile-card layout, including a terminal actions sheet; `TableEmptyState` and `TableLoadingSkeleton` are the current reusable table states; `DataTableFilterMenu` is the shared popover filter control; `PrintableListReport` is the supported isolated report source; `printDocument` must receive a namespaced body class and page style.
- Relevant paths/files: `resources/ts/pages/applicants.vue`, `resources/ts/pages/applicant-case.vue`, `resources/ts/pages/applicant-pipeline.vue`, `resources/ts/components/{PrintableListReport,TableEmptyState,TableLoadingSkeleton}.vue`, `resources/ts/components/responsive-data-table/{ResponsiveDataTableServer,DataTableFilterMenu}.vue`, `resources/ts/utils/printDocument.ts`, `scripts/applications-tabs-regression.mjs`.
- Constraints: static routes already send Open Case to `applicant-case`; preserve it. Use logical CSS and Vuetify theme variables. Do not change generated icon CSS. The worktree includes unrelated in-progress settings/theme and generated-build changes that must remain untouched.

### 4. Graph and impact review

- Graph skills/tools or fallback methods actually used: no graph skill is available; used `rg` reference searches plus manual route/component/API/print-helper tracing.
- Dependencies, callers, data flows, schemas, and integrations examined: Applications and Pipeline both call `ApplicantApi` and route to the static `applicant-case` route; the list's service/stage/query filters feed `ApplicantApi.list`; the pipeline's filters feed `ApplicantApi.pipeline`; case attachment calls use `ApplicantAttachmentApi`; the print helper clones `PrintableListReport` only for `printing-table-*` print jobs; the responsive table uses its `mobile-action-key` to preserve end-of-row controls in a mobile action sheet.
- Potential downstream effects and affected tests: the applications stage-tab regression compiles the real page and needs new component stubs for the responsive table/report/states. The responsive-table migration preserves server pagination via `update:options`; visible report rows remain the loaded filtered page, matching the page's existing server pagination.
- Tool/review limitations: no authenticated browser session or production data is available; source/type/build and the existing headless regression are the available automated validation.

### 5. Clarifications, decisions, and assumptions

- Open questions: none material after repository review.
- User decisions: use the existing reusable empty state; use the pipeline's cards as the visual source for its loading state; put list-only File and Details context into the applicant details/case experience; hide pipeline filters behind a filter icon.
- Assumptions and rationale: “Portal Test Applicant details” is interpreted as the applicant's opened case/details workspace. The existing case owns both attachment management and service-specific details, while there is no component or translation with that exact title. A concise overview/details section will surface the list's former service-summary context alongside the existing Applicant Documents section.
- Assumptions and rationale: the requested clean print view means an isolated `PrintableListReport` based on the current filtered server page, not a new unpaginated export API. This fixes the current page-chrome print fault without silently changing report scope or fetching potentially large result sets.
- Alternatives considered: keeping the handwritten table and adding breakpoints would duplicate the mature mobile-card/action-sheet logic already used elsewhere; use `ResponsiveDataTableServer` instead. A primary-tonal Document card conflicts with the requested theme-derived background, so use theme surface/on-surface tokens rather than a hard-coded colour.

### 6. Implementation plan

- [x] Step 1 — Record the repository/route/component/print dependency findings and save this complete plan before source edits.
- [x] Step 2 — Migrate `pages/applicants.vue` to `ResponsiveDataTableServer`, with table empty/loading slots, translated headers, mobile title/actions configuration, and one `update:options` fetch path. Remove File and Details columns, remove primary applicant link styling, retain relationship/Open Case actions last, and add a `PrintableListReport` with clean visible-row values.
- [x] Step 3 — Reorganize `pages/applicant-case.vue` into a responsive overview plus named content sections. Add the list's service summary/details to the overview, retain attachment operations in Applicant Documents, and give Timeline, Tasks, University applications, Finance, Documents, and Notes consistent padded section surfaces. Replace the document primary background with theme-safe surface styling.
- [x] Step 4 — Move Applicant Pipeline controls into `DataTableFilterMenu`, keep service selection accessible in that menu, add active-filter feedback/reset/apply controls, and replace the progress-only loading UI with a card/column skeleton that follows the board geometry.
- [x] Step 5 — Update the applications stage-tab browser regression for the new rendering dependencies; perform focused source review for the rendered Applications/case/pipeline markers where browser execution is unavailable.
- [x] Step 6 — Run the focused regression, `npm run typecheck`, `npm run build:icons`, `npm run build`, and `git diff --check`; update this record with actual results and review each acceptance criterion.

### 7. Validation plan and results

- [!] `node scripts/applications-tabs-regression.mjs` — environment-blocked: Chrome could not open its local DevTools socket (`crashpad setsockopt: Operation not permitted`) in either sandboxed or approved execution. The harness was updated for the current theme's `semanticThemeColors` dependency and new Applications imports; `node --check scripts/applications-tabs-regression.mjs` passed.
- [x] `npm run typecheck` — passed (exit 0).
- [x] `npm run build:icons` — passed: 892 files scanned; 294 references found; 292 Tabler and 2 MDI icons bundled without unavailable-token warnings.
- [x] `npm run build` — passed: 1,354 client modules and 82 service-worker modules transformed; injectManifest generated 248 precache entries.
- [x] `git diff --check` — passed (exit 0; Git printed only pre-existing CRLF normalization warnings for unrelated files).
- [x] Manual/source review — verified no File/Details headers remain in Applications; responsive table has `mobile-cards` and `mobile-action-key`; print is namespaced as `printing-table-applicants` and has a `PrintableListReport` source; the case uses logical properties/theme variables; pipeline keeps drag/move and permission guards unchanged.
- Results: all build/type/static source checks passed. Browser regression remains blocked only by the Chrome runtime restriction.
- Checks not run and why: live authenticated visual verification was unavailable, and the focused headless regression could not start Chrome in this environment.

### 8. Risks and mitigations

- Responsive table migration could duplicate loads on pagination — mitigation: retain the existing explicit page/per-page fetch state and use one `update:options` handler.
- Print report can drift from on-screen columns — mitigation: derive report columns and row values from the same retained table data and omit the intentionally removed action/File/Details data.
- Section restyling could hide permission-gated actions or alter task/file behaviour — mitigation: move existing blocks intact, retaining each condition and handler.
- Pipeline filter menu can become tall on mobile — mitigation: reuse `DataTableFilterMenu`'s capped scrollable card and only keep filter controls inside it.
- Existing dirty working tree could be unintentionally included — mitigation: limit patches to the listed Applications files, focused regression, and this task record.

### 9. Progress log

- 2026-10-25 — DISCOVERY: Inspected repository state and found substantial unrelated settings/theme/PWA build work; it will be preserved. Read project instructions and the Plan-Implement workflow.
- 2026-10-25 — DISCOVERY: Traced Applications -> `ApplicantApi.list` -> static `applicant-case` route, Pipeline -> `ApplicantApi.pipeline`, case attachments, reusable states, responsive table mobile actions, and isolated print flow. Confirmed no graph skill is available and used repository-search/manual tracing instead.
- 2026-10-25 — PLAN: Saved the implementation plan before making feature edits. Chose the existing case workspace as the location for the table's former File and Details context, based on the current attachment and service-detail ownership.
- 2026-10-25 — PROGRESS: Replaced the handwritten Applications table with `ResponsiveDataTableServer`; added its reusable loading/empty slots, mobile card/action configuration, a final actions field, clean column values, and `PrintableListReport` for the A4 landscape isolated print path. Removed File and Details columns and the primary-coloured applicant CRM link.
- 2026-10-25 — PROGRESS: Redesigned the opened case into profile/details and service-workflow overview sections plus padded Timeline, Tasks, University applications, Finance, Documents, and Notes surfaces. Applicant service details now live in the profile area; attachments moved into the theme-safe Documents section, replacing the former primary-tonal attachment card.
- 2026-10-25 — PROGRESS: Moved every Applicant Pipeline filter into `DataTableFilterMenu`, kept apply/reset semantics, treated the mandatory service as board scope rather than an active-filter badge, and added `ApplicantPipelineSkeleton.vue`, matching the board column/card geometry and reduced-motion behaviour.
- 2026-10-25 — PROGRESS: Updated the focused Applications regression harness with the new page dependencies and theme dependency loader. The source syntax check passes; Chrome startup is environment-blocked before the harness can navigate.
- 2026-10-25 — VALIDATION: `npm run typecheck` passed (exit 0); `npm run build:icons` passed (892 files / 294 references / no unavailable icons); `npm run build` passed (1,354 client modules; 248 PWA precache entries); `git diff --check` passed. Generated build/PWA files were refreshed as the project requires.
- 2026-10-25 — COMPLETE: All requested Applications, case workspace, and pipeline UI changes are implemented. The only validation limitation is headless Chrome's environment-level socket restriction.

### 10. Completion summary

- Delivered: responsive Applications list/print report; organized applicant-case workspace; filter-menu pipeline plus matching board skeleton; focused regression-harness compatibility update.
- Acceptance criteria satisfied/not satisfied: all implementation criteria are satisfied. Browser visual execution is not confirmed because Chrome cannot start in this execution environment.
- Tests/checks passed: `npm run typecheck`, `npm run build:icons`, `npm run build`, `git diff --check`, and `node --check scripts/applications-tabs-regression.mjs`.
- Remaining limitations or follow-up work: run `node scripts/applications-tabs-regression.mjs` on a host where headless Chrome can initialize, then perform an authenticated light/dark, RTL/LTR, desktop/mobile visual pass and a physical/print-preview check of the Applications report.
