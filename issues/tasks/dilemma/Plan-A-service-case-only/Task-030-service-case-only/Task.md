# Task Task-030 — Make each contracted service the only case

## Metadata
- Task ID: `Task-030`
- Title: `Make each contracted service the only case`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack Laravel/Vue implementer with case-domain review`
- Estimated size: `L`
- Source plan/version: `User request 2026-11-05: remove the applicant wrapper case; one service case per contracted service; Applications -> Applications lists one row per service; no case on applicant creation; contract creation ensures the applicant and the service line; the service case owns the wrapper's calling, portal, document, note, task and finance functionality. Repository state 2026-11-05.`
- Requirement / behavior IDs: `CASE-ONLY-01` sole case identity, `CASE-ONLY-02` Applications register, `CASE-ONLY-03` no case on party creation, `CASE-ONLY-04` contract creates/reuses party and line, `CASE-ONLY-05` workspace parity, `CASE-ONLY-06` line-scoped tasks and finance, `CASE-ONLY-07` party-level data disclosure, `CASE-ONLY-08` wrapper retirement, `CASE-ONLY-09` stage-mirror retirement, `CASE-ONLY-10` dashboard and report truth, `CASE-ONLY-11` permissions and ownership, `CASE-ONLY-12` migration and rollback safety

## Objective

Make one `applicant_contract` service line the only case. The Applications -> Applications page becomes a service-case register with one row per line, the service-case workspace becomes the full working screen, creating an applicant creates only a party record, and creating a service contract creates or explicitly reuses the party and always creates the service line/case. Remove the applicant-level stage panel, timeline, writer and "Case created" history that currently act as a wrapper case.

## Context and problem

The current product has two case concepts:

- A wrapper case on `/applicants/:id`, created when an applicant is saved. `ApplicantController::save()` assigns the service's `new` stage and writes an `ApplicantStageHistory` row with the note `Case created`; the page renders a stage writer and timeline while the party has no service lines. The Applications list (`ApplicantController::index()`) returns one row per applicant, and its Open case action always opens this wrapper.
- One service case per `applicant_contract` line. `GET /api/applicant-contracts/{line}` returns only that line's stage, history, tasks and receipts, and `/service-cases/{line}` is the isolated workspace delivered by Task-028.

The product owner's model is the second one only. An applicant is a party/customer; each contracted service is a case. Ahmad Wali with two scholarship lines must therefore appear as two Applications rows, each opening its own case. There must be no applicant-level case that contains service cases.

The wrapper is not a separate `cases` table. It is the combination of `applicants.current_stage_id`, applicant-level stage history, the applicant case page, and the Applications list row. That makes the behavioural removal a read-model and UI change rather than a table drop, but it also means every current reader of `applicants.current_stage_id` must be audited.

The service-case workspace is currently a shell compared with the wrapper. It has the line stage, line history, line task list and line finance list, but it does not have the wrapper's calling/email actions, CRM relationship details, portal invitation/access/delete controls, profile editing, profile documents, notes, university applications, pending financial requests or task creation/completion. Moving those capabilities is part of this task; leaving them only on the wrapper would preserve the wrapper by another name.

Two concrete gaps matter for the move:

- **Manual task creation is not line-scoped.** `TaskController::store()` validates `applicant_id` but not `applicant_contract_id`; `applicant-case.vue::createTask()` sends only `applicant_id`. Only `ContractTaskService` creates tasks with a line id. The service case needs a real line-scoped create/complete path.
- **Finance recording already exists per line.** `IncomeController::store()` accepts `applicant_contract_id`, `IncomeController::applicantContracts()` lists an applicant's payable lines, `/income` records against a selected service line, and `ServicePaymentsDialog` submits one income per selected line. This task must reuse that flow and must not add a second finance endpoint or a duplicate payment form.

There is a blocking identity question. `applicants.service` holds one service and one per-service detail relation. The current contract generator always creates a new applicant for a new contract and explicitly prohibits `applicant_id`; only an extension reuses the parent's applicant. Reusing an existing applicant for another service is not supported by the current identity model. This task therefore supports explicit reuse of an existing applicant **of the same service** and blocks cross-service reuse until the product decides whether a party can own several service identities or needs a line-level detail model. It must not silently create a second party to work around that decision.

## Scope

### Included

- Define `applicant_contract.id` as the sole case id used by the Applications list, case route, stage history, task links, finance links and print.
- Replace the Applications -> Applications data source with a line register: one row per non-deleted contracted service line, including applicant identity, service, contract number/role, line stage, contract status, payment state/balance when permitted, responsible employee, destination, open-task count and dates.
- Keep the existing Applications filters, search, stage tabs, pagination and print, but apply them to lines. A stage filter must compare the line's stage, never `applicants.current_stage_id`.
- Make Open case open `/service-cases/{line}`. Remove the applicant-case entry point from the Applications page.
- Move the wrapper's working capabilities into the service-case workspace: call/email actions, CRM relationship details, portal invite/access/delete, party profile editing, party summary and guardian details, profile documents and task attachments, notes, university applications for scholarship/private, line finance and pending financial requests, task create/complete, and the existing stage/history panels.
- Add line-scoped manual task creation. `POST /api/tasks` must accept an optional `applicant_contract_id`, require it to belong to the submitted or resolved applicant, enforce the same ownership scope as the case, and persist it. The service-case page must preselect the line and send both ids.
- Reuse the existing income/service-payment flow from the service case. A Record payment action may preselect the applicant and line in the existing Income page or `ServicePaymentsDialog`; it must not create a second finance write path, table or calculation.
- Stop creating a case when an applicant is created. `POST /api/applicants` creates a party record only: no default stage assignment, no `ApplicantStageHistory` row and no Applications row until a service line exists.
- Make service-contract creation accept an optional explicit existing `applicant_id` for the same service, or create the party when absent. It must create exactly one contract, one `applicant_contract` line, one line stage/history row and one case row. Extensions keep their current parent-applicant behaviour.
- Update `VisitorController::convertToApplicant()` so conversion creates a party without a wrapper stage/history; the line is created when a service contract is issued.
- Retire the wrapper stage writer and UI: no applicant-level stage select, note, record button, terminal dialog, stage timeline or Open case link. `/applicants/{id}` may remain as a party profile reachable from the service case, but it must not present itself as a case. Remove or refuse `POST /api/applicants/{id}/change-stage` once no client uses it.
- Audit and update every remaining reader of `applicants.current_stage_id` used as case state, including the dashboard, reports, search/summary payloads and pipeline links. Service lines are the source of truth; an unlined party is not a case.
- Preserve legacy `applicant_stage_histories` rows with a null `applicant_contract_id` as historical audit data, but never write new case-level rows and never present them as a case timeline. Do not delete history in this task.
- Add focused feature tests, update affected existing suites, add English/Dari/Pashto copy, and update the maintained docs that describe the case model.

### Excluded

- No automatic dedupe by name, phone, email or national id. Existing-party reuse is an explicit operator selection; fuzzy matching needs its own policy.
- No cross-service reuse of one applicant until the identity decision is approved. If the product requires a scholarship applicant to also buy an academy service under the same party, stop and record the schema/domain change instead of creating a duplicate party.
- No line-scoped ownership for applicant-level tables (`applicant_universities`, `applicant_attachments`, `applicants.notes`, portal users, contact/call history). They remain party-level data, labelled as shared across the party's cases. Making them per-case is a separate schema decision.
- No schema drop of `applicants.current_stage_id` and no deletion of legacy stage history. The column becomes a deprecated compatibility field that no case workflow writes or reads.
- No change to fee, discount, currency, receipt, commission, payroll, stage-catalogue or print-layout rules.
- No rework of the already line-only pipeline beyond links, counts and stage truth affected by this task.
- No bulk stage movement, multi-case selection or new case table.
- No removal of the party profile, CRM record, portal account, attachments or notes; only their wrapper-case meaning is removed.

## Preconditions and assumptions

- The product owner confirms that an applicant is a party/customer and a contracted service line is the only case; two service lines on one party are two Applications rows and two workspaces.
- Existing service lines already carry their own `current_stage_id` and line history. The recent line-stage backfill is treated as the authoritative starting state; ambiguous legacy applicant history is not reassigned.
- An existing party may be selected explicitly for a new contract of the same service. Cross-service reuse is blocked pending a product/schema decision; the implementation must fail clearly rather than creating a duplicate.
- `/applicants/{id}` remains useful as a party profile reached from a case header. If the product later wants the route removed entirely, that is a small follow-up on the same page and router, not a reason to retain case semantics now.
- Party-level panels are allowed on the service case as long as they are visibly labelled as shared party data. Sibling line stage, history, tasks and finance must never appear in the line sections.
- No destructive migration is required. `applicants.current_stage_id` is already nullable in the current schema; the task stops if a schema change is needed to make existing rows valid.
- Tests create their own applicants, lines, stages, users and permissions; demo rows are not fixtures.

## Dependencies

None for execution. This is a self-contained alternative plan. It reuses the delivered line-only pipeline, the existing service-case read model and the existing income flow; it does not require a new task dependency.

## Contracts and boundaries

- **Case identity:** one `applicant_contract` row is one case. Its primary key is the case id in the route, list row, stage history, task link, receipt link and print. A contract with several service lines yields several cases.
- **Applications list:** `GET /api/applicant-contracts` (or the existing Applications route renamed to that contract) returns a paginated line register. Each row must expose at least `line_id`, `contract_id`, `contract_number`, `contract_role`, `parent_contract_number`, `is_extension`, `applicant_id`, applicant name/contact, service key/label, line `current_stage`, contract status, responsible employee, destination fields, open-task count, created date, and payment state/balance only when the viewer holds `view_applicant_financials`. The response must not return one row per applicant and must not include unlined parties.
- **Applications filters:** service, stage, status, payment status, responsible employee, country, university, education level, semester, search and date filters apply to the line and its party. Stage tabs are built from the selected service's active stages and count lines. Pagination totals and the print walk count lines, not applicants.
- **Applications actions:** Open case always targets `/service-cases/{line}`. The page must not link to `/applicants/{id}` as a case. A party profile link may exist inside the case header, not as the row action.
- **Party creation:** `POST /api/applicants` creates a party/customer record only. It must not assign `current_stage_id` or create an `ApplicantStageHistory` row. A party with no line has no case row and is absent from Applications.
- **Contract creation:** `POST /api/service-contracts/{service}` accepts optional `applicant_id` (explicit existing party). When supplied, the service must match the contract service and the actor must be allowed to manage that party; the generator loads that party, does not create a second one, creates one contract and one line, writes one line stage/history row and creates the line's delivery task. When omitted, the current create-party path runs without any applicant-level stage/history. Extension behaviour is unchanged and remains parent-applicant scoped.
- **Workspace read:** `GET /api/applicant-contracts/{line}` remains the case read. It may add a `party` summary and portal status needed by the header, but it must never include sibling line ids, sibling history, sibling tasks or sibling balances. Party-level panels may use the existing applicant document/notes/university/portal endpoints by applicant id.
- **Workspace writes:** stage writes stay on `POST /api/applicant-contracts/{line}/change-stage`. Task writes use the existing `/tasks` contract with `applicant_contract_id`. Finance writes use the existing `/incomes` contract; no new finance route. Portal, documents, notes and university writes use their existing applicant endpoints.
- **Task contract:** `POST /api/tasks` accepts `applicant_id` and/or `applicant_contract_id`. If both are supplied they must agree; if only the line is supplied the server derives the applicant. A line belonging to another applicant, a deleted/cancelled line, or a line outside the actor's case scope is rejected before insert. The created task keeps the line id and remains visible on the wrapper/task board.
- **Stage contract:** the line stage is the only editable case stage. Applicant-level `current_stage_id` is not written on party creation, contract creation or line moves; it remains a legacy nullable mirror for historical compatibility only. No new `is_case_level` history rows are produced. `POST /api/applicants/{id}/change-stage` is removed or returns a clear refusal once no client calls it.
- **Finance contract:** one receipt targets one line through `applicant_contract_id`; `IncomeController` and `ApplicantContractPaymentService` remain authoritative. The service-case action is a shortcut to the existing flow, not a parallel ledger.
- **Party-level disclosure:** profile documents, notes, portal access, CRM/call history and university applications are shared party data. The case page must label them as party-level and must not imply they belong exclusively to the open line. Line stage, line history, line tasks and line receipts remain isolated.
- **Permissions:** no permission rename or new grant. The list/case reads use `view_applicants` plus the existing ownership scope; stage writes use `change_applicant_stage`; task creation uses `create_tasks`; finance uses the existing income/payment grants; portal, documents, notes and party edits keep their current grants. The UI must hide or disable an action the viewer cannot use instead of relying on a 403 after submission.
- **Shared files:** `routes/api.php` is shared with existing route owners; add/rename only the list route and remove the applicant stage writer without reordering unrelated routes. The three frontend locale files and three backend catalogs are shared; add only the case-only key subtree and remove only keys whose producer is retired. Do not reformat unrelated entries.
- **Data boundary:** legacy applicant-level stage history is retained read-only. It is not merged into a line when the mapping is ambiguous, and it is not shown as a case timeline. No line, contract, receipt or issued snapshot is deleted.

## Exclusive file ownership

- `app/Http/Controllers/ApplicantController.php` — party-only create/update, line register, remove wrapper stage writer and unlined-case pipeline remnants, line-derived filters.
- `app/Http/Controllers/ApplicantContractStageController.php` — case read enrichment for party actions and line-scoped stage truth.
- `app/Http/Controllers/TaskController.php` — line-scoped manual task creation and ownership validation.
- `app/Http/Controllers/VisitorController.php` — visitor conversion creates a party without a wrapper case.
- `app/Http/Controllers/EducationalDashboardController.php` — case counts and stale-case links use lines, not `applicants.current_stage_id`.
- `app/Http/Controllers/EducationalReportController.php` — report stage/case columns and aggregates use lines.
- `app/Services/Contracts/ContractGenerationService.php` — explicit existing-party reuse and removal of applicant-level stage/history on creation.
- `app/Http/Requests/StoreServiceContractRequest.php` — allow `applicant_id` for same-service explicit reuse and keep unsafe fields prohibited.
- `app/Http/Resources/ApplicantContractResource.php` — party summary, portal status, line task/finance fields needed by the full workspace.
- `app/Http/Resources/ApplicantResource.php` — stop serializing the wrapper as the case; keep party identity and service details.
- `app/Models/Applicant.php` — remove case-level stage relationship semantics from the party model; keep legacy column handling explicit.
- `app/Models/ApplicantContract.php` — line relations required by the register and workspace.
- `resources/ts/pages/applicants.vue` — line-register table, filters, print, Open case; remove the applicant-case row action.
- `resources/ts/pages/applicant-case.vue` — retire the wrapper stage panel, timeline and writer; keep or reduce the page to a party profile.
- `resources/ts/pages/service-case.vue` — full workspace: party actions, documents, notes, university applications, tasks, finance, stage/history.
- `resources/ts/pages/service-contracts.vue` — explicit existing-party selection and contract creation that produces one case.
- `resources/ts/api/applicants.ts` — line-register types and party-only applicant calls.
- `resources/ts/api/serviceCases.ts` — expanded case payload and party-action helpers if needed.
- `resources/ts/api/task.ts` — line-scoped task create type.
- `resources/ts/api/kanban.ts` — `applicant_contract_id` in the shared task create type.
- `resources/ts/components/income/ServicePaymentsDialog.vue` — optional applicant/line preselection for the case shortcut without a second writer.
- `resources/ts/pages/income.vue` — accept the case shortcut context and reuse the existing income dialog.
- `resources/ts/plugins/1.router/routes.ts` — Applications and party-profile routing; no applicant-case entry point.
- `routes/api.php` — line-register route, optional existing-party contract route support, retire the applicant stage writer route.
- `resources/ts/plugins/i18n/locales/en.json` — case-only copy and retired wrapper keys.
- `resources/ts/plugins/i18n/locales/fa-AF.json` — Dari case-only copy and retired wrapper keys.
- `resources/ts/plugins/i18n/locales/ps-AF.json` — Pashto case-only copy and retired wrapper keys.
- `tests/Feature/ServiceCaseOnlyTest.php` — new end-to-end coverage for the sole-case model.
- `tests/Feature/ServiceCaseIsolationTest.php` — update wrapper expectations and line/party boundaries.
- `tests/Feature/VisaCaseWorkspaceTest.php` — update Applications register and party-profile expectations.
- `tests/Feature/ApplicantPipelineTest.php` — keep line-only pipeline green with the new register.
- `tests/Feature/ApplicantContractStageTest.php` — line stage remains the only case writer.
- `tests/Feature/ApplicantTaskIntegrationTest.php` — line-scoped task creation and completion.
- `tests/Feature/BuiltInContractGenerationTest.php` — existing-party reuse and no applicant-level case creation.

## Acceptance criteria

- [ ] Given a standalone `POST /api/applicants`, when the party is created, then it has no case stage, no `ApplicantStageHistory` row and no Applications row; it is readable as a party record and becomes a case only after a service line is created.
- [ ] Given `POST /api/service-contracts/{service}` for a new party, when it succeeds, then exactly one applicant, one contract, one `applicant_contract` line, one line stage/history row and one line delivery task exist, no applicant-level stage/history row is written, and the new case appears once in Applications.
- [ ] Given an existing party of the same service selected explicitly in the service-contract form, when the contract is created, then the existing party is reused, no duplicate applicant is created, and exactly one new line/case is added.
- [ ] Given an existing party of a different service or an unapproved cross-service request, when the contract is submitted, then the request is refused with a field-level explanation and no duplicate party, contract or line is created.
- [ ] Given a party with two contracted service lines, when Applications -> Applications renders, then it has two rows, one per line, each showing its own service, contract number, line stage and payment state; an unlined party has zero rows.
- [ ] Given any Applications row, when Open case is used, then it opens `/service-cases/{line}` for that row and never opens `/applicants/{id}` as a case.
- [ ] Given the Applications filters, search, stage tabs, pagination and print, when they are used, then every result, count and printed row is line-derived; a stage filter uses the line stage and an unlined party is never counted as a case.
- [ ] Given a viewer with case access, when the service-case workspace renders, then it offers the party's call/email actions, CRM details, portal controls, profile editing, profile documents, notes and university applications subject to the existing permissions, alongside only that line's stage, history, tasks and receipts.
- [ ] Given a viewer with `create_tasks`, when a task is created from the service case, then the task stores the open `applicant_contract_id`, appears in that line's task list and the shared wrapper/board view, and no duplicate line-less task is created.
- [ ] Given a task create request whose `applicant_contract_id` belongs to another applicant or is outside the actor's case scope, when it is submitted, then it is refused and no task is created; a valid line request resolves the applicant server-side.
- [ ] Given a viewer with the existing income/payment grant, when Record payment is used from the service case, then the existing income/service-payment flow opens with the applicant and line preselected, the receipt is written through `IncomeController`/`ApplicantContractPaymentService`, and the line balance and case receipts update without a second finance endpoint or duplicate form.
- [ ] Given the wrapper page and API, when the change is complete, then no applicant-level stage select, note, record button, terminal dialog, timeline or Open case link remains; no new `Case created` or applicant-level `Contract created` history row is produced; `/applicants/{id}` is a party profile only.
- [ ] Given dashboard, reports, search summaries and case links, when they render, then they use service lines as cases and do not present `applicants.current_stage_id` or an unlined party as an editable or counted case.
- [ ] Given the three frontend locales, the three backend catalogs and the affected screens, when `npm run typecheck`, `npm run i18n:check`, the focused feature suites and the 390/768/1280 px LTR/RTL light/dark pass run, then all pass, no retired wrapper key has a producer, and no new copy is missing.

## Verification plan

- Automated: add `tests/Feature/ServiceCaseOnlyTest.php` covering party-only creation, new-party contract creation, explicit same-service party reuse, cross-service refusal, two-line Applications rows, unlined-party absence, line-stage filters, line-scoped task create/reject, finance reuse, wrapper-writer retirement and permission boundaries. Update the isolation, workspace, pipeline, contract-stage, task-integration and contract-generation suites. Run the focused suites with `php artisan test --filter=...`, then `npm run typecheck`, `npm run i18n:check` and `git diff --check`.
- Data checks: create one party with two scholarship lines and one unlined party in a non-production fixture; assert two Applications rows and zero for the unlined party; assert `applicant_stage_histories` has exactly the line rows created by the two contracts and no new applicant-level row.
- Contract checks: submit a new-party contract, an explicit existing same-service party contract and a cross-service attempt; assert applicant counts, line counts, stage history rows and the resulting Applications rows.
- Task checks: create a line task from the case page, assert `tasks.applicant_contract_id`, and assert the global wrapper/board sees the same task without a second row.
- Finance checks: record a line payment through the case shortcut, assert one `incomes` row with `applicant_contract_id`, one receipt number, the line balance change and the case list update. Do not create a new finance table or endpoint.
- Permissions: a viewer outside the case ownership scope, a viewer without `change_applicant_stage`, a viewer without `create_tasks` and a viewer without finance rights must each see the correct disabled/hidden state and receive the correct 403 on a direct request.
- Manual: 390, 768 and 1280 px in LTR and RTL under light and dark; the long line register, the full case workspace and the party profile must remain readable with no horizontal page overflow.
- Regression: pipeline, commission, receipts, documents/printing, portal access and CRM actions must keep their existing behaviour. Report any check that cannot run, rather than claiming it passed.

## Implementation notes

Start by defining the line register contract and the new task/finance action contracts, because the list and the workspace must not diverge. Reuse the existing `ApplicantContractResource` line fields and the pipeline's line query patterns; do not create a second case table or a parallel case model.

For the Applications register, move the line query into `ApplicantController::index()` or a dedicated controller action and keep `GET /api/applicants` as the party read. Apply the existing `EmployeeScope` and `view_all_applicants` ownership rules to the line's applicant. Load only the relations the row needs; do not serialize every line's full finance payload. Count open tasks with a bounded aggregate. Keep the existing stage-tab and filter UI shape, but bind the stage list to the selected service and filter `applicant_contract.current_stage_id`.

For contract creation, add optional `applicant_id` to `StoreServiceContractRequest` only for a non-extension create. Load and authorize the existing party in `ContractGenerationService`, verify the service matches, and skip `createApplicant()`. Do not add fuzzy matching. In the service-contract form, add an explicit existing-party search/select that clears the identity fields or displays the selected party; the current form creates a party from the identity fields, so the two paths must not silently overwrite each other. Keep the portal-credentials path for a newly created party; for an existing party, direct the operator to the portal controls on the case.

Remove the applicant-level case creation from `ApplicantController::save()` and `ContractGenerationService::createApplicant()`: no `current_stage_id` assignment and no `Case created`/applicant-level `Contract created` history. Keep the line stage and the line history row in `ContractGenerationService::create()`. Update `VisitorController::convertToApplicant()` the same way.

Move the wrapper capabilities into `service-case.vue` by reusing existing components and API clients, not by copying their logic. The page may call the party endpoints for profile documents, notes, universities and portal actions because those are party-level. Keep the line stage/history/tasks/receipts fed only by `GET /api/applicant-contracts/{line}`. Extract a shared task dialog only if the wrapper and case still both need it after the flattening; otherwise keep one implementation on the case page.

For task creation, extend `TaskController::store()` with `applicant_contract_id` validation, resolve the applicant, enforce `EmployeeScope` and `view_all_applicants`, and persist the line. Return the line id in `TaskResource`. The service case sends both ids; the board/wrapper remains compatible.

For finance, do not add routes or services. Extend `ServicePaymentsDialog` with optional initial applicant/line props, or pass query context into `/income` and open the existing dialog. The create path must remain `createIncome()` with `applicant_contract_id`; the receipt and balance stay owned by `IncomeController` and `ApplicantContractPaymentService`.

Retire the wrapper by removing the applicant-level stage state, transition computeds, dialogs, APIs and timeline from `applicant-case.vue`, and remove the Open case action from the Applications register. Keep `/applicants/{id}` as a party profile reached from the case header; if the route is removed entirely, update the dashboard/report links in the same change. Audit every `current_stage_id` reader with a repository search and convert case-facing readers to the line stage; leave only legacy compatibility fields that no UI uses.

Do not drop `applicants.current_stage_id` or delete `applicant_stage_histories` in this task. Record the retained legacy rows and the deprecated column in the handoff. If a schema change is required for cross-service reuse, stop and write a follow-up decision rather than inventing a party merge.

## Risks and rollback

- **Cross-service identity is unresolved.** Reusing one party across different services is not supported by the current single `applicants.service` and detail relation. Mitigation: explicit same-service reuse only; fail clearly on cross-service; do not merge parties. If the product requires cross-service parties, stop and design the identity/detail change first.
- **Legacy applicant history is ambiguous.** Existing `applicant_stage_histories` rows without a line cannot always be mapped to one case. Mitigation: retain them as read-only audit data, do not display them as a case timeline and do not delete them. A separate audited backfill can be approved later.
- **Applications API consumers may expect applicant rows.** Mitigation: freeze the new line-row shape in tests and update every consumer in the same change; keep `GET /api/applicants` for party lookups.
- **Party-level data looks case-exclusive.** Documents, notes, universities, portal and call history are shared. Mitigation: label these sections as party data on the case page; do not copy them per line.
- **Dashboard/report counts regress.** Mitigation: convert case-facing `current_stage_id` reads to line stages and add regression tests for service-stage totals and stale-case links.
- **Finance shortcut becomes a second ledger.** Mitigation: reuse `createIncome` and `IncomeController`; no new table, route or calculation. If the shortcut cannot reuse them, drop the shortcut and link to `/income` instead.
- **Rollback.** This is primarily a code/read-model change, so rollback is redeploying the previous build. Do not delete legacy history or issued documents. If a data backfill is added despite the boundary, it must be reversible and report affected rows; the task must stop before an irreversible merge.
- **Stop conditions.** Stop if cross-service reuse is required, if a line cannot be given a stage for an existing contract, if an applicant-level history row must be destructively reassigned, or if the income flow cannot be reused without changing finance semantics.

## Handoff checklist

- [ ] Exclusive file ownership was respected; shared route/locale edits are recorded.
- [ ] Every acceptance criterion has evidence in `Progress.md`, not just code written.
- [ ] The two-line and unlined-party data checks are recorded with real fixture results.
- [ ] The task/finance contracts state that finance is reused, not duplicated.
- [ ] Legacy `applicants.current_stage_id` and applicant-level history handling is recorded.
- [ ] Cross-service reuse, party-level data scope and any unrun viewport/RTL check are explicit limitations.
- [ ] The task is ready for review/integration, not merely code complete.
