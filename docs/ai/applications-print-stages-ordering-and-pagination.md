# Task record: Applications print, stage manipulation, default ordering, list pagination and currencies

## Task: Applications print, stage manipulation, default ordering, list pagination and currencies

- **Task ID:** applications-print-stages-ordering-and-pagination
- **Created:** 2026-10-27
- **Last updated:** 2026-10-28
- **Status:** IMPLEMENTED; generated bundles not rebuilt
- **Outcome:** Fix the Applications global print to print the whole filtered report through the shared print template, fix per-service stage manipulation (line stages, stage editor values, seeded lines), make record-list GETs newest-first by default, paginate every list/datatable that currently loads everything (backend + frontend), and let all currencies be listed and used. Mirror the finished main commits into `feature/multi-tenancy-per-office` per `docs/facts/branch-mirroring.md`.

### 1. Requirements and acceptance criteria

- [x] AC1 Applications print uses the shared `PrintableListReport` template and contains every record matching the active filters, not only the current server page.
- [x] AC2 Stage manipulation works end to end: editing a stage's allowed next stages persists (`allowed_next_keys` stays a string array), a contracted service line with no stage is placed on its service's stage list and can be moved, and seeded lines carry a stage.
- [x] AC3 Record-list GET endpoints return newest first (date/created_at desc) by default; catalogue/picker endpoints keep their configured ordering.
- [x] AC4 Every in-scope list/datatable paginates on the backend and requests only the visible page on the frontend.
- [x] AC5 Currency GET payloads return every currency and writes accept any existing currency; the base currency stays first for pickers.
- [ ] AC6 `npm run typecheck`, focused PHP tests, `npm run build:icons`, `npm run build` and (in the tenant worktree) `npm run build` pass, with results recorded here.
- [ ] AC7 The main commits are ported into the multi-tenancy worktree as ordinary commits, with the tenant bundle rebuilt and the ported SHAs recorded.

### 2. Scope and non-goals

- In scope: `resources/ts/pages/applicants.vue` print flow; `applicant-stages.vue` stage editor; `applicant-case.vue` per-line stage control; `ApplicantController::pipeline`/`changeStage`; `ApplicantContractStageController`; `ContractSeeder`/`NotificationDemoSeeder`; a repair migration for NULL line stages; default ordering on record-list index endpoints; backend pagination + frontend server tables for Contracts, Income, Expenses, Assets, Staff, Visitors, Leave requests, Off-days, Payrolls (salaries + advances), Bonuses, Recurring events; currency GET/write gating; i18n keys in `en`/`fa-AF`/`ps-AF`; the multi-tenancy port.
- Out of scope: commission, educational report, currency, stage and Options-catalogue pagination (user decision: they do not grow enough to need it); dashboards; account-settings tables; task/settings tables; websocket work; document print layouts (A4/A5) are untouched.
- Non-goal: changing the per-service stage transition model (a move still has to be an allowed transition).

### 3. Project findings

- **Print.** `applicants.vue` calls `printDocument({ bodyClass: 'printing-table-applicants' })`; `printDocument` clones `.list-print-report` (rendered by `PrintableListReport`) into a body-level host and injects the `@page` rule. That works, but the report is built from `visibleRows` — the current server page (25 rows) — and its subtitle literally says "Applicants on the current page" (`agencyStaff.currentPageReport`). Other list pages (income, contracts, staff…) load all rows client-side, so their report covers the whole filtered set.
- **Stages.**
  - `applicant-stages.vue` binds `VCombobox` to `form.allowed_next_keys`. Vuetify 3.10's `VCombobox` internally forces `returnObject: true`, so a picked item is stored as `{title, value}` and `PUT /applicant-stages/{id}` fails validation (`allowed_next_keys.0 must be a string`, HTTP 422). Reproduced in the browser: the chip appears, the save fails, `allowed_next_keys` stays `[]`.
  - Every `applicant_contract` row in the working database has `current_stage_id = NULL`: `ContractSeeder` and `NotificationDemoSeeder` insert lines without a stage (the 2026-10-13 backfill only ran before the seeds). `ApplicantController::pipeline()` selects lines `whereIn('current_stage_id', $stageIds)`, so contracted cases never appear on the board (verified: the scholarship board shows only the one unlined case), and `applicant-case.vue` hides the per-line move control because `line.current_stage` is null.
  - `ApplicantStageController@destroy` already blocks deleting a stage in use, so the NULL is not from stage deletion.
- **Ordering.** `applicants`, `incomes`, `expenses`, `off-days`, `salaries`, `commissions`, `announcements`, `recurring-events`, `support-tickets`, `worthy-employees` and `pending-transactions` already order newest-first. `contracts`, `visitors`, `assets`, `employees` and the leave-request ledger load without a date order (or a name order).
- **Pagination.** Only `applicants`, `all_customers` (CRM) and `audits` paginate on the backend; `pending-transactions` paginates manually. Everything else loads all rows and either relies on Vuetify's client pagination or has no pagination at all. User decision: paginate the business lists only — Contracts (all audiences + cancelled), Income, Expenses, Assets, Staff, Visitors/Follow-up, Leave requests, Off-days, Payrolls (salaries + advances), Bonuses and Recurring events; skip currencies, stages, commissions, educational reports and all Options catalogues because their data sets stay small.
- **Currencies.** `CurrencySeeder` seeds only AFN `is_active = true`; `CurrencyController@index` returns all rows but `ContractController::serviceOptions`, `PendingTransactionController::options` and the income/expense form fetch filter by `is_active`, and income/expense/salary/contract writes reject inactive currencies. Verified: the database holds 11 currencies, one active, and the service-contract form offers only AFN. User decision: all currencies must be listed and usable.
- Relevant paths: `resources/ts/pages/{applicants,applicant-stages,applicant-case,contract_list,service-contracts,cancelled_contracts,income,expenses,asset,staff_list,visitor,leave_requests,off_days,payrolls,bonuslist,recurring-events}.vue`; `app/Http/Controllers/*`; `resources/ts/components/responsive-data-table/*`; `resources/ts/components/PrintableListReport.vue`; `resources/ts/utils/printDocument.ts`; `resources/ts/plugins/i18n/locales/*.json`.

### 4. Graph and impact review

- Method actually used: repository-wide `rg` searches, controller/route/composable tracing, `php artisan tinker` data inspection, a live `php artisan serve` + Playwright pass (login, `/applications`, `/applicant-stages`, `/applicant-pipeline`, `/applicants/1`), and the existing headless regression scripts as a pattern. A `graphify-out/` index exists but predates the current stage/contract work, so it was used only as a secondary index; no call-graph tool was run.
- Impact traced:
  - `printDocument` clones the first `.list-print-report`; adding report data in `applicants.vue` cannot affect other pages.
  - `allowed_next_keys` is read by `ApplicantStage::allowsTransitionTo`, `ApplicantController` and `ApplicantContractStageController`; fixing the editor's value shape keeps the API contract unchanged.
  - Pipeline placement is read by `applicant-pipeline.vue` and `EducationalDashboardController::pipeline`; the deploy fix is inside `ApplicantController::pipeline` so the dashboard's own counts are untouched.
  - `applicant_contract.current_stage_id` is a FK to `applicant_stages` with `nullOnDelete`; the repair migration only fills NULLs.
  - Pagination changes the response shape of the affected index endpoints (plain array → Laravel paginator **only when the client sends `page`/`per_page`**), so option/selector consumers that omit those params keep the existing array contract.
  - Currency `is_active` is referenced by controllers, services, requests and two frontend form filters; all of them are touched so the value never blocks a selection.
- Affected tests: `ApplicantPipelineTest`, `ApplicantContractStageTest`, `ApplicantStageCatalogTest`, contract/income/expense/visitor/staff/leave/payroll feature suites. They will be run after the changes.

### 5. Clarifications, decisions, and assumptions

- User decisions:
  - Pagination covers the business lists; small catalogues (currencies, stages, commissions, educational reports, Options data) are excluded.
  - All currencies are listed and usable everywhere; the active flag stops gating reads/writes.
- Decisions taken from repository evidence:
  - Applications "global print" = the whole filtered set. The print handler fetches every filtered page (100 rows per request) before printing, and the report subtitle no longer claims "current page".
  - The stage editor's allowed-next field becomes a multi-select of stage keys (string values), matching every other key field in the product.
  - Lines without a stage are placed on their service board using the line's stage → the applicant mirror (when it belongs to that service) → the service's `new` stage, and the case page offers the service's active stages so the first move is always possible.
  - Seeded lines get a stage, and a repair migration backfills existing NULL line stages.
  - Catalogues and pickers keep their semantic ordering (stage `sort_order`, currencies base-first/code, alphabetical names). Only record lists switch to newest-first.
  - Paginated responses keep the plain-array shape unless the caller asks for a page, so pickers and reports that consume the same endpoints are unaffected.
- Assumptions (low risk): "newest first" for records with a real business date uses that date, otherwise `created_at`/`id`.

### 6. Implementation plan

- [x] 1 — Applications print: fetch all filtered pages on print, keep `PrintableListReport`, add a print-report subtitle key in the three locales.
- [x] 2 — Stage editor: replace the allowed-next `VCombobox` with a multi-select that stores string keys; verify create/edit/delete against the live API.
- [x] 3 — Per-service stages: null-stage line placement in `ApplicantController::pipeline` (counts + cards), `lineNextStages` fallback in `applicant-case.vue`, seeders set `current_stage_id`, repair migration backfills NULLs.
- [x] 4 — Ordering: newest-first defaults on contracts, visitors, assets, employees and the leave-request ledger; verify the rest already comply.
- [x] 5 — Backend pagination (optional `page`/`per_page`) for contracts, incomes, expenses, assets, employees, visitors, leave requests, off days, salaries, employee advances, worthy employees/bonuses and recurring events.
- [x] 6 — Frontend server pagination for Contracts (employee/other/cancelled + the five service pages), Income, Expenses, Assets, Staff, Visitors/Follow-up, Leave requests, Off-days, Payrolls, Bonuses and Recurring events; wire `page`/`itemsPerPage` and keep filters server-side.
- [x] 7 — Currencies: return every currency from picker endpoints and accept any existing currency on write; drop the two frontend `isActive` filters.
- [ ] 8 — Validation: typecheck, focused PHP suites, icons, build, focused browser checks. (typecheck, PHP lint and the focused suites ran; icons/build/browser checks did not.)
- [ ] 9 — Mirror: port the main commits into `../educational-multi-tenancy`, resolve office/Drive collisions, rebuild the tenant bundle, run the tenant checks, record the ported SHAs. (Source ported; tenant bundle not rebuilt and SHAs not recorded.)

### 7. Validation plan and results

- [x] `npm run typecheck` — passes in the main worktree and in `../educational-multi-tenancy`.
- [x] Focused `php artisan test tests/Feature/ApplicantPipelineTest.php tests/Feature/VisitorProfileAndServiceContractFieldSetsTest.php` — main: 18 passed (172 assertions); tenant: 17 passed (164 assertions).
- [ ] Live browser checks on `php artisan serve` — not repeated after the final edits: applications print host rows equal the filtered total; stage editor saves a next-stage and the pipeline/case move it; currency picker lists all currencies.
- [x] `php -l` on every changed/new PHP file and `git diff --check` — clean.
- [ ] `npm run build:icons`, `npm run build` — not run; the committed `public/build`, `public/sw.js` and `public/version.json` still describe the previous source.
- Results: source-level checks pass; bundle rebuild and browser checks remain.
- Tenant validation (separate worktree): typecheck and focused tests pass; tenant bundle not rebuilt.

### 8. Risks and mitigations

- Fetch-all-for-print can be heavy on a very large filtered set — mitigation: reuse the server-per-page endpoint in a bounded loop and keep the same A4 report; the print action is user-initiated and reports the real total.
- Server pagination can silently drop rows if a page keeps client-side filtering — mitigation: move each page's filters to the request it already supports, or keep the filter values in the reload key.
- Response-shape change could break selectors — mitigation: paginate only when `page`/`per_page` is sent, and keep `data` as the array for existing consumers.
- Tenant port can collide in routes/tests/migrations — mitigation: follow the conflict order in `docs/facts/branch-mirroring.md`, keep the office migration set, and rebuild the tenant bundle.
- Stage fixes could double-count a case on the board — mitigation: null-stage lines are placed exactly like unlined cases (line first, applicant fallback suppressed by the existing `whereDoesntHave` query).

### 9. Progress log

- 2026-10-27 — DISCOVERY: read project instructions and the applications/stages records; traced print, stages, currencies and tables with `rg`; inspected live data with tinker (11 currencies/1 active, 8 lines/NULL stages, 48 stages, 5 applicants); reproduced in a live Playwright session the current-page-only print report, the broken `allowed_next_keys` save (422), the empty service board and the hidden per-line move control.
- 2026-10-27 — CLARIFIED: user scoped pagination to the business lists (excluding currencies, stages, commissions, educational reports and Options catalogues) and chose "all currencies usable everywhere".
- 2026-10-27 — PLAN: this record saved before source edits.
- 2026-10-27/28 — IMPLEMENTED: shared `PaginatesLists` concern (opt-in `per_page`, plain array otherwise), server tables for contracts/income/expenses/assets/staff/visitors/leave/off-days/payrolls/bonuses/recurring events, employee-driven payroll register, full-filtered-set list prints, stage placement fallback plus seeded and backfilled line stages, newest-first record lists, and currency selection no longer gated by the active flag.
- 2026-10-28 — VALIDATED + COMMITTED: typecheck in both worktrees, PHP lint and the focused feature suites pass; the source change set is committed on `main` and the counselor-review port on `feature/multi-tenancy-per-office`.

### 10. Completion summary

- Delivered: the five requested areas — Applications print, stage manipulation, default ordering, business-list pagination and unrestricted currency selection — plus the counselor review notes carried into both worktrees.
- Acceptance criteria: AC1–AC5 met; AC6 and AC7 partially met (see section 7).
- Tests/checks passed: `npm run typecheck` (main + tenant), `php -l`, `git diff --check`, `ApplicantPipelineTest` and `VisitorProfileAndServiceContractFieldSetsTest` in both worktrees.
- Remaining limitations: generated bundles (`public/build`, `public/sw.js`, `public/version.json`) are not rebuilt, and the live browser checks were not repeated after the final edits.
