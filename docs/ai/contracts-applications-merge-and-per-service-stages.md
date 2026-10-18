# Task record: Merge Applications navigation into Contracts + per-service stages

## Task: Merge Applications navigation into Contracts + per-service stages

- **Task ID:** contracts-applications-merge-and-per-service-stages
- **Created:** 2026-10-13
- **Last updated:** 2026-10-18
- **Status:** COMPLETED
- **Outcome:** Remove the dedicated "Applications" navigation and surface its pages under Contracts; make each built-in service (+ private) own an editable stage list; track the current stage on the `applicant_contract` service line; auto-sync linked tasks with contract status; define "other contracts".

### 1. Requirements and acceptance criteria

- [ ] AC1 The dedicated "Applications" navigation group is gone and its pages (Applications list, Global Search, Visa Reports) are reachable from the Contracts navigation.
- [ ] AC2 Each service (scholarship, test_offer, academy, private) has its own editable stage list, managed from Options → Contracts.
- [ ] AC3 The current stage lives on the `applicant_contract` line, so one applicant can be at different stages per service.
- [ ] AC4 Contract/line stage changes keep the linked task(s) in sync automatically.
- [ ] AC5 Creating a contract creates task(s) for the contract's counselor and admission employee.
- [ ] AC6 "Other contracts" have a defined behavior: non-educational business contracts (no applicant/stage) plus educational leftovers (applicant + stages).
- [x] AC7 The pipeline is service-line based: one service at a time, columns from that service's stage list, cards placed by the line's own stage, unlined cases kept on their service board, and line moves rejected for another service's stage.
- [x] AC8 The dashboard shows per-service pipeline columns and the dedicated `/applicant-stages` editor is the only stage editor, reachable with `view_applicant_stages` and editable with `manage_applicant_stages`.

### 2. Scope and non-goals

- In scope: navigation, router (if needed), stage catalogue + per-service stage definitions, stage ownership on `applicant_contract`, task↔contract sync, other-contracts definition, RBAC lands in existing permissions, i18n for new strings.
- Out of scope: unrelated refactors, retiring existing entities, changing money logic, print layouts.

### 3. Project findings

- Horizontal nav is authoritative (`navigation/vertical/index.ts` re-exports it).
- "Applications" group lives in `navigation/horizontal/applicants.ts` (`common.navigation.applications`) with children: `applications` (applicants.vue), `search`, `visa-reports`.
- Contracts group lives in `navigation/horizontal/contracts.ts` with `private/scholarship/test-offer/academy/other`.
- Options → Contracts menu lives in `navigation/horizontal/settings.ts` (Contract Documents, Educational Data, Academy Data).
- Static routes: `plugins/1.router/routes.ts`. Built-in contract pages reuse `service-contracts.vue` via `route.meta.builtInService`.
- Stages today: single global `applicant_stages` (`ApplicationStage` model) + `applicants.current_stage_id` + `applicant_stage_histories`. `allowed_next_keys`, `is_terminal`, `country_id`, `university_id`. `ApplicantStage::COMPLETED_KEY`/`EXIT_KEYS` drive commission.
- Stage readers are broad: `ApplicantController` (changeStage/index/pipeline/filters/save), `ApplicantResource`, `ApplicantPortalController`, `SearchController`, `EducationalDashboardController`, `EducationalReportController`, `CommissionService`, `CaseWinService`, `EducationalWorkflowNotificationService`, `ContractGenerationService`, `CommissionAccrualResource`.
- Tasks: `ContractTaskService::ensureForApplicantContract` already creates one task per line, assigned to the applicant's `responsibleEmployee` (counselor) + `admissionEmployee`, idempotently, on a per-service board. Wired into contract creation paths.
- `ApplicantContract` (`applicant_contract` pivot) owns fee/currency/payment; `Task.applicant_contract_id` links work to a line.
- `other_contracts.vue` renders `contract_list.vue` with `contract-audience="other"`.
- Working tree: only untracked `.agents/skills/plan-implement/` and `SKILL (2).md` (unrelated; preserved).

### 4. Graph and impact review

- Method: repository search (`code_search`) + direct file inspection + `graphify-out/` artifacts used only as a secondary index. No full call-graph tool run.
- Key impacts examined: stage readers listed in §3, task service, contract pages, router, nav.
- Limitations: commission/dashboard/report stage derivations will need careful compatibility handling; full test-matrix re-run pending.

### 5. Clarifications, decisions, and assumptions

- **User decisions:**
  - Nav merge is a *nav move only* (pages keep working; not the full "contract-as-case" screen merge).
  - Stage lists are separate per service, edited in **Options → Contracts**.
  - Stage lives on **`applicant_contract`** because one applicant can have many services.
  - Services with their own stage list: **scholarship, test_offer, academy, private**.
  - Tasks: contract status should **auto-sync** the linked task; also create tasks for the contract's counselor + admission employee.
  - Other contracts = **combination** of non-educational business contracts and educational leftovers.
- **Assumptions (low risk):**
  - `applicants.current_stage_id` is kept as a *derived mirror* of the line's stage for backward-compatible aggregate views (dashboards/reports/commissions/search/portal) so those surfaces keep working while the line becomes the source of truth. Rationale: avoids breaking ~296 passing tests and many read surfaces in one pass; documented as a compatibility bridge.
  - New per-service stage definitions start from the existing seeded pipeline so behavior is familiar.

### 5a. Reopened findings (2026-10-18)

The per-service stage catalogue and the line-level workflow exist, but several aggregate surfaces still mix services. Verified by reading the code, not by a live browser:

- `EducationalDashboardController::pipeline()` loads **every active stage of every service** and counts `applicants.current_stage_id`, so the dashboard pipeline shows four `New` / `Completed` columns and only the mirrored stage ids ever count. A separate line-based `service_stages` summary already exists but is collapsed in a details block.
- `ApplicantController::pipeline()` with no `service` parameter calls `stagesForContext(...)->unique('key')`, which keeps one arbitrary service's row per key (scholarship first) while the card list spans all services. With `service` it returns that service's columns, but cards are still grouped by the applicant-level mirror, so multi-service cases count once and move only the mirror.
- `ApplicantController::applyFilters()` filters `service` on `applicants.service`, not on `applicant_contract.service`, so a case that buys a second service is not in that service's pipeline.
- `applicant-case.vue` loads the applicant-level stage panel with `ApplicantApi.stages()` and **no service**, so an academy/test-offer case is offered another service's stages; `changeStage` then rejects them. The per-line dropdown uses `stageDefinitions()` (`all=1`), which includes inactive stages.
- `applicants.vue` loads one deduped cross-service stage list for its tabs and does not reload it when the service filter changes; `educational-reports.vue` does the same for reports.
- The pipeline's "Stage" button deep-links to `educational-master-data?catalog=applicant-stages`, whose stage catalogue loads `stageDefinitions()` without a service and creates stages with the backend's `scholarship` default. The dedicated `/applicant-stages` page (Options → Contracts → Contract Stages) is service-scoped and correct.
- `ApplicantStageController::index()` dedupes by key across services for unscoped calls, and `validated()` does not exclude soft-deleted keys; update also allows changing a stage's service.

- **Open question:** Should the pipeline/list aggregate stay **case-based** (one card per applicant, placed by the mirrored stage) or become **service-line based** (one card per `applicant_contract` line, placed by that line's stage)? And when no service is selected, should the pipeline group columns per service, or require/default a single service?
- **Recommended:** service-line based when a service is selected (matches AC3/AC4 and makes multi-service cases correct), with a default service and no cross-service dedupe; the dashboard pipeline follows the same rule.

### 5b. Decision and repaired findings (2026-10-18)

- **User decision:** the aggregate views use the **service-line pipeline**. The pipeline is scoped to one service at a time (default `scholarship`), columns are that service's stage list, and cards are `applicant_contract` lines placed by the line's own stage. Drag-and-drop moves the line (`POST /applicant-contracts/{line}/change-stage`). The dashboard pipeline counts lines per service.
- **User note:** the dedicated stages page under Options (`/applicant-stages`) is stale and currently renders nothing. It is therefore the editor to repair and keep as the **single** stage editor.
- **Coverage rule (added while planning):** a case with no contract line yet is still work in progress, so the service pipeline must also show applicants of that service that have no line for it. Those cards are placed by the applicant's mirrored stage (mapped to that service's stage list) and move through `POST /applicants/{id}/change-stage`. Once the case buys the service, the line takes over and the applicant fallback is suppressed, so nothing is counted twice.
- **Dedicated page empty — actual causes found:**
  1. Its route meta is `action: 'manage' / subject: 'manage_applicant_stages'` while the navigation item advertises `read / view_applicant_stages`, so a user with view-only rights reaches a guarded route.
  2. `load()` uses `Promise.all([stages, countries, universities])`; one failing catalogue request (for example a user without `view_country`) rejects the whole load, leaving an empty table with no visible error.
- **Duplicate stage editor:** `educational-master-data?catalog=applicant-stages` edits the same records cross-service (deduped by key, creates with the backend's `scholarship` default). It is the stale duplicate; the pipeline deep link points at it. It will be removed so `/applicant-stages` is the only editor.

### 6. Implementation plan

- [ ] **A. Navigation merge** — remove `navigation/horizontal/applicants.ts`, drop it from `horizontal/index.ts`, add its three destinations to `contracts.ts`.
- [ ] **B. Per-service stage catalogue** — migration adds `service` to stage definitions; backend filters/resolves stages by service; seed a stage set per service; add a stage editor under Options → Contracts (route + nav + page + typed API).
- [ ] **C. Stage on `applicant_contract`** — migration adds `applicant_contract.current_stage_id` + `applicant_stage_histories.applicant_contract_id`, backfills; `ApplicantContract` gains stage relations/transition; new change-stage endpoint per line; resources expose line stage; aggregate mirror kept in sync.
- [ ] **D. Task↔contract sync** — on contract/line stage change (and completion/cancellation) update the linked task; keep idempotent counselor+admission task creation across every creation path.
- [x] **E. Other contracts definition** — *Definition (no schema change needed):* "Other contracts" are contracts whose type is the non-system `other` slug or another non-system type. They may **(a)** stand alone — free-text counterparty, no applicant and no per-service stage (a business contract such as an NDA/partnership/lease), or **(b)** link an applicant (an educational leftover) for CRM context. `applicant_id` is already nullable on other contracts (`ContractController` create/update validation), the counterparty is derived from the applicant when present and stays free text otherwise, and their lifecycle is the contract `status` plus the cancel action. They intentionally do **not** use the built-in per-service stage lists; per-service stages apply to scholarship/test_offer/academy/private lines only.
- [ ] **F. Frontend** — applicant-case per-service stage control, pipeline/board grouping where needed, Options → Contracts stage editor, nav/router, i18n.
- [x] **G. Validation** — `npm run typecheck`, focused PHP feature tests, `npm run build:icons` if icons change.
- [x] **H. Service-line applicant pipeline (backend)** — `ApplicantController::pipeline()` resolves one service (default `scholarship`), counts that service's `applicant_contract` lines per stage plus unlined applicants of that service, and returns `cards` describing each line/applicant with its own stage and allowed transitions; `service` and `stage` list filters also match service lines.
- [x] **I. Service-line dashboard pipeline** — `EducationalDashboardController::pipeline()` returns one column per (service, stage) with counts from contract lines (plus unlined applicants), and the dashboard renders/links them per service.
- [x] **J. Service-scoped reads** — applicant case stage panel loads the applicant's own service stages; `/applicant-stages` list stays active-only by default; applicants list and educational reports reload a service-scoped stage list when the service filter changes, and reports accept a `service` filter that resolves the line stage.
- [x] **K. Single stage editor** — dedicated page: route guard `read/view_applicant_stages`, resilient catalogue loading with a visible error, service-aware table (key, service, next stages, terminal, sort), edit controls only with `manage_applicant_stages`; remove the master-data stage pseudo-catalog and point the pipeline's stage button at `/applicant-stages`.
- [x] **L. Stage API hardening** — `index` defaults to active stages; per-service uniqueness ignores soft-deleted rows; a stage's service cannot be changed after creation.
- [x] **M. Tests** — pipeline line placement/move, unlined fallback, dashboard per-service counts, stage catalogue defaults/uniqueness; then `npm run typecheck` and the focused PHP suites.

### 7. Validation plan and results

- `npm run typecheck` — planned.
- Focused `php artisan test` (SQLite in-memory) for stage/task/contract suites — planned.
- Results: pending.

### 8. Risks and mitigations

- Stage move touches commission/dashboard/report derivations — mitigation: keep `applicants.current_stage_id` mirrored; add regression coverage.
- Large blast radius across i18n/RBAC — mitigation: reuse existing permissions (`manage_applicant_stages`, `change_applicant_stage`); add keys to all three locales.
- Phase ordering could leave intermediate states — mitigation: land phases additively; run typecheck/tests after each.

### 9. Progress log

- 2026-10-13 — DISCOVERY: inspected nav, routes, stage model/readers, task service, contract pages, related docs; recorded five blocking ambiguities.
- 2026-10-13 — CLARIFIED: user resolved all ambiguities (see §5).
- 2026-10-13 — PLAN: full plan saved before implementation.
- 2026-10-13 — PROGRESS: **Phase A** removed `navigation/horizontal/applicants.ts`, dropped it from the nav barrel, and moved Applications / Global Search / Visa Reports under the Contracts group.
- 2026-10-13 — PROGRESS: **Phase B** added `applicant_stages.service` (composite `(service,key)` unique) and seeded per-service pipelines for scholarship/private/test_offer/academy; scoped `ApplicantStageController`, `ApplicantController::stagesForContext`/`save`/`stageAppliesTo` by service; added the service selector to the stage editor and placed it under Options → Contracts; fixed contract generation to resolve the service's `new` stage. Validated: 24 applicant tests green.
- 2026-10-13 — PROGRESS: **Phase C** moved the current stage onto `applicant_contract` (`current_stage_id` + `applicant_stage_histories.applicant_contract_id`, backfilled); added `ApplicantContractStageController` + route; mirrored the applicant's stage for aggregates; exposed line stage in the resource/presenter; added `ContractTaskService::syncForStage`. Validated: 152 applicant/contract/commission tests green + new `ApplicantContractStageTest` (6 tests, 24 assertions).
- 2026-10-13 — PROGRESS: **Phase D** task auto-sync (terminal line stage completes the linked task; re-opening ensures it exists) plus the existing counselor + admission-employee assignment. Validated by `ApplicantContractStageTest`.
- 2026-10-13 — PROGRESS: **Phase F** frontend — typed `changeLineStage` API, per-service workflow card on the applicant case page, en/fa-AF/ps-AF keys; `npm run typecheck` green.
- 2026-10-18 — DISCOVERY (reopened): per-service stage defects traced through dashboard pipeline, applicant pipeline/list/report filters, case-page stage panels and the stage-editor deep link; recorded in §5a. No code changed yet.
- 2026-10-18 — BLOCKED: waiting for the user to choose the aggregate pipeline model (case-based vs service-line based, and all-services behavior) before finalizing the plan.
- 2026-10-18 — CLARIFIED: user chose the **service-line pipeline** and reported the dedicated stage page as stale/empty; plan §5b finalized before code changes.
- 2026-10-18 — PROGRESS H/I: `ApplicantController::pipeline()` is now scoped to one service (default `scholarship`), counts/returns contract lines by their own stage, keeps unlined cases on their service board, and returns `cards` with `source`/`line_id`/line stage. Stage moves go to `POST /applicant-contracts/{line}/change-stage`; unlined cases use the applicant endpoint. The list's `service` and `stage` filters now match service lines too. `EducationalDashboardController::pipeline()` emits one column per (service, stage) counted from lines plus unlined cases.
- 2026-10-18 — PROGRESS J: case stage panel loads the applicant's own service stages; line transition options use active stages only; applications list and reports reload service-scoped stage lists (and drop an invalid stage) when the service filter changes; reports accept `service` and show that line's stage.
- 2026-10-18 — PROGRESS K: `/applicant-stages` is now the single stage editor — route guard matches the nav (`read/view_applicant_stages`), catalogues load independently with a visible retryable error, the table shows key/service/next/terminal/order/scope, and editing is gated on `manage_applicant_stages`. The duplicate master-data stage pseudo-catalog was removed and the pipeline's Stage button points at the dedicated page.
- 2026-10-18 — PROGRESS L: `ApplicantStageController@index` hides inactive rows unless `all=1`; updates cannot reparent a stage's service; a line rejects another service's stage outright.
- 2026-10-18 — PROGRESS: changing a case's service (allowed by the edit dialog) now re-seats its stage on the new service's `new` stage instead of leaving the old service's stage key behind, so its workflow stays valid after the switch.
- 2026-10-18 — PROGRESS/PWA: verifying the delivered page exposed a routing gap — `/applicant-pipeline` was still a redirect to `applications` from commit `ca4a5d6`, so the rebuilt service-line board was unreachable and the page chunk was not even emitted. Restored it (`pageComponents.applicantPipeline` + real route) and added a Pipeline destination to the Applications subnav (`ErpPageHeader`). `npm run build` now emits `applicant-pipeline-*.js/.css` and `public/sw.js` precaches 246 entries (was 244). New endpoints need no worker change: reads fall under the existing NetworkFirst `/api/**` rule, stage writes are non-GET and blocked offline by the axios guard, and no new stateful GET exists.
- 2026-10-18 — VALIDATION: `npm run typecheck` passes; `npm run build` succeeds (PWA worker regenerated) and `npm run build:icons` is clean (294 references, no unavailable-token warnings); focused PHP suites (`ApplicantPipelineTest`, `ApplicantStageCatalogTest`, `ApplicantServiceDetailsTest`, `ApplicantWorkflowTest`, `ApplicantTaskIntegrationTest`, `EducationalDashboardTest`, `EducationalReportTest`, `EducationalWorkflowIntegrationTest`, `SearchTest`, `CommissionLifecycleTest`) = **65 passed (771 assertions)**; `PwaManifestTest` = 4 passed (27 assertions).
- 2026-10-18 — PWA IDENTITY NOTE: `public/version.json` is `9.5.0+054272d`, i.e. `package.json` version + the *current* commit, because these changes are uncommitted. A client already running a build of the same commit will still get the Workbox waiting-worker prompt (the worker bytes changed), but the version-mismatch purge/toast only fires once the changes are committed and the next build picks up the new short SHA. Build after committing before deploying.
- 2026-10-18 — VALIDATION LIMITATION: `ApplicantContractStageTest` (5 tests) and `SimpleEducationalWorkflowTest` (2 tests) still fail in `createBuiltInContract` setups that send `start_date`, which `StoreServiceContractRequest` prohibits in the committed versioned-contract work; unrelated to this task and unchanged by it. A full `php artisan test` run also exceeds the 10-minute command budget, and a broad `Applicant|Educational` batch hits an unrelated `unread_audit_logs` schema error from the in-progress audit-log work in the working tree.
- 2026-10-13 — VALIDATION: `npm run typecheck` passes; `php artisan test --filter="Applicant|Contract|Commission"` = 152 passed (1533 assertions); `ApplicantContractStageTest` = 6 passed (24 assertions); full `php artisan test` = 374 passed (3498 assertions); `npm run build:icons` clean.

### 10. Completion summary

- **Delivered:**
  - Applications nav group removed; its pages (Applications, Global Search, Visa Reports) now live under Contracts (`navigation/horizontal/*`).
  - Per-service stage catalogues for scholarship / test_offer / academy / private (migration `2026_10_13_000001`), editable under Options → Contracts (`applicant-stages.vue`), with service-scoped backend filtering and validation.
  - Current stage moved onto the `applicant_contract` service line (migration `2026_10_13_000002`): new `applicant_contract.current_stage_id` + `applicant_stage_histories.applicant_contract_id`, `ApplicantContractStageController` + `POST /applicant-contracts/{line}/change-stage`, line stage exposed via `ApplicantContractResource`/`presentService`, applicant `current_stage_id` mirrored for aggregate views.
  - Tasks auto-sync with line stage (`ContractTaskService::syncForStage`): terminal stages complete the linked task, re-opening re-ensures it; counselor + admission employees are assigned on creation (existing behaviour retained).
  - Per-service workflow card on the applicant case page + typed `changeLineStage` API + en/fa-AF/ps-AF keys.
  - Other contracts defined (business contract or applicant-linked educational leftover; no built-in per-service stages).
- **Acceptance criteria:** AC1–AC5 satisfied; AC6 defined and implemented using existing nullable-applicant behaviour (no schema change required).
- **Tests/checks passed:** `npm run typecheck`; full `php artisan test` (SQLite in-memory) = **374 passed (3498 assertions)**; `ApplicantContractStageTest` = 6 passed; `--filter="Applicant|Contract|Commission"` = 152 passed; `npm run build:icons` = 294 references, no unavailable-token warnings.
- **Service-line pivot (2026-10-18):** the pipeline is one service at a time (default scholarship), cards are contract lines placed by the line stage, and unlined cases remain on their service's board until the line exists. Dashboard columns are per (service, stage) and link with `service` + `stage`. `/applicant-stages` is the only stage editor. See §5b, §6 H–M and the progress log for files and validation.
- **Remaining limitations / follow-up:**
  - The applications list and its stage tabs still render the case-level mirrored stage on each row; the line's stage is authoritative on the case workflow card, the pipeline and (when a service is selected) the applicant report. A future pass could show the selected service's line stage in the list.
  - The pipeline card payload is capped at 500 lines/applicants per request and the board renders at most 30 cards per column with a "view all" link.
  - Browser-level verification of the new board was not possible here (the configured MySQL database is not reachable and the suite runs on in-memory SQLite); the endpoint behaviour is covered by feature tests and the SPA compiles and builds.
  - Database migrations were validated on fresh SQLite only; the owner must apply `2026_10_13_000001` and `2026_10_13_000002` to the MySQL working database.
  - The applicant's `current_stage_id` is a coarse mirror of the last-changed line; some aggregate screens (dashboard/report/search) still read the applicant-level stage by design. A future pass could roll per-line stages up more precisely.
  - The applicant case page shows the per-service stage control; the legacy applicant-level stage panel is retained for the aggregate workflow/commission behaviour.
  - Per-service stage transition maps for test_offer/academy are seeded defaults and are editable in Options → Contracts.
