# Task Task-027 — Make the visa case workspace report one workflow truth

## Metadata
- Task ID: `Task-027`
- Title: `Make the visa case workspace report one workflow truth`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack Laravel/Vue implementer with case-workflow review`
- Estimated size: `L`
- Source plan/version: `visa-cases-issues.md` (2026-11-01 business-logic review, VISA-01..VISA-14); `docs/ai/contracts-applications-merge-and-per-service-stages.md` (AC3, AC7 and the 2026-10-18 service-line decision); `docs/visa-case-enhancements-plan.md`
- Requirement / behavior IDs: `VISA-01`, `VISA-02`, `VISA-03`, `VISA-04`, `VISA-05`, `VISA-06`, `VISA-07`, `VISA-08`, `VISA-09`, `VISA-10`, `VISA-11`, `VISA-12`, `VISA-13`, `VISA-14`

## Objective
The visa case workspace and the applications list show one consistent workflow: one stage writer per case, a timeline that says which workflow each row belongs to, a finance card that matches the case's payment state, and document, task and permission states that do not contradict each other.

## Context and problem
The 2026-11-01 review of `/applications`, `/applicants/:id` and `/applicant-pipeline` found fourteen defects (VISA-01..VISA-14) that survive the earlier Task-001 work. The dominant one is VISA-01: the case page still renders the legacy applicant-level "Move case to stage" panel next to the per-service workflow rows. The service line is the approved authority for the current stage, and the applicant stage is a compatibility mirror for dashboards and reports, yet the page offers the mirror as an equally prominent write control. A case-level move writes only `applicants.current_stage_id` and a history row with `applicant_contract_id = NULL`; it does not move a line, does not sync the delivery task and creates the drift already present in the demo data (case #6 is `cancelled` while its only line is `new`; case #1's mirror says `consultation` while extension line `CTR-26-002` is `new`).

Task-001 built the shared transition guard, aligned the list filter with the row stage and exposed the pipeline counts, but the review found that the page still offers both writers, that the per-line form cannot send the terminal reason the server requires, that the stage deep link is dropped, that history rows carry no line identity, that the same-service rows are indistinguishable, that the documents section mixes two stores, that the Finance card reports no activity on a partially paid case, and that the two stage controls use different permission checks. Task-001's forward write ownership is therefore handed to this task; its remaining three acceptance criteria are register-and-render checks that this task's verification covers.

## Scope
### Included
- Remove the case-level stage-write control for a case that has at least one contracted service line and serve the aggregate stage as a labelled, read-only mirror; keep the case-level control as the only writer for a case with no line.
- Reject a case-level stage request for a lined case at the API boundary so the UI cannot be bypassed, and keep the mirror updating from line moves exactly as today.
- Give the per-service stage row a required reason for terminal targets, matching the server rule, and surface the field-level error in that row.
- Resolve the list stage after the stage catalogue loads, so `service` and `stage` deep links select the tab and the Stage column shows the stage that satisfied the filter.
- Expose and render the contract line (number, primary or extension with parent, fee, status, payment state) on every service-workflow row and on each timeline history row; label case-level history explicitly.
- Show the case's line-level money position in the Finance card and label posted-ledger totals for what they are.
- Reconcile closed-case open work: name the line and the reason each open task remains open, and stop the case-level writer from creating new unreconciled work.
- Use one permission and scope check for every stage control, and hide or disable a control the viewer cannot use instead of returning 403 after submission.
- Make the documents section state which upload feeds the document gate and which list only holds task files.
- Make the default applications-list stage tabs coherent for one context, and label a case with no service line explicitly on the list and in the workspace.
- Make task succession readable when one line has a completed task and a new open successor with the same title.
- Fix the visible copy defects (raw enum, generic page title, note label that overstates the requirement, university primary choice versus tracked applications).
- Fix the accessibility defects on document row actions, service stage selects and task completion.
- Add focused feature coverage for the guards, endpoints and payloads introduced here.

### Excluded
- Pipeline count labels and extension chips; Task-001 delivered those and the review found no defect there.
- Contract register payment, role and expiry columns (Task-003).
- Dashboard and report money definitions (Task-004).
- Shared responsive-table primitives and global icon-button conventions (Task-013).
- API failure feedback, toast precedence and inline-error plumbing (Task-018); this task consumes that contract and adds no second error surface.
- New stage definitions, new terminal outcomes, changes to the allowed-transition graph, or a new stage-history table.
- Repairing historical drift rows for cases #1 and #6 by rewriting stage or history records; the workspace must explain existing drift, not silently rewrite it.
- Payment requests, receipt printing and commission accrual.

## Preconditions and assumptions
- The approved 2026-10-18 decision stands: the contracted service line owns the stage, and `applicants.current_stage_id` is a derived mirror for aggregate screens. This task implements that decision rather than reopening it.
- Assumption for the product owner to confirm before the UI change ships: a case that has at least one contracted line can no longer be moved from the case-level control, and the server rejects that request with a 422 business error. The alternative (keep the control but write through to every line) is a different decision and is out of scope; if the product owner prefers it, stop and record the decision before implementing.
- The per-service reason field reuses the existing terminal-note rule in `App\Services\ApplicantDocumentReadiness`; no new validation vocabulary is introduced.
- `presentService()` today returns the contract number, fee, contract status and current stage, but not the line's paid, remaining or payment status, and not the extension role or parent number (those exist only on the pipeline card). `resources/ts/api/applicants.ts` already declares `payable_total`, `amount_paid`, `remaining_total` and `payment_status`, so the endpoint must be brought up to its own declared type from `ApplicantContractPaymentService` instead of duplicating money arithmetic in the page.
- `applicant_attachments` remains the canonical labeled-document store for the document gate; task attachments stay in the task-file store and are not merged into it.
- Demo data is not a fixture. Feature tests create applicants, service lines, stages, documents, tasks and users explicitly, and must not depend on seeded case ids.
- The application must keep working for MySQL and the SQLite test database. This task needs no schema change; if an implementation detail turns out to need one, stop and raise it instead of shipping a migration inside this task.

## Dependencies
- `Task-001` — the shared transition guard in `ApplicantDocumentReadiness`, the list `display_stage` expression, the permission-gated payment column and the pipeline contract notes are the baseline this task extends. Task-001 also hands forward write ownership of the case-screen files.
- `Task-018` — the single API-failure feedback contract. This task removes page-level duplicate toasts rather than adding a new error surface.

## Contracts and boundaries
- Stage writer: for a case with one or more `applicant_contract` lines, `POST /applicants/{applicant}/change-stage` is rejected with HTTP 422 and a business message, and writes no stage and no history row. For a case with no line, it keeps today's behaviour: it writes `applicants.current_stage_id` and one history row with `applicant_contract_id = NULL`.
- Mirror guarantee: a line move continues to update `applicants.current_stage_id` and every dashboard, report and list reader that uses the mirror keeps working; no reader is repointed and no column is dropped.
- Line transitions: `POST /applicant-contracts/{line}/change-stage` keeps its path and payload, accepts and stores a trimmed `note`, and returns the existing field-level errors (`errors.stage_id`, `errors.note`).
- History payload: each history row exposes its contract line identity (`contract_number`, `contract_role`, `parent_contract_number`) or an explicit case-level flag, plus actor, note and timestamp. The timeline must not infer identity from ordering.
- List contract: `service` and `stage` query parameters stay the deep-link contract used by the dashboard and pipeline. A stage value that does not exist for the selected service is resolved once the catalogue loads, not silently discarded.
- Finance contract: the Finance card states line fee, paid, remaining and payment status from the line data, and separately labels posted income and posted expense totals. It must never claim no financial activity while a line carries a payment state.
- Permission contract: `change_applicant_stage` plus the existing case-manage scope decide whether any stage control is offered; the line endpoint keeps its existing ownership and scope checks. No new permission is introduced.
- Error behaviour: a blocked transition returns 422 with a field-level error; a permission failure returns 403 and the UI must not have offered the control. Success copy names the case and the stage.
- Integration seams: `app/Services/ApplicantDocumentReadiness.php` stays the only transition and terminal-reason rule; `app/Services/ContractTaskService.php` stays the only line-task synchroniser and is not edited here.
- Copy and locale: new keys follow the Task-026 freeze rule, are added to `en`, `fa-AF` and `ps-AF` with identical key paths, and use the existing `common.ui.*` / `pages.*` conventions. The locale catalogues and `lang/*/api.php` are shared catalogues, not exclusive ownership of this task.
- Files this task must not modify: `resources/ts/pages/applicant-pipeline.vue`, `app/Services/ContractTaskService.php`, `app/Http/Resources/ContractResource.php`, `resources/ts/pages/contract_list.vue`, `resources/ts/components/responsive-data-table/**`, `resources/ts/api/axios.ts`, `resources/ts/components/ApiErrorNotifier.vue`.

## Exclusive file ownership
- `app/Http/Controllers/ApplicantController.php` — case payload, timeline identity, legacy-writer guard and line money exposure.
- `app/Http/Controllers/ApplicantContractStageController.php` — line transition note and field-level errors.
- `app/Http/Controllers/ApplicantStageController.php` — coherent stage catalogue for the list context.
- `app/Services/ApplicantDocumentReadiness.php` — shared gate reused by both controls.
- `app/Http/Resources/ApplicantResource.php` — stage, history and finance fields for the list and workspace.
- `app/Models/ApplicantStageHistory.php` — owning contract-line accessor for history rows.
- `resources/ts/pages/applicant-case.vue` — single stage writer, timeline identity, finance, documents, copy and accessibility.
- `resources/ts/pages/applicants.vue` — stage tab/deep-link resolution, display agreement and unlined labelling.
- `resources/ts/api/applicants.ts` — typed history, finance and stage payloads.
- `tests/Feature/VisaCaseWorkspaceTest.php` — new coverage for the review findings.
- `tests/Feature/ApplicantWorkflowGateTest.php` — extend the existing gate suite for the legacy-writer guard.

## Acceptance criteria
- [ ] Given a case that has at least one contracted service line, when the case workspace renders, then the only stage-write control is the per-service row, the case-level stage is displayed read-only and labelled as a mirror of the authoritative line, and a direct case-level stage request is rejected with 422 and changes no stage or history row.
- [ ] Given a case with no contracted service line, when the workspace renders, then the case-level stage control remains its single writer and no empty service-workflow card is shown.
- [ ] Given a terminal target and no reason, when a per-service row records it, then the row reveals a required reason field, disables record while blank, sends the reason with the request, and the server rejects a blank reason with `errors.note` and no write; with a reason the move succeeds once and stores the reason.
- [ ] Given the URL `/applications?service=scholarship&stage=consultation`, when the list loads, then the Consultation tab is selected after the stage catalogue resolves and every returned row shows Consultation in its Stage column, matching the row that satisfied the filter.
- [ ] Given history rows from one case-level move and one service-line move, when the timeline renders, then each row names its contract number or the explicit case-level label, so the two workflows cannot be read as one sequence.
- [ ] Given two scholarship lines on one case, when the service rows render, then each row shows its contract number plus primary or extension identity (with the parent number for an extension) and fee, status and payment state.
- [ ] Given a case whose payment status is Partially Paid, when the Finance card renders, then it shows per-line fee, paid, remaining and payment status, and it never states there is no financial activity while at least one line carries payment state; any posted-ledger total is labelled as posted transactions only.
- [ ] Given a case closed at a terminal stage that still has open delivery work, when the workspace renders, then each open task states its service line and why it remains open, and a case closed through the remaining case-level writer cannot leave a line task open without that explanation.
- [ ] Given a viewer who cannot move stages (missing `change_applicant_stage` or outside the case manage scope), when the workspace renders, then no stage control is enabled, and the same shared check gates both the case-level and the line-level controls instead of producing a 403 after submission.
- [ ] Given the applications list opens with no service selected, when the stage tabs render, then they come from one coherent context with no cross-service deduplicated strip, and the tab filter and the Stage column use the same stage definition as the visible tab.
- [ ] Given an applicant-document upload and a task-attachment upload, when the Documents section renders, then exactly one action feeds the document gate, the task files are labelled as files attached to tasks, and any dialog that does not satisfy the gate says so before upload.
- [ ] Given one service line produced a completed delivery task and a new open successor task with the same title, when the Tasks section renders, then each task shows its line and its successor or predecessor state so the duplicates read as a succession.
- [ ] Given the workspace renders, when labels are read, then the service name is humanized, the header names the actual case type rather than always Visa case, the note label states when a reason is genuinely required, and the university primary choice is distinguished from tracked applications.
- [ ] Given keyboard or screen-reader use of the workspace, when the controls are traversed, then every document icon action has an accessible name, each service stage select is labelled with its contract number, and task completion is gated with an explanation rather than a server error.
- [ ] Given 390, 768 and 1280 px viewports in LTR and RTL under both light and dark themes, when the case workspace and applications list render, then the stage controls, service rows, timeline and finance values stay reachable and legible without horizontal page overflow.

## Verification plan
- Automated: add `tests/Feature/VisaCaseWorkspaceTest.php` covering the lined-case 422, the unlined-case success, the terminal reason on a line, deep-link stage resolution, history identity, finance payload and the permission-gated controls; extend `tests/Feature/ApplicantWorkflowGateTest.php` for the legacy-writer guard. Run `php artisan test --filter=VisaCaseWorkspaceTest`, `php artisan test --filter=ApplicantWorkflowGateTest`, then `npm run typecheck`.
- Regression: the existing applicant, contract-stage, pipeline, dashboard and commission suites must keep passing, because the mirror and history payloads are shared. Confirm no reader of `applicants.current_stage_id` changed behaviour for a lined case that is moved through its line.
- Manual: open a lined case and confirm one writer; open an unlined case and confirm the case-level control; select a terminal target on a service row with a blank and a filled reason; follow a dashboard and pipeline deep link into `/applications`; read the timeline with mixed rows; compare the Finance card against the list payment status; sign in as a stage-permission holder outside the case scope.
- Viewports and direction: 390, 768 and 1280 px in LTR and RTL, light and dark, for the workspace and the list.
- Fixtures: one applicant with two scholarship lines (one primary, one extension), one unlined applicant at a non-terminal stage, one partially paid line, one closed case with an open delivery task, and one viewer with `change_applicant_stage` but no case-manage scope. Non-production only; no writes to the shared demo database.
- Evidence: test names and assertion counts, the 422 response body for a lined case-level request, a list screenshot showing tab and row stage agreeing, a timeline screenshot with mixed rows, and the finance values for the partially paid case.

## Implementation notes
Remove the case-level panel from the lined-case branch of the workspace template and replace it with a read-only stage summary that names the line the value comes from; keep the existing panel only for the unlined branch, so the page has one writer per case rather than two. Guard the legacy endpoint before it writes anything, and return the business message through the existing error channel so the page does not need a second handler.

For the list, resolve `stage` after the catalogue load (a watcher or a resolve step in the load sequence) and prefer the line expression already used by `display_stage`; do not introduce a third stage concept. For the timeline, extend the existing history serialisation rather than joining in the template.

Keep `ApplicantDocumentReadiness` as the only rule object: the line row should call the same rule the endpoint enforces, so the disabled state and the 422 cannot drift. Do not add a second permission check in the template; derive one `canMoveStage` from the existing authorisation helper plus the manage scope and use it for both controls.

Prefer plain presentation changes for finance and task identity; `presentService()` and the task payload already carry the fields. Do not edit `ContractTaskService` to change task creation; explain succession in the workspace instead.

## Risks and rollback
- Risk: rejecting the lined-case legacy endpoint breaks an integration or a script that still calls it. Mitigation: search every caller, keep the endpoint for unlined cases, return a specific business code, and record the change in the handoff; the mirror is still written by line moves.
- Risk: the read-only mirror is mistaken for a broken control. Mitigation: label it with the source line and keep the per-service rows visually adjacent, as the review recommends.
- Risk: deep-link resolution changes dashboard and pipeline landing behaviour. Mitigation: keep the query parameter names frozen and verify each deep link after the change.
- Risk: existing drift rows (cases #1 and #6) are shown differently once history identity and finance truth land. Mitigation: explain rather than rewrite; no history or stage row is edited by this task.
- Data migration: none. This task adds no column, no table and no backfill; rollback is code-only and re-deploying the previous build restores the prior behaviour.
- Stop conditions: if the product owner rejects the single-writer rule for lined cases, or if a required payload field is not available without a schema change, stop and record the decision instead of widening the task.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
