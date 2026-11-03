# Task Task-001 — Make the case stage authoritative and gate transitions

## Metadata
- Task ID: `Task-001`
- Title: `Make the case stage authoritative and gate transitions`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack Laravel/Vue implementer`
- Estimated size: `L`
- Source plan/version: `issues/review/REPORT.md` §4 P1-1 and P1-2; `issues/review/issues/01-case-stage-divergence/issue.md`; `issues/review/issues/02-document-and-close-gate/issue.md`; `issues/review/issues/04-payment-status-hidden/issue.md` case-list slice; `issues/review/issues/11-audit-trail/issue.md` timeline slice; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-01`, `ISSUE-02`, `ISSUE-04-CASE`, `ISSUE-11-TIMELINE`

## Objective
One documented stage truth drives the case header, list, filters, pipeline counts and task creation, and the server refuses forward transitions without a labeled document and terminal transitions without a reason.

## Context and problem
`applicants.current_stage_id` and `applicant_contract.current_stage_id` are both writable. `ApplicantController::changeStage()` updates only the applicant and writes history; `ApplicantContractStageController::changeStage()` updates one line and mirrors it onto the applicant. The applications list filters and displays the applicant field, while the pipeline renders one card per service line plus unlined applicants and counts lines per column but cases in its totals. Case #1 currently has applicant stage 6 and two scholarship lines at stage 1, which reproduces the report.

The case page says a labeled document is required before progressing, but `App\Services\ApplicantDocumentReadiness` has no call sites and `changeStage()` validates `note` as nullable. The UI therefore promises a control the server does not enforce.

The applications list renders a payment-status column, but `ApplicantResource` returns `payment_status` only when `applicantContracts` was loaded, and the index loads those relations only for `view_applicant_financials` holders. The result is an all-dash column for every other viewer.

## Scope
### Included
- Define and document the workflow unit: the contracted service line is the authoritative stage for that service; the applicant stage is a derived mirror of the most recently changed line, used only where no line exists.
- Make the applications list filter and the stage it displays come from the same expression, including when a service filter is applied.
- Reconcile pipeline column counts, card counts and header totals so labels state exactly what is counted.
- Label extension lines on pipeline cards with their role and parent contract; never present an extension as an independent primary case without that label.
- Enforce the document gate server-side on forward non-terminal transitions once a case leaves its initial stage.
- Enforce a mandatory note on terminal transitions (Cancelled, Withdrawn, Rejected and any other terminal stage).
- Return field-level validation errors and make the case UI disable or explain blocked transitions.
- Render the applications payment column only when the viewer may see financials; otherwise omit the column rather than show dashes.
- Show the actor and note for each stage-history entry in the case timeline.

### Excluded
- Contract-register payment, role and expiry columns (Task-003).
- Dashboard salary/report figures (Task-004).
- Shared table sticky columns and global icon-button naming (Task-013).
- New stage definitions, new terminal outcomes or changes to the allowed-transition graph.
- Backfilling historical stage decisions other than the mirror rule described below.

## Preconditions and assumptions
- The demo data is not a fixture; tests must create applicants, contracts, stages, attachments and users explicitly.
- `ApplicantStage` already exposes `is_terminal`, `sort_order` and `allowsTransitionTo()`; this task uses those, not a new stage engine.
- Assumption to validate during implementation: the initial stage for each built-in service is the stage with the lowest `sort_order` that is not terminal. If the catalogue does not expose that deterministically, add a scoped helper rather than a schema change.
- Assumption to validate during implementation: `applicant_attachments` is the labeled-document store used by `ApplicantDocumentReadiness::ready()`. If another attachment table is canonical, the readiness service must be corrected, not bypassed.

## Dependencies
- `Task-013` — the shared responsive table must expose mobile card fields and sticky action columns before the mobile application cards and desktop action column can be accepted.

## Contracts and boundaries
- Inputs/outputs: `POST /applicants/{applicant}/stage` and the service-line stage endpoint keep their paths and payload shape, adding only validation errors.
- Compatibility guarantee: `applicants.current_stage_id` remains populated and is never null after any line transition; existing dashboards and reports that read it continue to work.
- Error behavior: a blocked forward transition returns HTTP 422 with `errors.stage_id` or `errors.applicant_id`; a missing terminal reason returns HTTP 422 with `errors.note`.
- Permission/data scope: `assertCaseManageAccess` and the existing line-stage ownership checks remain in force; no new permission is introduced.
- Integration seam: both controllers call the same readiness and terminal-reason guard service; no duplicated rule text.
- Coordination: the contract-register payment presentation is owned by Task-003; this task only covers the applications list.
- Files this task must not modify: `resources/ts/pages/contract_list.vue`, `app/Http/Controllers/ContractController.php`, `resources/ts/utils/localization.ts`, `resources/ts/components/responsive-data-table/**`.

## Exclusive file ownership
- `None` — implementation was delivered and this task now holds no forward write ownership. On 2026-11-01 the visa-case review (`visa-cases-issues.md`) found VISA-01..VISA-14 still open in the case screens, so forward write ownership of `app/Http/Controllers/ApplicantController.php`, `app/Http/Controllers/ApplicantContractStageController.php`, `app/Http/Resources/ApplicantResource.php`, `app/Services/ApplicantDocumentReadiness.php`, `resources/ts/pages/applicants.vue`, `resources/ts/pages/applicant-case.vue`, `resources/ts/pages/applicant-pipeline.vue` and `resources/ts/api/applicants.ts` moved to Task-027. Task-001 keeps only read-only review of its three open visual criteria.

## Acceptance criteria
- [ ] Given a case with applicant stage 6 and two scholarship lines at stage 1, when the applications list is filtered by scholarship stage New, then every returned row displays New and the filter uses the same service-line stage expression that the row displays.
- [ ] Given the pipeline for one service, when the page renders, then the header count label, the number of cards and the per-column counts each state whether they count cases or service lines, and the displayed numbers reconcile with that definition.
- [ ] Given an extension contract line, when it appears on the pipeline, then its card names its role and parent contract number and cannot be mistaken for a second primary case.
- [ ] Given a case with zero labeled documents that is leaving its initial stage, when a forward non-terminal transition is submitted through the UI or a direct API request, then the request is rejected with a field-level error and no applicant stage, line stage or history row changes.
- [ ] Given at least one labeled document, when the same forward transition is submitted, then it succeeds once and its history row records the actor and the note.
- [ ] Given Cancelled or Withdrawn with a blank note, when the transition is submitted, then it is rejected and the case UI requires the reason and explains that terminal outcomes need one.
- [ ] Given a viewer without `view_applicant_financials`, when the applications list renders, then the payment column is absent; given a viewer with the permission, then the column shows the row's overall payment status or a defined empty value for a case with no lines, never a silent all-dash placeholder.
- [ ] Given a stage-history entry, when the case timeline renders, then it shows the actor name and the note in addition to the from/to stage and timestamp.
- [ ] Given a 390 px viewport, when the applications page renders, then the stage tab strip and the Applications/Visa reports sub-navigation remain reachable without clipping.

## Verification plan
- Automated: add or extend feature tests under `tests/Feature/` for gate rejection, gate success, terminal reason, filter/display alignment and payment-column permission. Run the focused files with `php artisan test --filter=ApplicantWorkflowTest` and any new test file, then `npm run typecheck`.
- Manual: use a seeded case with no documents, attempt each forward transition, attach one labeled document, retry; attempt each terminal outcome with an empty and a filled note. Check the list at 390, 768 and 1280 px in LTR and RTL.
- Data fixtures: create two applicants, one with two scholarship lines at different stages and one unlined; create stages through the existing catalogue seeder. Clean up after the run.
- Regression: confirm existing `ApplicantWorkflowTest`, `ApplicantPipelineTest`, `ApplicantContractStageTest` and `ApplicantCaseViewTest` still pass; confirm no direct write to `applicant_contract.current_stage_id` bypasses the mirror rule.
- Evidence: test output, a list screenshot showing filter and displayed stage agreeing, a pipeline screenshot with count labels, and a blocked-transition response body.

## Implementation notes
Prefer one guard object used by both controllers. Keep the terminal-note rule in the same service so the UI message and server message cannot drift. For the list, avoid two different stage concepts in one response: if a service filter is present, join the line stage for that service; if absent, use the mirrored applicant stage and document that choice in the endpoint comments. Do not delete the applicant mirror; other aggregates still read it.

## Risks and rollback
- Risk: gating all forward moves can freeze legacy cases that never had attachments. Mitigation: scope the gate to transitions leaving the initial stage, and provide an explicit backfill/exception decision before enabling in production.
- Risk: changing list filtering can alter dashboard tile counts. Mitigation: keep a rollback switch at the query level and verify each dashboard deep link after the change.
- Data migration: none required. The rollback is code-only; no stage rows are rewritten.
- Stop condition: if the stage catalogue cannot identify an initial stage deterministically, stop and record the decision instead of inventing one.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
