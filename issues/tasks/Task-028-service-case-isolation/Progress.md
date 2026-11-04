# Progress — Task-028

## Status snapshot
- Status: `Review`
- Owner: `Full-stack Laravel/Vue implementer`
- Last updated: `2026-11-04`
- Current focus: `Implementation complete; only the 390/768/1280 px LTR/RTL light/dark browser pass remains`
- Blocker: `None`
- Next action: `Walk the applicant case list, /service-cases/:line and the pipeline at 390, 768 and 1280 px in LTR/RTL under light/dark, then sign off.`

## Acceptance criteria progress
- [x] Given an applicant with two contracted service lines, when `/applicants/{id}` renders, then the service-workflow card is absent: no stage select, reason field, record button or combined stage timeline is rendered for any line, and the page shows one read-only case row per line with its service, contract number, current stage, contract status and payment state plus a link to that case.
- [x] Given the same applicant, when a case row is opened, then `/service-cases/{line}` displays only that line's applicant identity, service, contract, current stage, allowed next stages, stage history, line tasks and line finance, and no sibling line id, history row, task or balance is present in the payload or the page.
- [x] Given a service line at its initial stage with zero `applicant_attachments`, when a legal forward transition is submitted from the case page or `POST /api/applicant-contracts/{line}/change-stage`, then it succeeds, writes exactly one line history row and returns no document-required error.
- [x] Given an applicant with no service line and zero `applicant_attachments`, when a legal forward transition is submitted to `POST /api/applicants/{id}/change-stage`, then it succeeds with no document check and the case-level writer remains available only for that unlined applicant.
- [x] Given a terminal target with a blank note, when it is submitted from either stage endpoint, then the request is rejected with `errors.note` and writes no stage or history row; with a trimmed note it succeeds once and stores the note.
- [x] Given a viewer who holds `view_applicants` but is outside the case ownership scope and lacks `view_all_applicants`, when the case page or `GET /api/applicant-contracts/{line}` is requested, then access is refused with 403 and no case data is returned; the same viewer with `view_all_applicants` can read it.
- [x] Given a viewer without `change_applicant_stage`, when the case page renders, then no enabled stage control is offered, and a direct change request is refused with 403; a viewer with the grant and case scope can move the line.
- [x] Given a lined applicant, when `POST /api/applicants/{id}/change-stage` is called directly, then it still returns 422 `business.case_stage_managed_by_service` and changes no applicant stage, line stage or history row, even though the applicant page no longer offers that control.
- [x] Given `GET /api/applicants-pipeline?service=scholarship`, when the board renders, then every card is one non-cancelled `applicant_contract` line of that service, an applicant with two lines produces two cards, and an applicant with no line produces no card.
- [x] Given a pipeline card, when it is moved or the move menu is used, then exactly one request is sent to `POST /api/applicant-contracts/{line}/change-stage`, the moved line changes stage and gains one history row, and every sibling line on the same applicant keeps its stage and history.
- [x] Given a line with no current stage or a stage outside the selected service's live list, when the pipeline places it, then it appears under the service's `new` or first stage and never under a stage read from `applicants.current_stage_id`.
- [x] Given the pipeline header and columns, when counts render, then `counts.cards`, the sum of column counts and the totals all count service-case lines for the selected service, and no label or payload unit claims unlined applicants are cases.
- [x] Given the applicant Documents section, when it renders, then upload, list, rename and delete still work for profile documents, but no copy, disabled state or server rule states that a document is required to change a stage, and `business.applicant_document_required` is no longer produced.
- [x] Given the three frontend locales and the three backend catalogs, when `npm run i18n:check` and a key search run, then the new case-workspace keys exist in all three frontend locales, the removed gate/workflow keys have no remaining producer or reference, and no locale key is missing.
- [ ] Given 390, 768 and 1280 px viewports in LTR and RTL under light and dark themes, when the applicant page case list, the service-case workspace and the pipeline board render, then stages, links, history and finance values remain reachable and legible without horizontal page overflow.

## Work completed with evidence
- Replaced `ApplicantDocumentReadiness` with `ApplicantStageTransitionGuard`, which only rejects a terminal stage with a blank note. Both stage controllers call it; no document existence check remains in the workflow.
- Added `GET /api/applicant-contracts/{line}` and `/service-cases/:line`. The read action loads only the line's applicant identity, contract identity, current stage, allowed next stages, stage history, delivery tasks and receipts, and applies the same ownership scope as `GET /applicants/{id}`.
- Removed the unified service-workflow card, per-line stage state, per-line move function and combined timeline for lined applicants from `applicant-case.vue`. The page now renders one read-only case row per line with a workspace link; the applicant-level writer and timeline remain only when the party has zero lines.
- Rebuilt `pipeline()` to return only non-cancelled `applicant_contract` lines of the selected service. Placement reads the line stage or the service start stage, never `applicants.current_stage_id`; totals and `counts` are line-derived. `applicant-pipeline.vue` always moves `card.line_id` and opens the service-case workspace.
- Added the typed `ServiceCaseApi` read/move client, `ApplicantContract::tasks()` for line-scoped delivery work, and the case-workspace copy in all three frontend locales. Retired the gate/workflow keys and the three backend `business.applicant_document_required` entries.
- Added `ServiceCaseIsolationTest` (11 tests) and rewrote the gate, pipeline, contract-stage, workspace and service-details expectations for the zero-document and line-only contracts.

## Files changed and why
- `app/Http/Controllers/ApplicantController.php` — removed the document guard, rebuilt the pipeline line-only, and derived placement and counts from lines.
- `app/Http/Controllers/ApplicantContractStageController.php` — added the single-case read action and replaced the document guard with the terminal-reason guard.
- `app/Http/Resources/ApplicantContractResource.php` — added applicant identity, allowed next stages, line history, line tasks and line receipts; no sibling data.
- `app/Models/ApplicantContract.php` — added the `tasks()` relation used by the case payload.
- `app/Services/ApplicantStageTransitionGuard.php` — new terminal-reason-only transition rule.
- `app/Services/ApplicantDocumentReadiness.php` — deleted; no stage path may consult applicant attachments.
- `routes/api.php` — one `GET applicant-contracts/{line}` route beside the existing writer.
- `resources/ts/api/applicants.ts`, `resources/ts/api/serviceCases.ts` — line-only pipeline type and typed case client.
- `resources/ts/pages/applicant-case.vue` — read-only case list, no unified workflow ledger; timeline and writer only for unlined intake.
- `resources/ts/pages/service-case.vue` — new isolated case workspace with stage writer, history, tasks and finance.
- `resources/ts/pages/applicant-pipeline.vue` — line-only cards, one writer, service-case links.
- `resources/ts/plugins/1.router/routes.ts` — static `/service-cases/:line` route.
- `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json` — new case-workspace keys, retired gate/workflow keys, corrected pipeline count note.
- `lang/{en,fa,ps}/api.php` — removed the retired document-required key.
- `tests/Feature/ServiceCaseIsolationTest.php` (new), `ApplicantWorkflowGateTest.php`, `ApplicantPipelineTest.php`, `ApplicantContractStageTest.php`, `VisaCaseWorkspaceTest.php`, `ApplicantServiceDetailsTest.php`, `ApplicantTaskIntegrationTest.php`, `tests/TestCase.php` — zero-document and line-only coverage; the document helper comment no longer describes a gate.

## Checks run and results
| Date/time | Command/check | Result | Evidence path | Notes |
|---|---|---|---|---|
| 2026-11-04 | `php artisan test --stop-on-failure` on ServiceCaseIsolation, ApplicantWorkflowGate, ApplicantPipeline, ApplicantContractStage, VisaCaseWorkspace, ApplicantServiceDetails and ApplicantTaskIntegration | PASS — 53 tests, 411 assertions | terminal | Includes all new isolation, permission, zero-document, terminal-reason and line-only pipeline cases. |
| 2026-11-04 | `php artisan test --stop-on-failure` on ApplicantWorkflow, ApplicantCaseView, ApplicantContractLines, ApplicantContractPayment, EducationalDashboard, EducationalWorkflowIntegration, CommissionLifecycle and IncomeApplicantPicker | PASS — 52 tests, 796 assertions | terminal | Regression around applicant finance, dashboard pipeline counts and commission. |
| 2026-11-04 | `php -l` on every changed PHP file and the three language catalogs | PASS | terminal | No syntax errors. |
| 2026-11-04 | `npm run typecheck` | PASS — exit 0 | terminal | New page, route and typed client compile. |
| 2026-11-04 | `npm run i18n:check` | PASS — exit 0, 313 source files, 3271 used keys, no missing keys | terminal | Unused-key notice only. |
| 2026-11-04 | `git diff --check` | PASS — exit 0 | terminal | No whitespace errors. |
| 2026-11-04 | `vendor/bin/pint --test` on changed PHP | FAIL — pre-existing style only | terminal | HEAD copies of `ApplicantController.php` and `routes/api.php` fail with the same `ordered_imports` / `statement_indentation` findings; the only new import-order finding was fixed. |

## Decisions and deviations
- The case unit is the `applicant_contract` line. An applicant is a party profile; two lines produce two cases, two histories, two task sets and two balances.
- The applicant-level writer and timeline stay only for a party with zero service lines. They are not a fallback for a lined party.
- The terminal-reason rule remains. Only the attached-document prerequisite was removed, matching the product-owner request.
- Unlined parties are intentionally absent from the pipeline; they remain on `/applications`. An intake lane would be a separate product decision.
- `applicant_stage_histories` still receives an initial line row at contract generation, so a move adds exactly one further row rather than being the line's first history row.
- No schema change and no migration. Pipeline `totals` keep their existing keys but now count lines, split by the line stage's terminal state.

## Blockers and open questions
- The 390/768/1280 px LTR/RTL light/dark browser pass has not been run; that is the only open acceptance criterion.
- The new Dari and Pashto case-workspace copy needs a native read-through before closing.
- Product review should confirm that a party without a sold service line is absent from the pipeline board and stays only on the applications list.

## Handoff notes
- `routes/api.php` is shared with Task-009; this task added one route only and did not reorder unrelated routes.
- The locale files are shared with Task-026; only the `educational.caseUi` subtree and the pipeline count note changed.
- Do not reintroduce the applicant-level fallback writer for a lined party, a bulk multi-line mover, or a stage document check. Each needs a new approved decision.
- The applicant mirror `applicants.current_stage_id` is still written for dashboards and reports but is never the editable stage for a lined party.

## Activity log (append-only)
- `2026-11-04` Implemented Task-028 end to end. The line is the case: the new read endpoint and `/service-cases/:line` workspace expose only one line's history, tasks and finance; the applicant page is a party profile with read-only case rows; the pipeline is line-only and moves only `card.line_id`; the document gate is removed while the terminal-reason rule stays. Added `ServiceCaseIsolationTest` and updated the affected suites. 53 affected tests / 411 assertions and 52 regression tests / 796 assertions passed; typecheck, i18n check and diff check passed. Status moved to `Review` with only the viewport/direction/theme pass open. Pint reports only pre-existing findings also present in HEAD.
