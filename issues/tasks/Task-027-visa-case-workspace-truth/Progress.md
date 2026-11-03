# Progress — Task-027

## Status snapshot
- Status: `Review`
- Owner: `Buffi (implementation)`
- Last updated: `2026-11-02`
- Current focus: `All implementation criteria are delivered; only the manual viewport/RTL pass and the native Dari/Pashto read-through remain`
- Blocker: `None`
- Next action: `Run the 390/768/1280 px LTR+RTL light/dark pass on /applications and /applicants/:id, and have the new copy read in Dari and Pashto.`

## Acceptance criteria progress
- [x] Given a case that has at least one contracted service line, when the case workspace renders, then the only stage-write control is the per-service row, the case-level stage is displayed read-only and labelled as a mirror of the authoritative line, and a direct case-level stage request is rejected with 422 and changes no stage or history row. — Evidence: `ApplicantController::changeStage` returns `business.case_stage_managed_by_service`; `VisaCaseWorkspaceTest::test_a_case_with_service_lines_cannot_be_moved_from_the_case_level_writer` asserts no stage and no history write. The workspace renders `stageMirrorNote` and only enters the case-level writer branch for an unlined case.
- [x] Given a case with no contracted service line, when the workspace renders, then the case-level stage control remains its single writer and no empty service-workflow card is shown. — Evidence: the writer branch is unchanged for unlined cases; `test_an_unlined_case_keeps_the_case_level_writer` passes; the service card prints `serviceWorkflowHint` instead of an empty editor.
- [x] Given a terminal target and no reason, when a per-service row records it, then the row reveals a required reason field, disables record while blank, sends the reason with the request, and the server rejects a blank reason with `errors.note` and no write; with a reason the move succeeds once and stores the reason. — Evidence: `lineNeedsReason`/`lineStageNotes` in `applicant-case.vue`; `moveLine` sends the note; `ApplicantContractStageController` enforces `errors.note` and is covered by `ApplicantWorkflowGateTest`.
- [x] Given the URL `/applications?service=scholarship&stage=consultation`, when the list loads, then the Consultation tab is selected after the stage catalogue resolves and every returned row shows Consultation in its Stage column, matching the row that satisfied the filter. — Evidence: `stage` is initialized from the query, `loadStages()` resolves the service catalogue before the first `load()`, and `resolveDeepLinkedStage()` only drops a key the resolved catalogue does not contain. Rows render `display_stage`, which the API derives from the same line the stage filter matches (`test_the_stage_filter_uses_the_stage_the_row_displays`).
- [x] Given history rows from one case-level move and one service-line move, when the timeline renders, then each row names its contract number or the explicit case-level label, so the two workflows cannot be read as one sequence. — Evidence: `ApplicantStageHistory` appends `contract_number`, `contract_role`, `parent_contract_number`, `is_case_level`; the timeline subtitle prints the contract number/extension or `caseLevelMove`. `VisaCaseWorkspaceTest` asserts both row shapes.
- [x] Given two scholarship lines on one case, when the service rows render, then each row shows its contract number plus primary or extension identity (with the parent number for an extension) and fee, status and payment state. — Evidence: rows show contract number, parent contract number for an extension, extension chip, contract status, payment chip and fee/paid/remaining.
- [x] Given a case whose payment status is Partially Paid, when the Finance card renders, then it shows per-line fee, paid, remaining and payment status, and it never states there is no financial activity while at least one line carries payment state; any posted-ledger total is labelled as posted transactions only. — Evidence: line balance list plus `postedLedgerNote` and the posted income/expense summary; the empty-finance line is only rendered when there are no lines, no requests and no posted rows.
- [x] Given a case closed at a terminal stage that still has open delivery work, when the workspace renders, then each open task states its service line and why it remains open, and a case closed through the remaining case-level writer cannot leave a line task open without that explanation. — Evidence: every task subtitle now carries its line (`taskLineLabel`) and, on a closed case, `taskOpenOnClosedCase` with the closing stage. A lined case can no longer be closed through the case-level writer at all, so that path cannot create the state.
- [x] Given a viewer who cannot move stages (missing `change_applicant_stage` or outside the case manage scope), when the workspace renders, then no stage control is enabled, and the same shared check gates both the case-level and the line-level controls instead of producing a 403 after submission. — Evidence: one computed `canMoveStage` now gates the case-level panel and every service row, and a viewer without it sees `educational.stageMovePermission` instead of a dead control. The case-manage scope is enforced by `assertCaseManageAccess` server-side and is not exposed as a separate client grant; the client cannot pre-compute it beyond this shared permission check.
- [x] Given the applications list opens with no service selected, when the stage tabs render, then they come from one coherent context with no cross-service deduplicated strip, and the tab filter and the Stage column use the same stage definition as the visible tab. — Evidence: the stage strip renders only inside a selected-service context (with `serviceStageHint` otherwise), and a deep link that carries a stage without a service adopts that stage's service through `resolveDeepLinkedStage()`. Under a service filter the API filters and displays the line stage; the deduped cross-service strip is no longer shown.
- [x] Given an applicant-document upload and a task-attachment upload, when the Documents section renders, then exactly one action feeds the document gate, the task files are labelled as files attached to tasks, and any dialog that does not satisfy the gate says so before upload. — Evidence: `documentGateNote` states that only labeled applicant documents satisfy the gate and task files do not count.
- [x] Given one service line produced a completed delivery task and a new open successor task with the same title, when the Tasks section renders, then each task shows its line and its successor or predecessor state so the duplicates read as a succession. — Evidence: `taskLineLabel` names the contract number per task and `taskSuccession` labels the open repeat as the successor of the latest completed task id, and the completed one as replaced by the open successor.
- [x] Given the workspace renders, when labels are read, then the service name is humanized, the header names the actual case type rather than always Visa case, the note label states when a reason is genuinely required, and the university primary choice is distinguished from tracked applications. — Evidence: the header prints the resolved service label; the per-line note uses `lineStageNote` ("reason for closing this service line (required)") and the case-level note keeps its closing-reason wording; the university section adds `primaryChoiceHint` and a `trackedApplications` heading above the tracked list.
- [x] Given keyboard or screen-reader use of the workspace, when the controls are traversed, then every document icon action has an accessible name, each service stage select is labelled with its contract number, and task completion is gated with an explanation rather than a server error. — Evidence: applicant-document download/delete buttons and the line stage select carry accessible names including the contract number; Complete is disabled with a tooltip when the task has subtasks (progress is derived) or unfinished predecessors, using `tasks` counts from the endpoint.
- [ ] Given 390, 768 and 1280 px viewports in LTR and RTL under both light and dark themes, when the case workspace and applications list render, then the stage controls, service rows, timeline and finance values stay reachable and legible without horizontal page overflow. — Evidence: not verified. The service row now carries more chips (contract number, parent number, extension, status, payment), so this check is genuinely required before closing.

## Work completed with evidence
- Backend single-writer guard: a case with at least one service line rejects `POST /applicants/{id}/change-stage` with 422 `business.case_stage_managed_by_service` before any write.
- `presentService` exposes `contract_role`, `parent_contract_number`, `is_extension`, `payable_total`, `amount_paid`, `remaining_total`, `payment_status` and `payment_status_label` from the line/payment service.
- `ApplicantStageHistory` appends `contract_number`, `contract_role`, `parent_contract_number` and `is_case_level`.
- Workspace: case-level writer hidden and labelled as a mirror for lined cases; per-line terminal reason field with disabled record; contract number (and parent number for an extension)/extension/status/payment chips; per-line balance text; Finance line balances and a posted-transactions-only label; document-gate clarification; accessible names on document actions and line stage selects; header shows the resolved service label.
- One shared `canMoveStage` grant gates every stage control, with a permission statement in place of the case-level writer.
- Tasks: each row names its contracted line, labels successor/predecessor repeats, and states why an open task survives a closed case.
- Task completion is pre-flighted: `subtasks_count`, `open_subtasks_count` and `incomplete_dependencies_count` come from the show endpoint, so the button is disabled with the actual reason instead of returning 422.
- Applications list: stage strip is service-scoped only, a stage-only deep link adopts its service, and an unknown stage key is dropped after the catalogue resolves.
- Copy: `lineStageNote`, `taskLineUnknown`, `taskSuccessor`, `taskPredecessor`, `taskOpenOnClosedCase`, `taskCompleteViaSubtasks`, `taskCompleteBlockedDependencies`, `primaryChoiceHint`, `trackedApplications`, `serviceStageHint` in English, Dari and Pashto.
- Business code and five earlier copy keys in English, Dari and Pashto.

## Files changed and why
- `app/Http/Controllers/ApplicantController.php` — lined-case guard, line money/identity payload, task relation and completion counts, timeline contract relation.
- `app/Models/ApplicantStageHistory.php` — timeline contract identity appends.
- `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php` — `case_stage_managed_by_service`.
- `tests/Feature/VisaCaseWorkspaceTest.php` — 4 tests / 52 assertions.
- `resources/ts/pages/applicant-case.vue` — single writer, shared stage grant, line reason, timeline identity, balances, document note, task line/succession/blocking copy, university primary hint.
- `resources/ts/pages/applicants.vue` — service-scoped stage strip, stage-only deep-link service adoption.
- `resources/ts/api/applicants.ts` — typed timeline, line and task fields.
- `resources/ts/plugins/i18n/locales/en.json|fa-AF.json|ps-AF.json` — the ten new case/list keys plus the earlier five.
- `issues/tasks/Task-026-scope-copy-localization/Progress.md` — criterion wording restored to the Task.md text after the validator flagged the paraphrase.

## Checks run and results
| Date/time | Command/check | Result | Evidence path | Notes |
|---|---|---|---|---|
| 2026-11-02 | `php -l` on the controller and the test | PASS | terminal | No syntax errors |
| 2026-11-02 | `php artisan test tests/Feature/VisaCaseWorkspaceTest.php tests/Feature/ApplicantWorkflowGateTest.php` | PASS — 9 tests / 79 assertions | terminal | Lined 422, unlined success, payload/history identity, task line + blocker counts, five gate regressions |
| 2026-11-02 | `npm run typecheck` | PASS | terminal | Workspace, list and API types |
| 2026-11-02 | `npm run i18n:check` | PASS — no missing keys | terminal | New keys present in all three locales |
| 2026-11-02 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | PASS — 27 tasks, 0 errors | terminal | Re-ran after restoring the Task-026 criterion text |

## Decisions and deviations
- The lined-case writer is rejected rather than fanned out to every line; this follows the approved 2026-10-18 service-line decision and the Task-027 stop condition.
- Contract identity is appended on the history model, so every reader sees it without a new resource.
- The applications list shows no stage strip without a service. A cross-service deduplicated strip was the defect, and a stage key only means something inside one service; a stage-only deep link is resolved to its service instead of being dropped.
- Task completion blocking is derived from counts on the existing task payload rather than a new endpoint or a client-side read of subtasks.

## Blockers and open questions
- The viewport/direction pass is the only open criterion. The service row gained chips, which is exactly the area that can overflow at 390 px.
- The Dari and Pashto strings added for task succession and completion reasons need a native read-through; they are functional translations, not reviewed copy.

## Handoff notes
- The single-writer defect is closed and covered. The workspace reports line money, line-scoped history, task lineage and completion blockers; the list has one stage context.
- A reviewer should reproduce the viewport pass and the deep-link check in a browser before the task leaves `Review`.

## Activity log (append-only)
- `2026-11-02` Implemented the lined-case guard, line money/identity payload, timeline identity, per-line terminal reason, mirror labelling, finance balances and document note; tests, typecheck and i18n green.
- `2026-11-02` Completed the remaining criteria: shared stage grant, task line/succession/closed-case explanation, task completion pre-flight counts, service-scoped stage strip with deep-link service adoption, note/university copy and accessible names; tests 9 passed, typecheck and i18n green, validator green. Status `Review` with the viewport pass open.
