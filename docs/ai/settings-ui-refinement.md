## Task: Settings navigation, artwork, office, support, and appearance refinement

- **Task ID:** settings-ui-refinement
- **Created:** 2026-10-25
- **Last updated:** 2026-10-25
- **Status:** PARTIALLY COMPLETED
- **Outcome:** A quieter, responsive settings experience with compact document-artwork controls, balanced Office fields, focused Support copy, and fixed reusable semantic theme colours.

### 1. Requirements and acceptance criteria

- [x] Work is performed on a feature branch created from the local `main` branch, without overwriting existing work.
- [x] Desktop settings navigation shows section names/icons only; each section description remains visible only in the active-section heading.
- [x] On mobile, the settings sections are selected through an accessible menu while preserving the existing route-per-section URLs and Office permission visibility.
- [x] Documents artwork controls (contract, receipt, and payslip) retain upload/removal, scoped artwork, preview, and safe-area functionality while using compact, responsive previews with clear spacing.
- [x] Office profile fields use balanced responsive columns. The useful profile-completion status appears below the fields rather than beside them.
- [x] The Support form no longer shows the severity-help sentence, including the default “The right choice for most questions and issues.” text.
- [x] Appearance no longer exposes configurable feedback/status colours. Success, info, warning, and error tokens use reusable fixed code constants, never company settings or legacy browser-storage values.
- [x] Appearance reset restores dark background `#111313` and dark cards/menus `#1A1B1B`; backend defaults and initial Vuetify themes agree.
- [ ] Full browser and database-backed validation could not run because this environment has neither a running MySQL service nor the SQLite driver, and its Playwright daemon exits at startup. Source-level and frontend integration checks passed.

### 2. Scope and non-goals

- In scope: the Setup → Settings shell, document-artwork presentation, embedded Office and Support panels, configurable theme contract, theme boot/persistence behavior, targeted tests, and this progress record.
- Out of scope: changing persisted contract artwork or safe-area data, print layout/paper sizes, Office geofence behavior, creating an additional office model, changing brand/primary-secondary colour customisation, or dropping legacy database columns containing obsolete status-color preferences.

### 3. Project findings

- Current behavior: `resources/ts/pages/setup/settings.vue` owns the route section and desktop/sidebar layout. It repeats descriptions in every `VListItem`, and the active heading already renders the needed description. Office and Support are embedded components with their own saves/APIs.
- Current behavior: all four artwork editors use portrait previews capped at 360px wide; each includes a safe-area preview and fields. The behavior must remain intact while compacting their presentation.
- Current behavior: `OfficeSettingsPanel.vue` uses asymmetric 8/4 columns for name/code and a profile-completion chip as a third 4-column field. The chip is a meaningful indicator based on name, code, and address.
- Current behavior: Support renders `severityHelp` under the category selector, not the severity selector; its normal translation is the requested sentence.
- Current behavior: semantic color preferences travel through `ThemePalette`, request validation, setting resource/model, API types/client validation, local storage, startup theming, branding refresh, and appearance UI. No other feature reads those settings directly.
- Relevant architecture and conventions: static route `/setup/settings/:section?` is defined in `resources/ts/plugins/1.router/routes.ts`; settings API responses unwrap under `response.data.data`; theme color application is centralized in `resources/ts/utils/themePersistence.ts`; `npm run typecheck` is mandatory instead of bare TypeScript commands.
- Relevant paths/files: `resources/ts/pages/setup/settings.vue`, `resources/ts/components/setup/OfficeSettingsPanel.vue`, `resources/ts/pages/support.vue`, `resources/ts/utils/themePersistence.ts`, `resources/ts/plugins/vuetify/{index.ts,theme.ts}`, `resources/ts/composables/useBranding.ts`, `app/Support/ThemePalette.php`, setting API request/resource/model/contracts, tests, and locale catalogs.
- Constraints: preserve RTL through Vuetify/logical CSS, both themes, mobile/tablet layouts, upload validation, and all document safe-area interactions. Do not hand-edit generated icon CSS. No document printing CSS is involved.

### 4. Graph and impact review

- Graph skills/tools or fallback methods actually used: no graph skill is available; used `rg` call/reference searches plus manual import/data-flow tracing.
- Dependencies, callers, data flows, schemas, and integrations examined: static router → Settings section state → embedded Office/Support; Settings form → API contract → request validation/controller → resource → branding/startup palette; `themePersistence` is called by Vuetify startup, settings live preview/save, and branding refresh.
- Potential downstream effects and affected tests: changing the configurable palette requires synchronizing the PHP default provider, resource, request behavior, frontend API types/client validation, storage keys, theme application, and Company Settings tests. Artwork UI changes must leave contract document rendering and its existing print regression untouched.
- Tool/review limitations: static tracing does not substitute for authenticated browser visual checks or testing physical touch resizing on a mobile device.

### 5. Clarifications, decisions, and assumptions

- Open questions: none material after repository review.
- User decisions: status colours are not company preferences; dark reset colours are `#111313` (application background) and `#1A1B1B` (cards/menus).
- Assumptions and rationale: “mobile menu navigation” means a compact accessible Vuetify menu whose options retain existing route links. “Compact and intact” means lowering bounded visual preview dimensions only, not removing previews, uploads, safe zones, source selection, or scope behavior.
- Decision: keep old status-color columns physically present to avoid data-loss migrations, but remove them from fillable/validated/resource/client settings contracts. Fixed tokens live in one TypeScript reusable constant and are applied on every theme initialization/refresh, so stale server values and old local-storage values cannot affect the application.
- Decision: retain the profile-completion chip and move it to a full-width final row because it communicates useful profile readiness without competing with input fields.

### 6. Implementation plan

- [x] Step 1 — Update the settings navigation shell in `settings.vue`: remove list-item descriptions, add a mobile menu nav, and preserve route/permission behavior and active heading descriptions.
- [x] Step 2 — Refine `settings.vue` document-artwork markup/styles: use consistently spaced responsive editor cards and smaller bounded previews while keeping existing artwork/safe-area control bindings untouched.
- [x] Step 3 — Balance Office profile layout in `OfficeSettingsPanel.vue`, retaining completion state in a final full-width row; remove the Support severity-help rendering and now-unused computation.
- [x] Step 4 — Make semantic status tokens fixed/reusable: reduce company-adjustable palette fields end-to-end, centralize the hard-coded semantic colors in the theme utility, ensure startup/branding resets them, and set requested dark defaults in PHP/frontend/Vuetify definitions.
- [x] Step 5 — Update focused tests/validation metadata and localization only where the removed runtime references require it; preserve unrelated legacy translations/db columns.
- [x] Step 6 — Run targeted settings validation and PHP tests, then `npm run typecheck`, relevant i18n/icon/build checks as feasible; inspect the final diff and record real results.

### 7. Validation plan and results

- [x] `npm run test:settings-validation` — passed with added assertions for responsive navigation, compact artwork, Office layout, removed helper copy, and fixed semantic colors.
- [ ] Focused Laravel Company Settings tests — blocked: configured MySQL is unavailable; an isolated SQLite run is also unavailable because the PHP SQLite driver is not installed.
- [x] `npm run typecheck` — passed.
- [x] `npm run i18n:check` — passed; it reports one pre-existing/concurrent likely-untranslated string in `TableLoadingSkeleton.vue` and unused-key counts.
- [x] `npm run build:icons` — passed: 891 files scanned, 294 icon references, no unavailable icon warnings.
- [x] `npm run build` — passed and regenerated committed build/service-worker/version output.
- [x] Manual source review — confirmed preserved route, permission, artwork upload/safe-area bindings, logical CSS, and no print CSS changes. Browser review was attempted but blocked by a Playwright daemon startup failure.
- Results: Vue SFC parsing, targeted validation, PHP syntax, JSON parsing, typecheck, i18n audit, icon generation, production build, and `git diff --check` passed. Database feature tests and browser interaction checks are blocked as noted.
- Checks not run and why: authenticated browser interaction cannot launch in this environment; database-backed tests cannot connect to local MySQL and have no SQLite driver fallback.

### 8. Risks and mitigations

- Responsive artwork previews could make safe-area controls harder to use — mitigation: retain portrait aspect ratio, pointer handlers, bounded full-width layout, and four numeric controls.
- Old persisted palettes could still override fixed semantic colors — mitigation: remove their fields from storage reads/writes and apply the reusable constants unconditionally.
- Removing API fields can affect older clients — mitigation: legacy columns remain; server ignores deprecated writes and resource omits them so new clients have one source of truth.
- Mobile navigation can hide currently selected context — mitigation: label its trigger with the active section and keep the same routed links.
- Theme changes can create inaccessible foregrounds — mitigation: retain the existing contrast calculation for editable primary/secondary/surface colors and provide explicit dark on-colors for fixed status tokens.

### 9. Progress log

- 2026-10-25 — DISCOVERY: Inspected repository status and created `settings-ui-refinement` from local `main` (which was three commits ahead of `origin/main`). Reviewed settings, Office, Support, routes, API/resource/request/model, palette, Vuetify startup, branding refresh, tests, scripts, locales, and prior settings record.
- 2026-10-25 — DISCOVERY: Traced the status-color data flow manually with `rg`; no graph tool is available. Confirmed the Office completion chip is useful and that the requested Support sentence is the normal severity-help translation rendered below the category input.
- 2026-10-25 — PLAN: Full plan finalized and saved before feature implementation. No material ambiguity remains.
- 2026-10-25 — PROGRESS: Began implementation by creating a reusable fixed semantic-color module, removing semantic colors from the adjustable palette, and aligning dark surface defaults across the PHP palette and initial Vuetify definitions.
- 2026-10-25 — PROGRESS: Replaced mobile settings sidebar navigation with a current-section menu; desktop list descriptions were removed while active-heading descriptions remain. Wrapped contract/receipt/payslip artwork editors in responsive cards with 180–260px bounded previews; upload, scoped-editor, source-selection and safe-area bindings are unchanged.
- 2026-10-25 — PROGRESS: Balanced Office fields into equal responsive pairs, moved the completion chip to its own final row, removed Support severity-help rendering/translations, localized the Office heading description, and strengthened the settings validation script with UI/theme regression assertions.
- 2026-10-25 — VALIDATION: `npm run test:settings-validation` passed twice (including new regression assertions); PHP syntax and locale JSON parsing passed; `npm run typecheck`, `npm run i18n:check`, `npm run build:icons`, `npm run build`, and `git diff --check` passed. Icon build found 294 valid references and the production build regenerated committed artifacts.
- 2026-10-25 — VALIDATION LIMITATION: `php artisan test tests/Feature/CompanySettingsTest.php` could not connect to the configured MySQL service, including with sandbox escalation. `DB_CONNECTION=sqlite DB_DATABASE=:memory:` could not run because PDO SQLite is unavailable. `playwright-cli open http://localhost:8000` failed because its daemon exited with code 1.
- 2026-10-25 — WORKTREE NOTE: Unrelated concurrent table-state/form-control source changes and its `docs/ai/reusable-table-states-and-form-controls.md` record appeared during the production build. They were preserved without inspection or modification; generated production assets therefore reflect the complete current workspace.

### 10. Completion summary

- Delivered: responsive section menu navigation; concise desktop list navigation; compact artwork editor cards; balanced Office profile fields; simplified Support form; fixed semantic theme colors; requested dark surface defaults; API/theme contract cleanup; focused regression coverage.
- Acceptance criteria satisfied/not satisfied: all functional criteria are satisfied by source review and passing frontend checks. Live browser behavior and database-backed feature tests remain unverified because of the environment limitations above.
- Tests/checks passed: `npm run test:settings-validation`, PHP syntax checks, locale JSON parsing, `npm run typecheck`, `npm run i18n:check`, `npm run build:icons`, `npm run build`, and `git diff --check`.
- Remaining limitations or follow-up work: run `php artisan test tests/Feature/CompanySettingsTest.php` with an available test database and perform an authenticated mobile/desktop browser pass when Playwright can launch.
