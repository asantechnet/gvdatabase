# Progress — Task-001

## Status snapshot
- Status: `Review`
- Owner: `Full-stack implementer`
- Last updated: `2026-10-31`
- Current focus: `Implementation and task-local verification complete; visual/accessibility checks at 390/768/1280 px remain for review.`
- Blocker: `None`
- Next action: `Review the diff, then check the applications/pipeline screens at 390, 768 and 1280 px in LTR and RTL for the count labels, extension chips and disabled transition state.`

## Acceptance criteria progress
- [x] Given a case with applicant stage 6 and two scholarship lines at stage 1, when the applications list is filtered by scholarship stage New, then every returned row displays New and the filter uses the same service-line stage expression that the row displays. — Evidence: `tests/Feature/ApplicantWorkflowGateTest::test_the_stage_filter_uses_the_stage_the_row_displays` (pass): a line moved to `consultation` while the applicant mirror was forced back to `new`; `?service=scholarship&stage=consultation` returns the row with `display_stage.key = consultation`, and `?stage=new` returns no rows. Controller uses one expression for filter and display (`ApplicantController::applyFilters`, `applyDisplayStages`).
- [ ] Given the pipeline for one service, when the page renders, then the header count label, the number of cards and the per-column counts each state whether they count cases or service lines, and the displayed numbers reconcile with that definition. — Evidence: `applicant-pipeline.vue` now labels column counts "N cards", header chips "Active/Closed/All cases", and explains the definition; API returns `counts.cards`/`counts.columns`. Not visually verified in a browser.
- [ ] Given an extension contract line, when it appears on the pipeline, then its card names its role and parent contract number and cannot be mistaken for a second primary case. — Evidence: pipeline card now carries `contract_role` and `parent_contract_number`, and the card renders "Extension of {number}". Not visually verified.
- [x] Given a case with zero labeled documents that is leaving its initial stage, when a forward non-terminal transition is submitted through the UI or a direct API request, then the request is rejected with a field-level error and no applicant stage, line stage or history row changes. — Evidence: `ApplicantWorkflowGateTest::test_a_forward_move_out_of_the_initial_stage_needs_a_labeled_document` asserts 422 with `errors.stage_id`, unchanged history count and unchanged stage; `test_a_service_line_obeys_the_same_document_and_reason_rules` covers the line endpoint.
- [x] Given at least one labeled document, when the same forward transition is submitted, then it succeeds once and its history row records the actor and the note. — Evidence: same test asserts `current_stage.key = consultation`, exactly one history row, `note = Documents checked` and `changed_by = acting user`.
- [x] Given Cancelled or Withdrawn with a blank note, when the transition is submitted, then it is rejected and the case UI requires the reason and explains that terminal outcomes need one. — Evidence: `ApplicantWorkflowGateTest::test_closing_a_case_requires_a_reason` asserts 422 with `errors.note` and an unchanged active case, then success with a note. `applicant-case.vue` already required the reason client-side and now shows the server rule.
- [x] Given a viewer without `view_applicant_financials`, when the applications list renders, then the payment column is absent; given a viewer with the permission, then the column shows the row's overall payment status or a defined empty value for a case with no lines, never a silent all-dash placeholder. — Evidence: `ApplicantWorkflowGateTest::test_payment_status_is_only_exposed_to_viewers_with_finance_access` asserts the key is absent without the permission and `unpaid` with it. `applicants.vue` filters the column header and its filter out for viewers without the permission, and prints "No services" instead of a dash.
- [x] Given a stage-history entry, when the case timeline renders, then it shows the actor name and the note in addition to the from/to stage and timestamp. — Evidence: `applicant-case.vue::stageSubtitle` now joins timestamp, `changer.name` and note; `stage_history.changer` was already loaded by `ApplicantController::show`. Rendered source only; not visually verified.
- [ ] Given a 390 px viewport, when the applications page renders, then the stage tab strip and the Applications/Visa reports sub-navigation remain reachable without clipping. — Evidence: not verified; the tab strip already uses `show-arrows`. Requires a viewport check.

## Work completed with evidence
- Added the shared transition guard in `App\Services\ApplicantDocumentReadiness`: terminal moves require a note, and forward non-terminal moves leaving the initial stage require a labeled document. Both stage controllers call it before writing anything.
- The applications list now returns and displays one `display_stage` per row, computed from the filtered service line (or the applicant mirror when that service has no line), and the stage filter uses the same expression.
- `payment_status` is only present for viewers with `view_applicant_financials`; the column and its filter are omitted otherwise.
- Pipeline response now states what columns count (`counts.cards`/`counts.columns`) and exposes contract role/parent number for extension cards.
- Case timeline shows the actor; the case page explains and disables a forward move blocked by the missing document.
- Added the two business error messages in English, Dari and Pashto.

## Files changed and why
- `app/Services/ApplicantDocumentReadiness.php` — replaced the unused one-line readiness service with the shared transition guard (document + terminal reason + initial-stage rule).
- `app/Http/Controllers/ApplicantController.php` — stage filter aligned with display, `display_stage` attached per page, payment status only for finance viewers, gate called on `changeStage`, pipeline counts and extension identity.
- `app/Http/Controllers/ApplicantContractStageController.php` — line transitions use the same guard; history stores the trimmed note.
- `app/Http/Resources/ApplicantResource.php` — `payment_status` gated by permission (`MissingValue` otherwise); exposes `display_stage`.
- `app/Http/Controllers/ApplicantController.php::pipelineLineCard` — `contract_role`, `parent_contract_number`; eager loads `contract.parentContract`.
- `resources/ts/api/applicants.ts` — typed `display_stage`, `changer`, extension fields and pipeline counts.
- `resources/ts/pages/applicants.vue` — permission-gated payment column/filter, `display_stage`, mobile decision fields.
- `resources/ts/pages/applicant-case.vue` — document-gate explanation/disable, actor in timeline subtitle.
- `resources/ts/pages/applicant-pipeline.vue` — honest count labels, counts note, extension chip, no duplicate generic failure toast.
- `lang/{en,fa,ps}/api.php` — `business.applicant_document_required`, `business.terminal_stage_note_required`.
- `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json` — pipeline count labels, extension note, "No services", document-required copy.
- `tests/Feature/ApplicantWorkflowGateTest.php` — new suite (5 tests).
- Existing suites updated for the new contract: `ApplicantWorkflowTest`, `ApplicantPipelineTest`, `ApplicantContractStageTest`, `CommissionLifecycleTest`, `UnifiedPackageServiceTest`, `EducationalWorkflowIntegrationTest`, `EducationalDashboardTest`, `ApplicantServiceDetailsTest`, `ApplicantTaskIntegrationTest` now seed the labeled document / terminal notes their transitions require.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php -l` on changed PHP files | Local | Pass | — | No syntax errors |
| 2026-10-31 | `node -e` JSON parse of en/fa-AF/ps-AF locales | Local | Pass | — | Three locale files valid |
| 2026-10-31 | `npm run typecheck` | Local | Pass (exit 0) | — | Project script only |
| 2026-10-31 | `php artisan test` (10 affected files) | MySQL `testing` | 61 passed, 3 failed (DDL collisions) | — | Failures were `migrations`/`currencies` already exists from another process migrating the shared `testing` schema concurrently; no assertion failures |
| 2026-10-31 | `DB_DATABASE=testing_gate php artisan test tests/Feature/ApplicantWorkflowGateTest.php` | MySQL `testing_gate` | Pass (5 tests, 44 assertions) | — | Isolated database to avoid the concurrent DDL; database dropped afterwards |

## Decisions and deviations
- Workflow unit: the contracted service line remains authoritative; the applicant stage stays as the compatibility mirror, and the list shows the line stage only under a service filter (documented in `applyDisplayStages`).
- Initial-stage detection: lowest `sort_order`, non-terminal, active stage for the stage's service (fallback to the applicant service). If no initial stage can be determined, the document gate does not apply — this avoids freezing legacy catalogues.
- The forward rule excludes backward moves (appeals) and terminal moves; terminal moves use the reason rule instead.
- Reused the existing `Tests\TestCase::seedApplicantDocuments()` helper instead of adding a new trait.

## Blockers and open questions
- None. The product decision about gating legacy cases that never had attachments is recorded as the risk below; the gate is scoped to leaving the initial stage.

## Handoff notes
- Ready for review. Visual checks still needed: 390/768/1280 px, LTR/RTL, light/dark for the pipeline count labels, extension chip, payment column absence and the disabled transition button.
- `git status` still contains unrelated pre-existing modifications from other tasks; only the files listed above belong to this task.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issues 01, 02, 04 and 11; implementation has not started.
- `2026-10-31` Implemented the shared transition guard, list stage alignment, payment-column permission gate, pipeline count labels and extension identity, case timeline actor and document-gate UI; added `ApplicantWorkflowGateTest` and updated the affected suites. Typecheck and the isolated gate suite pass. Status moved to `Review`.
