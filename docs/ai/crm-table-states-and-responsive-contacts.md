## Task: CRM table states, responsive actions, and contact refinements

- **Task ID:** crm-table-states-and-responsive-contacts
- **Created:** 2026-10-25
- **Last updated:** 2026-10-25
- **Status:** COMPLETED
- **Outcome:** Visitors, Follow-up, and CRM Applicants present reusable loading and empty states, retain usable logical-end actions and readable layouts on phones, and use restrained quick-contact and relationship-note styling.

### 1. Requirements and acceptance criteria

- [x] The Visitors and Follow-up modes use `TableLoadingSkeleton` while list data loads and `TableEmptyState` (icon plus description) when their respective lists have no rows.
- [x] CRM Applicants (`/all-customers`) uses the same reusable loading and empty states; its duplicate card title/icon is removed and its search/actions toolbar distributes search from filter/print controls with RTL-safe logical layout.
- [x] The Add New Visitor form removes the four requested persistent helper descriptions without changing validation, the counselor lock, or the notes helper.
- [x] All three CRM lists place the Actions heading and content at logical inline end on desktop and are fully usable without horizontal scrolling on phones, with compact but readable mobile spacing.
- [x] Follow-up and CRM Applicant phone/email quick contacts remain expandable, add an email-copy action, and render as primary text only—without tonal/hover backgrounds.
- [x] Applicant relationship notes use a neutral bordered surface rather than a primary-toned panel.
- [x] The work remains light/dark and RTL/LTR compatible, preserves existing API/data behavior, and passes relevant static validation.

### 2. Scope and non-goals

- In scope: CRM Visitor/Follow-up and Applicant list presentation, the existing reusable table-state components, responsive table action rendering, shared quick-contact appearance/actions, the relationship-details notes card, locale strings for copying email, and this record.
- Out of scope: CRM API/model/schema changes, route changes, visitor/applicant business rules, changing unrelated tables, redesigning the relationship dialog beyond its notes card, or rebuilding generated icon CSS.

### 3. Project findings

- Current behavior: `visitor.vue` serves both Visitors and Follow-up according to `?list=follow-up`; it uses native data-table loading and contains the requested Add New Visitor hints. `all_customers.vue` is the CRM Applicants destination and has the duplicate title/icon, a combined toolbar, and native server-table loading/no-data output.
- Relevant architecture and conventions: `TableLoadingSkeleton.vue` and `TableEmptyState.vue` are existing, accessible presentational components. `ResponsiveDataTable`/`ResponsiveDataTableServer` are the globally registered wrappers. Their `mobileCards` mode eliminates dense table grids on narrow viewports and presents Actions in a bottom sheet. `ContactActionMenu.vue` is shared by visitor, applicant, applicant-case, and staff surfaces. `CrmRelationshipDetails.vue` owns the Relationship notes panel.
- Relevant paths/files: `resources/ts/pages/visitor.vue`, `resources/ts/pages/all_customers.vue`, `resources/ts/components/TableLoadingSkeleton.vue`, `resources/ts/components/TableEmptyState.vue`, `resources/ts/components/responsive-data-table/responsiveDataTable.ts`, `resources/styles/styles.scss`, `resources/ts/components/ContactActionMenu.vue`, `resources/ts/components/crm/CrmRelationshipDetails.vue`, and the three locale JSON files.
- Constraints: actions must use logical CSS alignment for RTL; use `npm run typecheck` rather than bare TypeScript tools; preserve existing dirty settings/theme and generated-build changes; generated icon CSS is not edited manually.

### 4. Graph and impact review

- Graph skills/tools or fallback methods actually used: no graph skill is available; used repository `rg` searches plus manual caller/data-flow tracing.
- Dependencies, callers, data flows, schemas, and integrations examined: CRM navigation and static route map -> `/visitor` and `/all-customers`; `visitor.vue` -> `VisitorApi.getAll` plus shared headers/actions; `all_customers.vue` -> `ApplicantApi.list`/server table; responsive table -> global Vuetify table alias and mobile action-sheet implementation; quick-contact menu callers across visitor/applicant/staff pages; relationship-details callers across all CRM/applicant surfaces. Existing visitor regression coverage and locale commands were inspected as validation precedents.
- Potential downstream effects and affected tests: generic contact trigger styling/copy action affects every existing quick-contact caller, while compact mobile-card/action handling benefits any future opt-in list. No request/API payload changes are needed. Existing Visitor follow-up regression is the closest focused behavioral check; translation parity checks cover new locale keys.
- Tool/review limitations: static and harness checks cannot replace a signed-in manual pass across light/dark, LTR/RTL, and real phone viewport interaction.

### 5. Clarifications, decisions, and assumptions

- Open questions: none material after tracing the named UI elements.
- User decisions: use the existing reusable skeleton and icon/description empty state; remove only the named helper text from the Add New Visitor dialog; keep quick contacts expandable but make their row triggers text-only; remove the applicant relationship-notes primary background.
- Assumptions and rationale: “Applicants inside CRM” means `/all-customers`, because it is the CRM route containing the exact duplicate Applicants card title described. Mobile cards are the established responsive-table capability and remove the need to horizontally scroll dense CRM tables. The shared quick-contact parent is the intended location for consistent phone/email trigger styling; its email menu will retain compose-email and add the explicitly requested Copy email action.
- Alternatives considered: keeping wide tables with hidden scrollbars would still require horizontal gesture scrolling and leave dense cells hard to read; card mode is already supported by the project wrapper. Styling individual page contact cells would leave the same shared menu inconsistent in other CRM contexts.

### 6. Implementation plan

- [x] Step 1 — Made `TableLoadingSkeleton` compact at phone widths, then rendered the existing skeleton/empty-state components around the Visitor and Follow-up list. Visitor fetch and form/action save state are separate, so saving no longer replaces a dialog's underlying table with a skeleton.
- [x] Step 2 — Enabled responsive cards for Visitors, Follow-up, and CRM Applicants; added shared mobile-card spacing/overflow rules and preserved relationship details plus menu-based actions in the phone Actions sheet.
- [x] Step 3 — Set all relevant Actions headers/cells to logical inline end; removed the four requested Add New Visitor persistent hints without changing edit-form hints or validation.
- [x] Step 4 — Refined `/all-customers`: removed its duplicate card heading, distributed search and filter/print controls with a responsive logical toolbar, and supplied reusable loading/empty table slots without unmounting the server table during paging.
- [x] Step 5 — Updated `ContactActionMenu` with text-only/no-hover-background triggers and an accessible clipboard Copy email menu item; added English, Persian, and Pashto strings.
- [x] Step 6 — Rendered Relationship notes in a neutral bordered panel and retained the existing textarea/save behavior.
- [x] Step 7 — Updated this record with outcomes; ran the available checks and reviewed the scoped diff.

### 7. Validation plan and results

- [x] `npm run typecheck` — passed (exit 0) after all source edits.
- [x] `npm run i18n:check` — passed (exit 0; 299 source files, 3,210 used keys). The reusable skeleton's fallback label was moved to `common.messages.loading`, fixing its pre-existing hard-coded English audit warning.
- [!] `npm run i18n:test` — failed before this task's keys were reached because the pre-existing `ps-AF:documents.payslip.workedDays` value does not preserve its interpolation placeholders.
- [!] `node scripts/visitor-follow-up-regression.mjs` — its syntax check passed, but its browser run fails before mounting the Visitor page: unrelated current theme code combines CommonJS `require` and top-level `await` under Node 24 (`ERR_AMBIGUOUS_MODULE_SYNTAX`). The harness includes mocks for the newly imported table-state components.
- [x] `npm run build:icons` — passed; 891 files scanned, 294 icon references found, 292 Tabler and 2 MDI icons bundled with no unavailable-icon warnings.
- [x] `npm run build` — passed; transformed 1,351 modules and produced the PWA service worker with 248 precache entries. Generated `public/build`, `public/sw.js`, and `public/version.json` were refreshed according to project convention.
- [x] `git diff --check` — passed for the task's tracked source/doc paths.
- [x] Manual source review — confirmed the acceptance criteria, API preservation, logical CSS alignment, and page-scoped print behavior.
- Results: all source/build checks relevant to this change passed. The two failed checks are blocked by unrelated existing localization/theme-test defects.
- Checks not run and why: no authenticated visual browser session was available; perform a live light/dark, LTR/RTL, and narrow-phone visual pass before release.

### 8. Risks and mitigations

- Mobile-card action sheet can omit non-button components — mitigation: teach the shared action collector to preserve relationship-detail and menu actions, with an explicit mobile rendering mode for relationship details.
- Shared contact styling can affect non-CRM callers — mitigation: keep the interaction menu, labels, and contact logging behavior intact; only remove the trigger surface/hover treatment and add copying.
- Table state replacement could blank the screen during a dialog save — mitigation: distinguish list-fetch state from mutation saving state on Visitors.
- Compact mobile layout can make text difficult to scan — mitigation: remove horizontal overflow, retain label/value fields, give cards bounded padding and field separators, and reduce only surplus wrapper/table-cell gaps.

### 9. Progress log

- 2026-10-25 — DISCOVERY: Inspected repository status and found unrelated in-progress settings/theme and generated-build edits; they will remain untouched. Read project instructions and the Plan-Implement workflow.
- 2026-10-25 — DISCOVERY: Traced `/visitor` (Visitors + Follow-up) and `/all-customers` (CRM Applicants), their APIs, static routes, responsive-table wrapper, quick-contact parent, relationship dialog, existing reusable table-state components, localization structure, styles, and the Visitor regression harness.
- 2026-10-25 — DISCOVERY: No graph skill is available; completed the documented `rg`/manual dependency and caller review instead. Confirmed the CRM Applicant page is distinct from the operational Applications page.
- 2026-10-25 — PLAN: Saved this full implementation and validation plan before editing product code. No material clarification is required.
- 2026-10-25 — PROGRESS: Added Visitor/Follow-up skeleton and icon/description empty states, split fetching from saving state, enabled no-scroll mobile cards, and applied logical-end Actions alignment. Removed only the named Add New Visitor hints.
- 2026-10-25 — PROGRESS: Refined CRM Applicants with its reusable state slots, removed the duplicate card heading, added the distributed toolbar, and kept server-table pagination mounted while loading to avoid an update-options remount loop.
- 2026-10-25 — PROGRESS: Made shared quick-contact row triggers text-only, added Copy email with clipboard fallback/toast feedback and three locale entries, and changed the shared Relationship notes card to neutral bordered styling. Extended the responsive action collector so relationship details and existing menu actions remain usable on mobile.
- 2026-10-25 — VALIDATION: `npm run typecheck`, `npm run i18n:check`, `npm run build:icons`, `npm run build`, `node --check scripts/visitor-follow-up-regression.mjs`, and task-scoped `git diff --check` passed.
- 2026-10-25 — VALIDATION: `npm run i18n:test` remains blocked by the unrelated `ps-AF:documents.payslip.workedDays` interpolation mismatch. The Visitor browser regression remains blocked before page mount by an unrelated Node 24 CommonJS/top-level-await error in the current theme dependency; the harness was updated for this page's new component imports.
- 2026-10-25 — COMPLETE: Implementation is complete; no API, route, schema, or print behavior was changed. Pre-existing working-tree changes outside this task were preserved.

### 10. Completion summary

- Delivered: reusable skeleton and empty states on all requested CRM lists; compact no-scroll mobile cards with preserved Actions; logical-end action columns; simplified Add New Visitor helpers; CRM Applicant title/toolbar cleanup; text-only phone/email menus with Copy email; and neutral Relationship notes styling.
- Acceptance criteria satisfied/not satisfied: all requested implementation criteria are satisfied by source/build review. Live authenticated visual verification remains the only recommended follow-up.
- Tests/checks passed: `npm run typecheck`, `npm run i18n:check`, `npm run build:icons`, `npm run build`, `node --check scripts/visitor-follow-up-regression.mjs`, and task-scoped `git diff --check`.
- Remaining limitations or follow-up work: repair the independent Pashto interpolation mismatch and theme/harness Node-24 module-format issue before relying on the corresponding test commands; manually verify authenticated light/dark, RTL/LTR, and narrow-phone interactions.
