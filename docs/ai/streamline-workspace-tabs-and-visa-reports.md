## Task: Streamline workspace tabs and Visa Reports states

- **Task ID:** streamline-workspace-tabs-and-visa-reports
- **Created:** 2026-10-25
- **Last updated:** 2026-10-25
- **Status:** PARTIALLY COMPLETED
- **Outcome:** CRM, Contracts, Applications, and Visa Reports use one clear loading and empty-state treatment, workspace tabs keep mobile scrolling without a visible scrollbar track, and the obsolete Applications Search feature is removed end to end.

### 1. Requirements and acceptance criteria

- [x] CRM, Contracts, and Applications workspace list surfaces do not display a progress/loader in addition to their skeleton while data is loading.
- [x] Their empty states use the shared icon-and-description component where the page has an empty list/table result.
- [x] All current tab strips remain horizontally usable on narrow screens but do not render an unattractive scrollbar line beneath them.
- [x] The Applications Search tab, `/search` frontend route/page/API client, `/api/search` endpoint/controller, and feature-only tests are removed; ordinary in-page search and applicant picker search stay intact.
- [x] Every Visa Reports domain/type (Educational & CRM, Finance, HRM) displays a reusable responsive skeleton during loading and the reusable empty state when there are no report rows.
- [x] Visa Reports filters move from the expanded table/header section to the standard dropdown filter menu used on other list pages.
- [x] The Visa Reports Applicants status cell renders an appropriately coloured chip/badge.
- [~] The frontend type check passes; PHP feature tests are environment-blocked because this workspace cannot connect to its configured MySQL server.

### 2. Scope and non-goals

- In scope: shared workspace tab styling; CRM/Contracts/Applications list-state cleanup; deletion of the isolated cross-module Search feature; Visa Reports presentation/filter improvements; related tests and task documentation.
- Out of scope: changing the remaining per-page search parameters, applicant picker lookup, report API schema, report calculations, permissions, or mobile tab scrolling behavior.

### 3. Project findings

- Current behavior:
  - `ErpPageHeader.vue` supplies the CRM, Contracts, and Applications workspace navigation. Its Applications links include the obsolete `search` route.
  - `workspace.scss` intentionally sets `overflow-x: auto` on the workspace navigation but also gives it a visible thin scrollbar. Vuetify `VTabs` uses an internal `VSlideGroup`, which can likewise expose a bottom scrollbar.
  - CRM Visitors already chooses either `TableLoadingSkeleton` or `TableEmptyState`; CRM Applicants, service contracts, cancelled contracts, and several related current-list surfaces still use table/progress loaders or plain no-data copy.
  - Applications list and pipeline already use skeletons. Visa Reports instead shows a linear loader, an inline custom empty block, and an in-card expandable filters section.
  - `TableLoadingSkeleton.vue`, `TableEmptyState.vue`, and `DataTableFilterMenu.vue` are reusable responsive components. The filter menu provides the established dropdown pattern.
  - `pages/search.vue`, `api/search.ts`, the static `search` route, `SearchController`, `GET /api/search`, `SearchTest`, and one SearchController-dependent service-contract test comprise the obsolete Search feature. Repository search found no other consumer of that endpoint/client. Applicant/contract list search and income applicant picker use their own APIs and must remain.
  - The generic Visa Reports table serves Applicants, Contracts, Services, Universities, Visa Outcomes, Finance, and HRM report types; changing its render branches covers all report-domain tabs.
- Relevant architecture and conventions:
  - Vue 3 / TypeScript frontend with static routes in `resources/ts/plugins/1.router/routes.ts`; API response unwrapping stays in API contracts.
  - Laravel 12 APIs and feature tests; `npm run typecheck` is the only permitted TypeScript check command.
  - UI uses Vuetify and `vue-toastification`; both RTL/LTR and light/dark themes are required.
- Relevant paths/files:
  - `resources/ts/components/erp/ErpPageHeader.vue`, `resources/ts/components/erp/workspace.scss`, `resources/styles/@core/template/libs/vuetify/components/_tabs.scss`
  - `resources/ts/pages/{visitor,all_customers,service-contracts,cancelled_contracts,applicants,applicant-pipeline,educational-reports}.vue`
  - `resources/ts/components/{TableLoadingSkeleton,TableEmptyState}.vue`, `resources/ts/components/responsive-data-table/DataTableFilterMenu.vue`
  - `resources/ts/plugins/1.router/routes.ts`, `resources/ts/pages/search.vue`, `resources/ts/api/search.ts`
  - `app/Http/Controllers/SearchController.php`, `routes/api.php`, `tests/Feature/{SearchTest,ServiceContractNavigationTest}.php`

### 4. Graph and impact review

- Graph skills/tools or fallback methods actually used: no graph skill is installed; used `rg` call/reference searches plus direct review of static routes, API controller/route, navigation, page templates, reusable table components, and backend feature tests.
- Dependencies, callers, data flows, schemas, and integrations examined:
  - Applications header link → static `search` route → `pages/search.vue` → `api/search.ts` → `GET /api/search` → `SearchController`; the API is referenced by `SearchTest` and one test method in `ServiceContractNavigationTest` only.
  - `ErpPageHeader` → `.erp-subnav` styling and `VTabs` → internal `VSlideGroup`; styling must hide the scrollbar without changing overflow-x.
  - Visa Reports tab/domain selection → `ReportApi.get()` → generic report response/table; a type-specific Applicants cell branch can add a status chip without API changes.
  - Current CRM/Contracts/Applications workspace pages → shared loading/empty components; changes remain presentation-only and preserve each page's query/API data flow.
- Potential downstream effects and affected tests:
  - Deleting `/api/search` requires deleting its test coverage and removing the dependent test method, while retaining contracts test coverage that does not call search.
  - The tab selector is globally loaded from Vuetify's tabs stylesheet; selectors are limited to `VTabs`/`VSlideGroup` internals so normal scroll containers are unaffected.
  - Visa reports need mobile-friendly skeleton/table wrapping and semantic status-colour fallback for unexpected status values.
- Tool/review limitations: no browser runner/graph package was available during discovery; visual layout will be validated by static responsive markup/CSS review plus available build/type checks.

### 5. Clarifications, decisions, and assumptions

- Open questions: none material after repository review.
- User decisions: implement on local `main`; remove the Search tab and its frontend/backend logic completely; retain horizontal tab scrolling but remove the visible bottom line.
- Assumptions and rationale:
  - “Home / Applications Search” refers to the Applications workspace Search destination (`/search`), not the unrelated in-page filtering/search controls. This is supported by the isolated route/controller/client dependency chain.
  - “All current tabs” means the current workspace navigation and all native `VTabs` instances: shared styling will hide scrollbar tracks globally for tab slide groups while preserving scrolling; list state changes are limited to the CRM/Contracts/Applications workspace data views.
  - Existing shared skeleton and empty components are the requested reusable components; no replacement library/component is needed.
- Alternatives considered:
  - Removing `overflow-x` would eliminate the line but break narrow-screen tab reachability, so it is rejected.
  - Retaining the Search API for undocumented clients contradicts the requested complete frontend/backend removal and no in-repo consumer exists, so it is rejected.

### 6. Implementation plan

- [x] Step 1 — inspect branch/worktree, workspace navigation, tab owners, Search route/API dependency chain, report data flow, reusable states, and test commands; switch from the prior feature branch to requested `main`.
- [x] Step 2 — update workspace/tab CSS to keep horizontal navigation scrollable but hide browser scrollbar tracks for workspace navigation and Vuetify tab slide groups, including RTL-compatible behavior.
- [x] Step 3 — normalize CRM, Contracts, and Applications current-list loading/empty rendering to a single skeleton or shared empty state at a time; remove redundant progress/table loader usage without affecting mutation button loading states.
- [x] Step 4 — delete the obsolete Applications Search navigation link, static route/component registration, page/API client, Laravel endpoint/controller/import, and feature-only tests; remove only translation/text references that become unreachable.
- [x] Step 5 — refactor Visa Reports to use `DataTableFilterMenu`, `TableLoadingSkeleton`, and `TableEmptyState`; make the generic table wrapper responsive and replace the linear loader/inline empty block.
- [x] Step 6 — add report-type-aware Applicants status chip colours while retaining raw display treatment for all other report values and robust handling of unknown/missing statuses.
- [~] Step 7 — updated this record, inspected the final diff, passed `npm run typecheck` and PHP syntax checks; focused feature tests could not boot without MySQL.

### 7. Validation plan and results

- [~] `php artisan test tests/Feature/EducationalReportTest.php tests/Feature/EducationalOperationalReportingTest.php tests/Feature/ServiceContractNavigationTest.php` — blocked before tests began by `SQLSTATE[HY000] [2002]` connecting to configured MySQL at `127.0.0.1:3306`.
- [x] Repository reference search confirms no stale `SearchController`, `/api/search`, Search API client, static Search route, or Search page references remain.
- [x] `npm run typecheck` — passed (the npm configuration warnings did not affect the command).
- [x] `npm run build:icons` — passed; scanned 889 files and generated the required `tabler-file-off` bundled icon.
- [x] PHP syntax: `php -l routes/api.php`, `php -l tests/Feature/ServiceContractNavigationTest.php`, and `php -l app/Http/Controllers/EducationalReportController.php` — passed.
- [x] Static responsive/theme review: preserved `overflow-x: auto`, used logical/viewport-safe shared components, and uses Vuetify theme CSS variables/chips.
- Results: frontend type and icon checks passed; PHP test execution is environment-blocked.
- Checks not run and why: the focused feature suite needs the configured MySQL service, which is unavailable in this environment.

### 8. Risks and mitigations

- Hiding scrollbars could accidentally remove scrolling — mitigation: retain `overflow-x: auto` and target only scrollbar rendering pseudo-elements/properties.
- Search removal could delete unrelated lookup behavior — mitigation: remove only the isolated `SearchController` dependency chain; preserve `/api/applicants?search` and `/api/incomes/applicants?search`.
- A generic report table change can affect every domain — mitigation: gate only Applicants status-cell formatting and retain the generic column/value renderer elsewhere.
- Report filtering must still be usable on small screens — mitigation: reuse `DataTableFilterMenu`, whose card constrains its width/height to the viewport.

### 9. Progress log

- 2026-10-25 — DISCOVERY: Read the project instructions and Plan-Implement instructions; inspected the clean worktree, branch, static routes, workspace header/CSS, all native tab references, CRM/Contracts/Applications page states, Search dependency chain, reusable state/filter components, Visa Reports flow, and related PHP tests. No graph skill was available; documented repository-search fallback.
- 2026-10-25 — DECISION: Switched to requested local `main` (ahead of origin by six pre-existing commits). Treated `/search` as the user-requested Applications Search feature and will preserve unrelated list/picker searching.
- 2026-10-25 — PLAN: Saved the complete implementation/validation plan before feature edits.
- 2026-10-25 — PROGRESS: Hid workspace and all Vuetify tab scrollbar tracks without removing overflow; changed contract lists and the Applications pipeline to shared skeleton/empty states; removed the Applications Search link, page, route, API client, endpoint/controller, and dedicated tests; and refactored Visa Reports to the shared filter dropdown, responsive skeleton/empty state, and applicants-only status chips.
- 2026-10-25 — VALIDATION: `npm run typecheck` passed. `npm run build:icons` passed and generated the new static icon. PHP syntax checks passed. Exact focused `php artisan test` command was attempted but blocked before test execution by an unavailable configured MySQL server. `git diff --check` passed and exact stale Search-feature reference search returned no matches.

### 10. Completion summary

- Delivered: tab overflow polish, unified CRM/Contracts/Applications and Visa Reports state rendering, Search-feature retirement, dropdown report filters, and Applicants status chips.
- Acceptance criteria satisfied/not satisfied: all requested code changes are implemented; PHP feature-test execution remains unverified in this environment.
- Tests/checks passed: `npm run typecheck`, `npm run build:icons`, PHP syntax checks, stale-reference search, and `git diff --check`.
- Remaining limitations or follow-up work: rerun the focused Laravel feature suite with MySQL available.
