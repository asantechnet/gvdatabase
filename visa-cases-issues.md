# Visa cases pages — business-logic review

- **Date:** 2026-11-01
- **Scope:** `/applications` (cases list), `/applicants/:id` (visa case workspace), `/applicant-pipeline` (cases board)
- **Mode:** read-only review. No product code, configuration, migrations, seeders or tests were changed. No business records were written: the stage history row count was 22 before and after the review.
- **Method:** static tracing of the Vue page, API contract, controllers, services and models; live UI inspection through Playwright against `http://localhost:8000` as the seeded `admin@example.test` (super admin); SQLite/MySQL working data inspected read-only through `php artisan tinker`.
- **Expected behavior sources:** `docs/ai/contracts-applications-merge-and-per-service-stages.md` (especially AC3, AC7, plus the 2026-10-18 service-line decision), `docs/visa-case-enhancements-plan.md`, `AGENTS.md` (Payments section). No other approved case-workflow specification was found.

## Why the page feels "off" — the short answer

The case workspace carries **two independent stage-workflow systems at the same time**:

| Control | Where | Endpoint | What it writes |
|---|---|---|---|
| "Move case to stage" + "Stage note" + "Record Stage Change" | Top of the page, immediately under the stage chip | `POST /api/applicants/{applicant}/change-stage` | `applicants.current_stage_id` and one `applicant_stage_histories` row with `applicant_contract_id = NULL`. **It does not move any service line.** |
| Per-service "Service workflow" row (select + Record) | Below the profile card, one row per `applicant_contract` line | `POST /api/applicant-contracts/{line}/change-stage` | The line's `current_stage_id`, a history row tied to that line, a mirror update of `applicants.current_stage_id`, and the line task sync. |

The service-line workflow is documented as authoritative (AC3 and the 2026-10-18 service-line pivot); the applicant-level stage is only a **compatibility mirror** for dashboards/reports. On the case page that mirror is exposed as a second, equally prominent write control, so a user can record a case move that no service workflow follows. The real data already shows the resulting drift: case #6 is closed at `cancelled` while its only test-offer line (`CTR-26-016`) is still at `new`; case #1's mirror says `consultation` while its extension line (`CTR-26-002`) is at `new`.

## Severity summary

| ID | Severity | Finding |
|---|---|---|
| VISA-01 | Critical | Two competing stage writers; case-level moves leave service lines and line tasks behind |
| VISA-02 | High | Per-service terminal moves can never be completed from the UI (no closing-reason input) |
| VISA-03 | High | Stage filter/display contradiction on the applications list, and stage deep links do not apply |
| VISA-04 | High | Timeline merges case-level and line-level history with no line identity |
| VISA-05 | High | Same-service contract lines are rendered identically and cannot be told apart |
| VISA-06 | Medium | Two different document-upload flows with different meanings and gates |
| VISA-07 | Medium | Finance card reports "no activity" for a partially paid case and never shows line money |
| VISA-08 | Medium | A closed case still reports open work; case-level closure does not sync line tasks |
| VISA-09 | Medium | Stage controls use different permission checks; the case control is not gated in the UI |
| VISA-10 | Medium | Default applications list shows a cross-service deduped stage tab strip (21 tabs) |
| VISA-11 | Low | Duplicate delivery tasks with identical titles |
| VISA-12 | Low | Inconsistent service labels/terminology and misleading helper copy |
| VISA-13 | Low | Accessibility defects on icon-only actions and stage selects |
| VISA-14 | Low | Unlined cases use the case-level model inside a line-based board without explanation |

---

## VISA-01 — Two stage writers; case-level moves diverge from the service workflow

**Severity:** Critical — user-reported as "why there is two ways to record stage change".

**Expected (per AC3 / 2026-10-18 decision):** the contracted service line owns the current stage; the applicant-level stage is a derived mirror used only by aggregate screens. The case screen should have **one** way to record a workflow move, and any aggregate value should update from it.

**Actual:**
- The page renders the legacy applicant-level panel (`Move case to stage` select + note + record button) **and** a per-line `Service workflow` card with its own select and record button.
- The case-level call only updates `applicants.current_stage_id`; it does not touch `applicant_contract.current_stage_id`, does not call `ContractTaskService::syncForStage`, and writes a history row with `applicant_contract_id = NULL`.
- The line-level call updates the line, mirrors the applicant, and syncs the linked task.
- Observed data:
  - Applicant #6 is at `cancelled` and shows "This case is closed at Cancelled"; its only line (id 9, `test_offer`, `CTR-26-016`) is still at `new` and remains an open card on the test-offer board.
  - Applicant #1: applicant mirror `consultation`; line 1 `CTR-26-001` `consultation`; line 2 `CTR-26-002` `new`. The page shows all three values at once.
  - History rows for applicant #1: rows 9, 18 and 19 are case-level (`line = -`, including `consultation → documents_required` with no note); row 20 is line 1 (`new → consultation`). They are presented as one timeline (see VISA-04).

**Evidence:**
- UI: `.playwright-cli/case1-two-stage-controls.png` shows the two stage controls; the page text at the time was `Consultation` / `Service workflow` with two `Scholarship` rows (`Consultation`, `New`) and a stage panel offering `Documents Required` + `Withdrawn`.
- Read-only data check: `php artisan tinker` output for applicants 1 and 6 and the 22 history rows (`line = -` vs `line = 1/9/15`).
- Source: `resources/ts/pages/applicant-case.vue` (`transitionOptions`, `move`, `recordStage`, `moveLine`); `app/Http/Controllers/ApplicantController.php::changeStage` (~line 351); `app/Http/Controllers/ApplicantContractStageController.php::changeStage` (~line 24); `app/Models/ApplicantStageHistory.php`.

**Reproduction:**
1. Open `/applicants/1`.
2. Compare the top stage chip, the two `Service workflow` rows, and the Timeline.
3. Open the top select: it offers `Documents Required` / `Withdrawn`, although the newest service line is still at `New`.
4. Move the case-level stage (even a non-terminal move) and observe that the line rows and the line's task do not change.

**Decision needed:** keep the line form as the single write path and render the case stage read-only (derived), or collapse the two concepts into one. Removing the legacy panel from the page is required either way; the aggregate mirror can keep updating behind the scenes.

---

## VISA-02 — Per-service terminal moves cannot succeed (no note field)

**Severity:** High — a whole class of actions is offered but can never complete.

**Expected:** closing a line at `completed`, `cancelled`, `rejected` or `withdrawn` writes a permanent history row and must carry a reason, exactly like the case-level control says ("Stage note (required to close a case)").

**Actual:**
- `moveLine()` calls `ApplicantApi.changeLineStage(line.id, stageId)` with **no note argument**; the shared `note` ref belongs to the case-level panel and is not passed.
- `ApplicantContractStageController::changeStage` calls `ApplicantDocumentReadiness::assertTransition(...)`, which rejects every terminal target when `note` is empty with `business.terminal_stage_note_required`.
- The per-line select still lists the terminal targets (e.g. `Withdrawn`), and selecting one enables the Record button. There is no note input inside the service-workflow card, so the request always fails.
- The failure is only reported by the shared API notifier; the user has no way to supply the missing reason on that card.

**Evidence:**
- UI: applicant #1, first service row select = `Withdrawn`, Record button became enabled; the page also shows the note box only in the case-level panel above.
- Source: `resources/ts/pages/applicant-case.vue::moveLine`; `app/Http/Controllers/ApplicantContractStageController.php::changeStage`; `app/Services/ApplicantDocumentReadiness.php::assertTransition`.
- Note: after selecting the terminal option I did not submit a write, so no record changed; the finding is source-traced and deterministic.

**Expected resolution:** give the line row the same consent pattern as the case control (reason + confirmation) or stop offering terminal targets there.

---

## VISA-03 — Stage filter and displayed stage disagree; stage deep links are dropped

**Severity:** High — the list can show a case under a stage tab while the row displays a different stage, and dashboard/pipeline deep links do not filter.

**Expected:** selecting the `Consultation` tab (or opening `?service=scholarship&stage=consultation`) shows the cases whose selected service line is at `Consultation`, and the `Stage` column shows that same stage.

**Actual (observed):**
- Opening `/applications?service=scholarship&stage=consultation` selected **All Applications** and listed 3 scholarship records (`#7 New`, `#5 Visa Preparation`, `#1 New`). The query string was present but the filter was not applied.
- After clicking the `Consultation` tab, the count became 1 record (`#1 Ahmad Wali`) — but its `Stage` column still displayed **`New`** (the newest line, `CTR-26-002`), not `Consultation` (the line that matched the filter, `CTR-26-001`).
- The unfiltered list shows `#1` as `Consultation` (the applicant mirror); the service-filtered list shows `#1` as `New` (the newest line). The same case's stage changes with the filter.
- `VTabs` is bound to `stage` before the service stage list is loaded; with no matching tab yet it falls back to `''`, silently discarding the deep-link stage. `pipeline` and dashboard links rely on these query parameters.

**Evidence:**
- UI: `applications?service=scholarship&stage=consultation` → "3 records", selected tab "All Applications"; after clicking Consultation → "1 records", row `#1 ... New`.
- Source: `resources/ts/pages/applicants.vue` (`stage` ref initialisation, `VTabs v-model="stage"`, `onTabChange`); `app/Http/Controllers/ApplicantController.php::applyFilters` and `applyDisplayStages` (display stage is `orderByDesc('id')->first()` per applicant, i.e. the newest line, independent of the stage filter).

**Expected resolution:** resolve the stage after the catalogue loads (or use a route watcher), and display the line that satisfied the filter (or clearly state "newest line stage").

---

## VISA-04 — Timeline loses the distinction between case history and line history

**Severity:** High — the permanent record is the audit trail of the case; it currently reads as one workflow when it is two.

**Expected:** every history row identifies the workflow it belongs to (service line / contract number), and case-level rows are explicitly marked as case-level. Users must be able to read the case's actual journey without guessing.

**Actual:**
- `stageHistory` returns every `applicant_stage_histories` row for the applicant, sorted by `changed_at`; the template renders only `old → new`, date, changer and note.
- Rows for different service lines and rows for the applicant mirror are interleaved.
- On applicant #1 the timeline reads `University Selection → Withdrawn`, `Withdrawn → Consultation`, `Consultation → Documents Required`, `New → Consultation`. The first three are case-level; the last is line 1. Nothing says so.
- `applicant_contract_id` is present in the model but never exposed or rendered, so an auditor cannot tell which service line a row belongs to.

**Evidence:**
- UI: case #1 Timeline section (snapshot text) with the four rows above.
- Source: `app/Models/ApplicantStageHistory.php`; `app/Http/Controllers/ApplicantController.php::show` (`stageHistory.oldStage`, `stageHistory.newStage`, `stageHistory.changer`); `resources/ts/pages/applicant-case.vue::stageHistory`.

**Expected resolution:** expose the line/contract in the history payload and render it (or group the timeline per service line, with a separate "case-level" group).

---

## VISA-05 — Same-service lines are indistinguishable on the case page

**Severity:** High — moving the wrong contract line is easy and the result is not reversible through the normal flow.

**Expected:** each contracted service row identifies its own contract (number, primary vs extension, status, fee/payment state) so the user knows which line they are moving.

**Actual:**
- Applicant #1 has two `scholarship` lines: `CTR-26-001` (primary, `consultation`) and `CTR-26-002` (extension of `CTR-26-001`, `new`).
- The case page renders both as `Scholarship` only (`line.type ?? line.service`); no contract number, no "extension" marker, no date, no fee/status.
- The pipeline, by contrast, shows `CTR-26-001`, `CTR-26-002`, and `Extension of CTR-26-001` on the same two cards. The case page is the weaker screen for the same data.
- `presentService()` does return `contract_number`, `date`, `status`, `fee`, etc.; the template simply does not use them.

**Evidence:**
- UI: `.playwright-cli/case1-two-stage-controls.png` shows two identical `Scholarship` rows; the pipeline DOM showed the contract numbers and extension text.
- Source: `app/Http/Controllers/ApplicantController.php::presentService`; `resources/ts/pages/applicant-case.vue` service-workflow template (`line.type ?? line.service`).

---

## VISA-06 — Two document upload flows with different meanings

**Severity:** Medium — users can upload the "wrong" document and the stage gate will not see it.

**Expected:** one applicant-document flow that feeds the stage gate, clearly separated from task attachments. Two actions both called "Attach" should not target different stores.

**Actual:**
- Inline `Document label` + file + `Attach` posts to `POST /api/applicants/{id}/attachments` (`applicant_attachments`). Only these rows satisfy `ApplicantDocumentReadiness::ready()` and the "Attach at least one labeled document before moving this case out of its first stage" gate.
- The `Attach document` button opens a dialog that requires selecting a **case task** and posts through `TaskApi.uploadAttachment` (`input` task attachment). It does **not** create an `applicant_attachment`, so it does not clear the document gate.
- The page shows both lists: "Applicant documents" ("1 labeled document(s) on file") and a task-file list below (`From task: Deliver Scholarship — Ahmad Wali`), without explaining the difference.
- Case #7 at `New` showed the gate warning; the `Attach document` dialog would appear available (it has a task) but would not satisfy the warning.

**Evidence:**
- UI: case #7 stage panel warning and case #1 Documents section (inline upload + dialog + task file list).
- Source: `resources/ts/pages/applicant-case.vue` (`uploadApplicantAttachment`, `openDocumentDialog`, `uploadDocument`, `canAttachDocuments`); `app/Http/Controllers/ApplicantAttachmentController.php`; `app/Services/ApplicantDocumentReadiness.php`; `app/Http/Controllers/TaskController` attachment routes.

**Expected resolution:** keep one gate-feeding upload UI and label the task-file list as "Files attached to tasks" (or hide it behind the task detail). If both are intentional, the dialog copy must say it will not satisfy the document requirement.

---

## VISA-07 — Finance card contradicts the case's payment state and hides line money

**Severity:** Medium — the card named "Finance" is the most misleading part of the page for a partially paid case.

**Expected:** a case finance panel reflects the case's actual money position (line fees, paid, remaining, payment status) or explicitly says it only lists posted ledger entries.

**Actual:**
- Applicant #1: the applications list shows `Partially Paid` (derived from `applicant_contract`), while the case page Finance section shows `Posted income 0 · posted expenses 0` and `No financial activity for this case yet.`
- The page never renders the line `fee`, `amount_paid`, `remaining_total` or `payment_status`, even though `presentService()` supplies them.
- The only money actions are "New payment request" and a link to `/payment-requests`; the line's own payment state is invisible.

**Evidence:**
- UI: applications list row #1 `Partially Paid`; case #1 Finance section text `Posted income 0 · posted expenses 0` / `No financial activity`.
- Source: `resources/ts/pages/applicant-case.vue` (`postedIncomeTotal`, `postedExpenseTotal`, finance template); `app/Models/Applicant.php::overallPaymentStatus`; `app/Http/Controllers/ApplicantController.php::presentService`.

**Expected resolution:** show the `applicant_contract` money summary in the Finance card (and link to the contract/payment requests), or rename the card to "Posted transactions" and explain the gap.

---

## VISA-08 — Closed case still shows open work; case-level closure does not sync line tasks

**Severity:** Medium — the page presents mutually exclusive states.

**Expected:** a case closed at a terminal stage either has no open delivery work or explicitly shows what remains open and why. Closing a case and closing its lines should be one decision.

**Actual:**
- Applicant #6 is at `Cancelled` ("This case is closed at Cancelled; no further stage moves are available.") and the same panel shows `1 open task(s)`; an open task `sdddf` is listed.
- The case-level `changeStage` does not call `ContractTaskService::syncForStage`; only the line endpoint does. A case closed through the legacy panel therefore leaves its line delivery tasks open.
- Conversely, a line closed through the line endpoint completes its line task but the applicant mirror/task picture can still differ (see VISA-01).

**Evidence:**
- UI: case #6 stage panel (`Cancelled` + `1 open task(s)`); read-only data for applicant 6 (line `CTR-26-016` at `new`; task 15 open).
- Source: `app/Http/Controllers/ApplicantController.php::changeStage` (calls `ApplicantTaskService::ensureForStage`, not `ContractTaskService`); `app/Services/ContractTaskService.php::syncForStage`; `app/Http/Controllers/ApplicantContractStageController.php`.

---

## VISA-09 — The two stage controls use different permission checks

**Severity:** Medium — read-only users see a control they cannot use, and the permission needed differs by control.

**Expected:** one stage-move permission model; controls hidden or disabled when the current user cannot use them.

**Actual:**
- The case-level panel is not wrapped in any `canAccess` check in the template; any viewer sees `Move case to stage` and an enabled `Record stage change`.
- The route requires `change_applicant_stage`; the controller additionally calls `assertCaseManageAccess`, which for a non-super-admin without `view_all_applicants` requires `EmployeeScope::canManageOwnedBy`. A user with the stage permission but only view scope sees the control and receives 403.
- The per-line card is correctly gated by `canMoveLineStage = canAccess('change_applicant_stage')`.
- `openDocumentDialog`/`uploadDocument` (task attachments) are not gated by `edit_applicants`, while the inline applicant upload is (`canEditCase`).

**Evidence:**
- Source: `resources/ts/pages/applicant-case.vue` stage panel template vs service-workflow template; `app/Http/Controllers/ApplicantController.php::changeStage` + `assertCaseManageAccess`; `routes/api.php` line 403; `app/Http/Controllers/ApplicantContractStageController.php` (line endpoint does not call `assertCaseManageAccess` for `view_all_applicants` users).

---

## VISA-10 — Default applications list shows a cross-service tab strip

**Severity:** Medium — the default view is not a coherent pipeline and stage counts are hard to trust.

**Expected:** one service's stage list per list view (like the pipeline board) or a service-first selector.

**Actual:**
- With no service selected, `ApplicantApi.stages({ service: undefined })` returns a cross-service list deduped by key (`ApplicantStageController@index`), so the tab strip shows 21 tabs on a 7-case dataset, including academy-only stages (`Enrolled`, `In Class`, `Registered`) next to scholarship stages.
- The list filters by `applicants.current_stage_id` (the mirror) when no service is selected, but once a service is chosen it filters by the service line. The same tab therefore has different semantics depending on the filter state.

**Evidence:**
- UI: `/applications` default tab strip (21 tabs including `Enrolled`, `In Class`, `Registered`).
- Source: `resources/ts/pages/applicants.vue` (`loadStages`, `pipelineStages`, `terminalStages`); `app/Http/Controllers/ApplicantStageController.php@index`; `app/Http/Controllers/ApplicantController.php::applyFilters` / `stagesForContext` (`unique('key')`).

---

## VISA-11 — Duplicate delivery tasks with identical titles

**Severity:** Low.

**Actual:** applicant #1 has task 1 (progress 100) and task 21 (progress 0), both `Deliver Scholarship — Ahmad Wali`, both linked to line 1. The case page lists both, one under open tasks and one under completed, with no contract number or line context. `ContractTaskService::ensureForApplicantContract` intentionally creates a new task once the previous one is complete, so this is a data-shape consequence; the UI does not explain it.

**Evidence:** read-only task dump for applicant 1; case page Tasks section; `app/Services/ContractTaskService.php`.

---

## VISA-12 — Labels, terminology and helper copy

**Severity:** Low but visible.

- The profile card renders the raw enum `scholarship` (lowercase) while the rest of the page uses `Scholarship`.
- The page title always says `Visa case`, including `test_offer` and `academy` cases.
- The note field is always labelled `Stage note (required to close a case)`, although a note is optional for non-terminal moves; the case-level panel also displays `Add a reason before closing the case` before a terminal target is chosen.
- The university section shows `Primary choice: University of Toronto` and immediately below `No applications tracked yet.` without explaining that the primary choice is a detail field and the list is a separate tracker.

**Evidence:** case #1 and #7 UI text; `resources/ts/pages/applicant-case.vue` template (`serviceLabel`, note label, university section).

---

## VISA-13 — Accessibility defects

**Severity:** Low.

- Applicant-document row actions are icon-only buttons (`tabler-download`, `tabler-trash`) with no accessible name or tooltip.
- Per-line stage selects are announced only as `Open`; the two identical rows give no accessible distinction (no label containing the contract number).
- `Complete` on tasks is shown without a permission check; a user who cannot complete work gets only a server error.

**Evidence:** UI snapshot of case #1; template icon buttons and `completeTask`.

---

## VISA-14 — Unlined cases inside a line-based board

**Severity:** Low / design clarification.

Applicant #5 has no contracted service line but is at `Visa Preparation` and appears on the scholarship pipeline as a card, while the applications list shows `No services` in the Payment status column. This is the documented coverage rule (unlined cases stay on their service board), but the case page shows no service-workflow card and solely the legacy stage panel, so the user has no way to tell the "no service sold yet" case apart from a fully contracted one. The card/list should label it explicitly.

**Evidence:** pipeline DOM (`Portal Test Applicant | #5 · Active | Scholarship | ... Visa Preparation`); case #5 has no service lines; `docs/ai/contracts-applications-merge-and-per-service-stages.md` §5b coverage rule.

---

## What was verified working

- Login and navigation as the demo super admin; `/applications` renders 7 cases; `/applicant-pipeline` renders the scholarship board with contract numbers and extension labels.
- Case page data loading: profile, detail fields, stage chip, tasks, university section, finance, documents, notes all render.
- **Document gate works:** case #7 (`New`, no labeled documents) shows "Attach at least one labeled document before moving this case out of its first stage." Selecting a non-terminal target (`Consultation`) disables `Record Stage Change`.
- **Terminal confirmation/validation:** the case-level panel requires a reason for `Withdrawn` and shows the closing hint; the case-level select groups final outcomes separately and marks them "Closes the case".
- **Closed case handling:** case #6 shows the "closed at Cancelled; no further stage moves" state and renders no stage select.
- **Service-scoped stages:** the case page loads the applicant's service stages; the pipeline board is service-scoped and switches its stage columns with the service filter.
- **Pipeline placement:** line cards are placed by their own stage, and an unlined case appears on its service board (shown above).
- No writes were submitted during the review; the stage history count stayed at 22 and no attachments/tasks were created.

## Limitations

- Write-path behavior (stage submission, task completion, uploads, finance request, portal invitation) was traced in source, not executed, to keep the working data intact. VISA-02 is deterministic from source (missing note argument + server-side note requirement); the UI attempt was not submitted.
- The working data is seeded/demo data and includes prior manual test records (e.g. `Buffy Test Applicant`, odd notes such as `sdsddssd`); conclusions about data drift are drawn from those rows but the root causes are in the code paths cited.
- The working database is MySQL in this checkout (the docs note SQLite/MySQL test differences); only read queries were used.
- This review is bounded to the three case screens and their direct stage/document/task/finance surfaces. It does not claim the rest of the ERP is correct.

## Evidence artifacts

- `.playwright-cli/case1-two-stage-controls.png` — case #1 with the case-level panel and two identical `Scholarship` service rows.
- `.playwright-cli/case7-document-gate.png` — case #7 at `New` with the document gate and disabled record button.
- `.playwright-cli/page-2026-11-01T11-09-49-284Z.yml` — full case #1 snapshot (timeline, tasks, documents, finance).
- `.playwright-cli/page-2026-11-01T11-12-27-114Z.yml` — case #7 stage gate snapshot.
- `php artisan tinker` read-only output for applicants 1, 5, 6, 7 and the 22 history rows (reproducible with the same query; no output file written).
