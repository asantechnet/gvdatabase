# Task Task-028 — Isolate each service case and remove the stage document gate

## Metadata
- Task ID: `Task-028`
- Title: `Isolate each service case and remove the stage document gate`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack Laravel/Vue implementer with case-workflow review`
- Estimated size: `L`
- Source plan/version: `User request 2026-11-04: each case must be separate, the unified service-workflow ledger must be removed, no attached file may be required to move a stage, and the application pipeline must enforce case/service isolation`; `visa-cases-issues.md` (2026-11-01 visa-case review); `docs/contract-as-case-plan.md` (2026-10-07 contract-as-case direction); `AGENTS.md` (contracts assign built-in services through `applicant_contract`, which owns fee, currency, rate, discount and payment state)
- Requirement / behavior IDs: `CASE-ISO-01` case identity, `CASE-ISO-02` applicant page has no unified stage ledger, `CASE-ISO-03` per-case workspace, `CASE-ISO-04` no document gate, `CASE-ISO-05` terminal reason preserved, `CASE-ISO-06` scope and permissions, `CASE-ISO-07` pipeline is line-only, `CASE-ISO-08` line-scoped stage writes, `CASE-ISO-09` no applicant-mirror placement, `CASE-ISO-10` truthful counts and copy

## Objective
Make one contracted service line (`applicant_contract`) the unit of a case: it owns its stage, history, delivery tasks and money, it is opened and moved from its own workspace, and the applicant record is a party profile that only lists its cases. Remove the unified service-workflow ledger from the applicant page and remove every document prerequisite from stage changes. Rebuild the application pipeline so every card is one service case and every move targets only that line.

## Context and problem
The current applicant page (`resources/ts/pages/applicant-case.vue`) renders a `serviceWorkflow` card that lists every contracted line on one applicant and gives each row its own stage select, reason field and record button. Because `ApplicantController::changeStage` rejects a lined applicant with `business.case_stage_managed_by_service`, that card is the only place a multi-service applicant can be moved. This is exactly the unified ledger the product owner rejected: one screen combines several independent cases, shows a combined stage timeline, and makes each case's next action depend on a list that also contains the other cases.

The second defect is the transition guard. `App\Services\ApplicantDocumentReadiness::assertTransition` rejects a forward move out of the initial stage unless `applicant_attachments` contains at least one labeled document. Both stage controllers call it, so the user sees "attach at least one labeled document" when moving a case, even though the approved logic does not require a file for a stage change. The product owner has now stated explicitly that no attached file is needed.

The third defect is the pipeline. `ApplicantController::pipeline` mixes contracted lines with "unlined" applicants, and `applicant-pipeline.vue` falls back to `ApplicantApi.changeStage(card.applicant_id, ...)` when a card has no `line_id`. That keeps a second case-level writer alive, places cards by `applicants.current_stage_id`, and makes a party without a sold service look like a case. The pipeline must contain service cases only, and a card move must always address the line.

Task-027 delivered the current line-stage endpoint, line identity fields and single-writer guard. This task keeps those foundations but reverses Task-027's unified service-workflow presentation and its labeled-document rule. Task-027 remains the delivery record for its other criteria; its forward file ownership moves here so the follow-up has one writer.

## Scope
### Included
- Define a service case as one `applicant_contract` line and make that definition explicit in the API, page and pipeline contracts.
- Remove the service-workflow card from `applicant-case.vue` completely: template, per-line stage state, stage option loading, move function, reason state, CSS and the stale locale keys that only served that card.
- Remove the combined stage timeline from the applicant page for a lined applicant; history becomes case-scoped.
- Replace the removed card with a read-only case list on the applicant page: one row per line with service, contract number, current stage, contract status, payment state and a link to that case's own workspace.
- Keep the applicant-level stage panel and timeline only for an applicant with zero service lines, where the applicant record is still the case.
- Add a dedicated service-case workspace at `/service-cases/:line` backed by a new read endpoint that returns only that line's data: applicant identity, service, contract identity, current stage and legal next stages, line-scoped history, line-scoped tasks and line finance.
- Use the existing `POST /api/applicant-contracts/{line}/change-stage` as the only stage writer for a line, and show the terminal-reason field on the case page.
- Remove `ApplicantDocumentReadiness` and the labeled-document prerequisite from both stage controllers, replacing it with a terminal-reason-only guard; keep applicant document upload/list/rename/delete as profile documents.
- Remove the document-gate copy, the disabled stage state based on missing documents, and the `business.applicant_document_required` producer and key.
- Make the pipeline endpoint return only non-cancelled `applicant_contract` lines of the selected service, remove unlined applicant cards and the applicant-level fallback writer, place cards only from the line stage or the service's start stage, and count service cases rather than applicants.
- Update the pipeline page to render line-only cards, open the service-case workspace, and always send moves to the line endpoint.
- Add focused feature coverage for zero-document transitions, terminal reasons, lined-case case-level refusal, case payload isolation, permissions and line-only pipeline behavior.
- Add the new case-workspace copy to English, Dari and Pashto, and remove copy that has no remaining producer.

### Excluded
- No schema change to `applicant_contract`, `applicants`, `applicant_stages` or `applicant_stage_histories`; no migration and no historical drift repair.
- No removal of the applicant-document feature itself; only its role as a stage gate is removed. Task attachments and contract artwork are untouched.
- No change to fee, discount, currency-rate or payment calculations; the case page reads the existing line finance values.
- No restructuring of task creation, task completion, payment requests, commission accrual, payroll or printed documents.
- No conversion of the `/applications` list from a party directory into a line-level register; this task keeps the list as the party index and puts case actions on the case workspace.
- No new permission, no permission rename and no change to dashboards or reports that read `applicants.current_stage_id`.
- No removal of the applicant stage mirror column; it remains a derived compatibility field that the UI no longer presents as an editable stage for a lined applicant.
- No bulk-move or multi-case selection feature; isolation deliberately trades bulk movement for one-case-at-a-time control.

## Preconditions and assumptions
- The product owner's model is accepted: an applicant is a party/customer, and each contracted built-in service is an independent case. An applicant with two services therefore has two cases, two stages, two histories and two financial positions.
- The chosen case route is `/service-cases/:line`, where `:line` is the `applicant_contract` primary key. If the implementation finds a stronger existing naming convention, it may change the path label, but the contract remains one line per workspace and one line id in the URL; record any deviation.
- An applicant with no service line still behaves as a single case at the applicant level until a service is sold. This keeps intake work possible without reopening the unified ledger.
- The terminal-stage written reason is a separate audit rule from the document gate. The product owner objected to the attached-file requirement, not to the closing reason, so the terminal-note rule stays.
- The applicant page may keep read-only aggregate sections such as profile, all tasks, all posted finance and notes. The isolation rule governs stage and history writes: no control on the applicant page may move a line or write a line history row.
- The pipeline is a case board, not an intake board. A party without a sold service line stays on `/applications` and is absent from the pipeline until a line exists. If the product owner wants an intake lane, that needs a separate decision.
- `applicant_attachments` is the profile-document store. It remains available, but no stage path consults it.
- Demo data is not a fixture. Feature tests create applicants, lines, stages, documents, tasks, users and permissions explicitly.

## Dependencies
- `Task-027` — delivered the line-stage endpoint, `ApplicantContractResource` identity fields, the lined-case single-writer guard and the current workspace baseline. This task removes Task-027's unified service-workflow card and labeled-document rule and takes forward ownership of the affected files.
- `Task-018` — the single API-failure feedback contract. The new case page and the pipeline consume it and must not add a second generic error toast.

## Contracts and boundaries
- Case identity: one `applicant_contract` row is one case; its primary key is the case id used by the route, payload and history rows. A contract with several service lines yields several cases, not one case with sub-cases.
- Read endpoint: `GET /api/applicant-contracts/{line}` returns `success` and `data` for exactly one line. It includes the line's service, contract number, contract role and parent number when applicable, current stage, allowed next stages, line history, line tasks and line finance, plus the applicant identity needed to label the page. It must not include sibling line ids, sibling history rows, sibling tasks or sibling balances.
- Read scope: the endpoint requires `view_applicants` and the same applicant-ownership scope used by `GET /applicants/{id}`; `view_all_applicants` or super-admin bypasses ownership. A missing line returns 404 and an out-of-scope line returns 403.
- Write endpoint: `POST /api/applicant-contracts/{line}/change-stage` keeps its path and `stage_id`/`note` payload, keeps `change_applicant_stage`, keeps the line's ownership scope, keeps same-service stage validation and allowed-transition validation, and keeps field-level errors. It no longer calls any document-readiness check.
- Terminal rule: a terminal target with a blank or whitespace-only `note` is rejected with `errors.note`; with a note the transition writes one line history row with the actor, note and timestamp. No document rule runs before or after it.
- Applicant endpoint: `POST /api/applicants/{id}/change-stage` remains the writer only for an applicant with zero service lines. For a lined applicant it continues to return 422 `business.case_stage_managed_by_service` before any write. It also drops the document check.
- Applicant page: for a lined applicant the page renders no service-workflow select, no per-line reason field, no per-line record button and no combined stage timeline. It renders one read-only case row per line and a link to `/service-cases/:line`.
- Case page: the page loads only `GET /api/applicant-contracts/{line}` and the selected service's stage catalogue. It does not call `GET /applicants/{id}` to obtain the case, so the page cannot accidentally expose sibling data.
- Pipeline contract: `GET /api/applicants-pipeline?service=...` returns one card per non-cancelled `applicant_contract` line of that service. Every card has `source = line` and a non-null `line_id`. No "unlined applicant" card is produced. Card placement uses the line's current stage when it belongs to this service, otherwise the same stage key in this service's live list, otherwise the service's `new` or first stage. It never reads `applicants.current_stage_id` for placement.
- Pipeline moves: a card drag or menu action sends exactly one request to the line change-stage endpoint. There is no fallback to the applicant change-stage endpoint. A sibling line on the same applicant keeps its stage and history.
- Pipeline counts: column counts and header totals count service-case lines for the selected service and the current filters. `counts.unit` names a service case, not an unlined applicant. No header label may describe a party without a line as a case.
- Document contract: no stage controller, service, pipeline move or case page may call `ApplicantDocumentReadiness`, `ready()`, `require()` or any `applicant_attachments` existence check. Applicant attachments remain readable and writable through their existing endpoints and permissions.
- Permissions: no new permission is added. `view_applicants` gates case reads and the pipeline; `change_applicant_stage` plus the existing case-manage scope gates a stage write. The case page hides or disables a control the viewer cannot use instead of relying on a 403 after submission.
- Shared files: `routes/api.php` is owned by Task-009 and the frontend locale JSON plus the PHP language catalogs are owned by Task-026. This task only adds the one read route next to the existing applicant-contract stage route and its own key subtree, adds the same keys to all three frontend locales, and does not reformat those files.
- Stage options: when the case workspace or the unlined applicant panel loads its stage catalogue, it sends `line_id` (for a service case) or `applicant_id` (for an unlined applicant) to `GET /api/applicant-stages`. The stage-scope task owns the server-side filtering of that read; this task owns the request site and the `ApplicantApi.stages` parameters. The page must not re-derive test, country or university applicability locally, and it must not use the unfiltered `allowed_next_stages` array as its movable-option list for a test-offer case; the scoped read is authoritative.
- Files this task must not modify: `app/Services/ContractTaskService.php`, `app/Http/Resources/ApplicantResource.php`, `resources/ts/pages/applicants.vue`, `resources/ts/pages/applicant-stages.vue`, `app/Http/Controllers/ApplicantStageController.php`, payment services and document-print utilities.

## Exclusive file ownership
- `app/Http/Controllers/ApplicantController.php` — remove the document check from the unlined-case writer and make the pipeline line-only with line-derived counts and placement.
- `app/Http/Controllers/ApplicantContractStageController.php` — add the single-case read action, remove the document check, keep the line-scoped stage writer and field-level errors.
- `app/Services/ApplicantDocumentReadiness.php` — remove the document-readiness service and its call sites.
- `app/Services/ApplicantStageTransitionGuard.php` — the replacement guard that owns only the terminal-reason rule.
- `app/Http/Resources/ApplicantContractResource.php` — expose line-scoped history, tasks and finance for the case payload without adding sibling data.
- `resources/ts/pages/applicant-case.vue` — remove the unified service-workflow card, per-line stage state and combined timeline; add the read-only case list.
- `resources/ts/pages/service-case.vue` — the new single-case workspace with its stage writer, history, tasks and finance.
- `resources/ts/pages/applicant-pipeline.vue` — line-only cards, line-only moves and service-case links.
- `resources/ts/api/applicants.ts` — update the pipeline card type to the line-only contract.
- `resources/ts/api/serviceCases.ts` — typed single-case read and move methods.
- `resources/ts/plugins/1.router/routes.ts` — register `/service-cases/:line` and its page component in the static router.
- `tests/Feature/ServiceCaseIsolationTest.php` — new coverage for case isolation, zero-document moves, terminal reasons, permissions and line-only pipeline.
- `tests/Feature/ApplicantWorkflowGateTest.php` — replace the document-gate expectations with zero-document success while keeping the terminal-reason and lined-case-refusal assertions.
- `tests/Feature/VisaCaseWorkspaceTest.php` — replace the service-workflow row expectations with the read-only case list and single-case workspace.
- `tests/Feature/ApplicantPipelineTest.php` — update expectations from mixed line/unlined cards to line-only service cases.
- `tests/Feature/ApplicantContractStageTest.php` — remove gate-only document seeding and assert that a line moves without any attachment.

## Acceptance criteria
- [ ] Given an applicant with two contracted service lines, when `/applicants/{id}` renders, then the service-workflow card is absent: no stage select, reason field, record button or combined stage timeline is rendered for any line, and the page shows one read-only case row per line with its service, contract number, current stage, contract status and payment state plus a link to that case.
- [ ] Given the same applicant, when a case row is opened, then `/service-cases/{line}` displays only that line's applicant identity, service, contract, current stage, allowed next stages, stage history, line tasks and line finance, and no sibling line id, history row, task or balance is present in the payload or the page.
- [ ] Given a service line at its initial stage with zero `applicant_attachments`, when a legal forward transition is submitted from the case page or `POST /api/applicant-contracts/{line}/change-stage`, then it succeeds, writes exactly one line history row and returns no document-required error.
- [ ] Given an applicant with no service line and zero `applicant_attachments`, when a legal forward transition is submitted to `POST /api/applicants/{id}/change-stage`, then it succeeds with no document check and the case-level writer remains available only for that unlined applicant.
- [ ] Given a terminal target with a blank note, when it is submitted from either stage endpoint, then the request is rejected with `errors.note` and writes no stage or history row; with a trimmed note it succeeds once and stores the note.
- [ ] Given a viewer who holds `view_applicants` but is outside the case ownership scope and lacks `view_all_applicants`, when the case page or `GET /api/applicant-contracts/{line}` is requested, then access is refused with 403 and no case data is returned; the same viewer with `view_all_applicants` can read it.
- [ ] Given a viewer without `change_applicant_stage`, when the case page renders, then no enabled stage control is offered, and a direct change request is refused with 403; a viewer with the grant and case scope can move the line.
- [ ] Given a lined applicant, when `POST /api/applicants/{id}/change-stage` is called directly, then it still returns 422 `business.case_stage_managed_by_service` and changes no applicant stage, line stage or history row, even though the applicant page no longer offers that control.
- [ ] Given `GET /api/applicants-pipeline?service=scholarship`, when the board renders, then every card is one non-cancelled `applicant_contract` line of that service, an applicant with two lines produces two cards, and an applicant with no line produces no card.
- [ ] Given a pipeline card, when it is moved or the move menu is used, then exactly one request is sent to `POST /api/applicant-contracts/{line}/change-stage`, the moved line changes stage and gains one history row, and every sibling line on the same applicant keeps its stage and history.
- [ ] Given a line with no current stage or a stage outside the selected service's live list, when the pipeline places it, then it appears under the service's `new` or first stage and never under a stage read from `applicants.current_stage_id`.
- [ ] Given the pipeline header and columns, when counts render, then `counts.cards`, the sum of column counts and the totals all count service-case lines for the selected service, and no label or payload unit claims unlined applicants are cases.
- [ ] Given the applicant Documents section, when it renders, then upload, list, rename and delete still work for profile documents, but no copy, disabled state or server rule states that a document is required to change a stage, and `business.applicant_document_required` is no longer produced.
- [ ] Given the three frontend locales and the three backend catalogs, when `npm run i18n:check` and a key search run, then the new case-workspace keys exist in all three frontend locales, the removed gate/workflow keys have no remaining producer or reference, and no locale key is missing.
- [ ] Given 390, 768 and 1280 px viewports in LTR and RTL under light and dark themes, when the applicant page case list, the service-case workspace and the pipeline board render, then stages, links, history and finance values remain reachable and legible without horizontal page overflow.

## Verification plan
- Automated: add `tests/Feature/ServiceCaseIsolationTest.php` covering the no-document forward move on a line and on an unlined applicant, the terminal-note rejection and success, the lined-case case-level 422, the single-case payload with no sibling ids, the read/write permission boundaries and a line-only pipeline with two lines on one applicant. Extend the existing gate, workspace, pipeline and contract-stage suites for the removed workflow card and removed document seeding. Run `php artisan test --filter=ServiceCaseIsolationTest`, then the four updated suites, then `npm run typecheck` and `npm run i18n:check`.
- Regression: the applicant, contract-stage, pipeline, dashboard and commission suites must keep passing. Confirm that a line move still updates the applicant mirror for dashboards and reports even though the UI no longer uses the mirror as an editable stage.
- Manual: open a two-line applicant and confirm the page has no workflow selects and one link per case; open each case and confirm history/tasks/finance belong to that line only; move a line with zero documents; attempt a terminal move with a blank and a filled note; drag a pipeline card and confirm only that line moves; sign in as a viewer outside the case scope and as a viewer without the stage grant.
- Pipeline check: request `GET /api/applicants-pipeline?service=scholarship` for an applicant with two scholarship lines and for an applicant with no line; confirm two `line:` cards and no applicant card; inspect that every card has `source = line` and a non-null `line_id`.
- Viewports and direction: 390, 768 and 1280 px in LTR and RTL, light and dark, for the applicant case list, the service-case workspace and the pipeline.
- Fixtures: one applicant with a scholarship line and an academy line, one line at its initial stage with no attachments, one unlined applicant, one line with a missing or foreign-service stage, one viewer with `view_applicants` but outside ownership, one viewer with `view_all_applicants`, and one viewer without `change_applicant_stage`. Non-production only.
- Evidence: test names and assertion counts, the case payload showing no sibling ids, the pipeline JSON with two line cards and zero applicant cards, and the 422/403 response bodies for the negative cases.

## Implementation notes
Remove the service-workflow block and every symbol that exists only for it (`loadLineStages`, `lineStageOptions`, `lineStageSelection`, `lineStageNotes`, `movingLine`, `lineNextStages`, `lineNextStage`, `lineNeedsReason`, `lineStageLabel`, `moveLine`, `.case-section--workflow`, `.case-service-line`). Replace it with a plain list driven by `applicant.services`; do not add a second stage concept. Keep the existing applicant-level panel and timeline behind a zero-line guard so an intake record can still move before a service is sold.

Add the single-case action to `ApplicantContractStageController` rather than a new controller: load `applicant`, `currentStage`, `contract`, `currency`, `stageHistory`, line tasks and line payments, enforce the same ownership helper as the line writer, and return the existing `ApplicantContractResource` with conditional relations. Do not return the whole applicant payload. The page should read the applicant's display name and contact from that resource field, not from `ApplicantApi.get`.

Replace `ApplicantDocumentReadiness` with `ApplicantStageTransitionGuard` that exposes the terminal-note rule only. Delete the document methods and the `applicant_document_required` language key. Keep the attachment model, controller, routes and Documents section; change the subtitle to a neutral document count and remove gate language.

In the pipeline controller, remove `unlinedApplicants()` and `pipelineApplicantCard()`, make `placementStageId()` accept only the service stage list and the line's preferred stage, and derive card status from the line's terminal state rather than `applicants.status`. Keep the existing applicant filters so scope, service and search still narrow the line query. Update the card count and totality labels to service cases. In the page, remove the `card.line_id ? ... : changeStage(...)` branch, the `source` branching and the applicant-id case link; every card opens `/service-cases/{line_id}`.

Use the static router in `resources/ts/plugins/1.router/routes.ts` and add the page component to the component map next to the applicant case page. Use one `ServiceCaseApi.get(line)` and the existing `ApplicantApi.changeLineStage` or a typed equivalent in `serviceCases.ts`. Reuse the terminal-dialog pattern from the old line row on the new page; do not duplicate the response-unwrapping logic in the component.

Add the new keys under the existing `educational.caseUi` subtree in all three frontend locales, and remove only the keys this task retires. Do not reformat the catalogues and do not touch Task-026's org-scope keys. Add the route to `routes/api.php` immediately beside the existing `applicant-contracts/{line}/change-stage` route so the read and write paths stay together.

## Risks and rollback
- Risk: unlined applicants disappear from the pipeline board, so intake staff lose a visual queue. Mitigation: the applications list remains the party index and the task documentation states that a sold service line is required to enter the case pipeline; an intake lane is a separate feature if needed.
- Risk: removing the document gate allows a case to advance without documents. The product owner explicitly requested this; record the compliance trade-off in the handoff and do not add a hidden client-side check.
- Risk: per-case movement is slower than the removed bulk ledger. This is the intended isolation trade-off; do not reintroduce a multi-line writer as a convenience.
- Risk: the applicant mirror can still disagree with the lines. Mitigation: the UI never presents the mirror as an editable stage for a lined applicant and no history or stage row is rewritten; existing drift is explained, not repaired.
- Risk: changing the pipeline payload breaks a consumer that expects `source = applicant` or `line_id = null`. Mitigation: search every pipeline consumer, update the typed contract, and keep the route and query parameters frozen.
- Risk: a new read route is added in a file shared with Task-009. Mitigation: add only the single route next to the existing line route, do not reorder unrelated routes, and record the shared-file edit in the progress log.
- Data migration: none. This is a code-only change; rollback is redeploying the previous build, which restores the old UI and gate but not any data that was written under the new rules.
- Stop conditions: if the product owner actually wants the lined applicant to keep a working case-level writer, stop because that contradicts case isolation; if the single-case read requires a schema change, stop and record the need instead of adding a migration inside this task.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] The new route and shared-file edits are recorded with their owners.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
