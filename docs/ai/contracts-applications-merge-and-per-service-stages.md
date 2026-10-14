# Task record: Merge Applications navigation into Contracts + per-service stages

## Task: Merge Applications navigation into Contracts + per-service stages

- **Task ID:** contracts-applications-merge-and-per-service-stages
- **Created:** 2026-10-13
- **Last updated:** 2026-10-13
- **Status:** COMPLETED
- **Outcome:** Remove the dedicated "Applications" navigation and surface its pages under Contracts; make each built-in service (+ private) own an editable stage list; track the current stage on the `applicant_contract` service line; auto-sync linked tasks with contract status; define "other contracts".

### 1. Requirements and acceptance criteria

- [ ] AC1 The dedicated "Applications" navigation group is gone and its pages (Applications list, Global Search, Visa Reports) are reachable from the Contracts navigation.
- [ ] AC2 Each service (scholarship, test_offer, academy, private) has its own editable stage list, managed from Options → Contracts.
- [ ] AC3 The current stage lives on the `applicant_contract` line, so one applicant can be at different stages per service.
- [ ] AC4 Contract/line stage changes keep the linked task(s) in sync automatically.
- [ ] AC5 Creating a contract creates task(s) for the contract's counselor and admission employee.
- [ ] AC6 "Other contracts" have a defined behavior: non-educational business contracts (no applicant/stage) plus educational leftovers (applicant + stages).

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

### 6. Implementation plan

- [ ] **A. Navigation merge** — remove `navigation/horizontal/applicants.ts`, drop it from `horizontal/index.ts`, add its three destinations to `contracts.ts`.
- [ ] **B. Per-service stage catalogue** — migration adds `service` to stage definitions; backend filters/resolves stages by service; seed a stage set per service; add a stage editor under Options → Contracts (route + nav + page + typed API).
- [ ] **C. Stage on `applicant_contract`** — migration adds `applicant_contract.current_stage_id` + `applicant_stage_histories.applicant_contract_id`, backfills; `ApplicantContract` gains stage relations/transition; new change-stage endpoint per line; resources expose line stage; aggregate mirror kept in sync.
- [ ] **D. Task↔contract sync** — on contract/line stage change (and completion/cancellation) update the linked task; keep idempotent counselor+admission task creation across every creation path.
- [x] **E. Other contracts definition** — *Definition (no schema change needed):* "Other contracts" are contracts whose type is the non-system `other` slug or another non-system type. They may **(a)** stand alone — free-text counterparty, no applicant and no per-service stage (a business contract such as an NDA/partnership/lease), or **(b)** link an applicant (an educational leftover) for CRM context. `applicant_id` is already nullable on other contracts (`ContractController` create/update validation), the counterparty is derived from the applicant when present and stays free text otherwise, and their lifecycle is the contract `status` plus the cancel action. They intentionally do **not** use the built-in per-service stage lists; per-service stages apply to scholarship/test_offer/academy/private lines only.
- [ ] **F. Frontend** — applicant-case per-service stage control, pipeline/board grouping where needed, Options → Contracts stage editor, nav/router, i18n.
- [ ] **G. Validation** — `npm run typecheck`, focused PHP feature tests, `npm run build:icons` if icons change.

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
- **Remaining limitations / follow-up:**
  - Database migrations were validated on fresh SQLite only; the owner must apply `2026_10_13_000001` and `2026_10_13_000002` to the MySQL working database.
  - The applicant's `current_stage_id` is a coarse mirror of the last-changed line; some aggregate screens (dashboard/report/search) still read the applicant-level stage by design. A future pass could roll per-line stages up more precisely.
  - The applicant case page shows the per-service stage control; the legacy applicant-level stage panel is retained for the aggregate workflow/commission behaviour.
  - Per-service stage transition maps for test_offer/academy are seeded defaults and are editable in Options → Contracts.
