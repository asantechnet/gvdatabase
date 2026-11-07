# Progress — Task-030

## Status snapshot
- Status: `Ready`
- Owner: `Full-stack Laravel/Vue implementer with case-domain review`
- Last updated: `2026-11-05`
- Current focus: `Task contract only; no implementation started. Plan A is one of two mutually exclusive alternatives.`
- Blocker: `Plan selection is not made; Task-030 and Task-031 must not be implemented together. Cross-service party reuse also needs a product decision before implementation.`
- Next action: `Product owner selects Plan A or Plan B. If Plan A is selected, approve same-service reuse and the party-level data boundary, then claim the file ownership and start with the line-register and contract-creation contracts.`

## Acceptance criteria progress
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

## Work completed with evidence
- Task contract only. No application source, tests, migrations, routes, locale files or configuration were changed.
- The current behaviour was traced to `ApplicantController::save()`, `ContractGenerationService::createApplicant()`, `ApplicantController::index()`, `TaskController::store()`, `IncomeController::store()`, `applicant-case.vue` and `service-case.vue`; those traces are recorded in the task context.

## Files changed and why
- `issues/tasks/dilemma/Plan-A-service-case-only/Task-030-service-case-only/Task.md` — the Plan A contract.
- `issues/tasks/dilemma/Plan-A-service-case-only/Task-030-service-case-only/Progress.md` — this record.
- No product file was modified.

## Checks run and results
| Date | Command/check | Result | Evidence | Notes |
|---|---|---|---|---|
| 2026-11-05 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks/dilemma/Plan-A-service-case-only` | PASS — 1 task, 0 errors | Terminal | Validated as an isolated alternative because Plan A and Plan B intentionally claim overlapping files. Implementation checks are not run. |

## Decisions and deviations
- Plan A removes the wrapper behaviour without dropping the legacy `applicants.current_stage_id` column or deleting applicant-level history. This keeps the change reviewable and avoids an irreversible data merge.
- Existing-party reuse is explicit and limited to the same service. Cross-service reuse is a blocking product/schema decision, not an implementation detail to guess.
- Finance recording is reused from the existing Income flow. The task does not add a second ledger, endpoint or form.
- Party-level data (documents, notes, universities, portal, call history) remains shared and must be labelled; it is not duplicated per case in this task.

## Blockers and open questions
- **Plan selection:** Task-030 and Task-031 are alternatives; only one may be implemented.
- **Cross-service identity:** Should one party be able to own scholarship, test-offer, academy and private lines, or is each service a separate applicant? The current single `applicants.service`/detail relation cannot support cross-service reuse without a design change.
- **Legacy history:** Should ambiguous applicant-level stage histories be mapped to a line later, left as audit-only, or archived? This task leaves them untouched.
- **Party-level data:** Confirm that notes, documents, portal access, call history and university applications may remain shared across a party's cases while being shown on each case page.
- **Viewport/Dari/Pashto:** Native copy review and the 390/768/1280 px LTR/RTL light/dark pass remain future implementation checks.

## Handoff notes
- Do not start coding until Plan A is selected. Task-031 is the smaller wrapper-preserving alternative and is not a prerequisite.
- Start with the line-register and contract-creation contracts; they define the case identity that every other change depends on.
- Keep `POST /api/applicants` as the party read/write surface. Do not repurpose it as the Applications register.
- Reuse the existing line stage/history/task/finance readers; do not create a second case model.
- If cross-service reuse is requested, stop and write a separate identity/detail task instead of merging applicants or creating duplicate parties.

## Activity log (append-only)
- `2026-11-05` — Created Task-030 as the Plan A alternative. No product code was edited. The task records the current wrapper behaviour, the missing line-scoped task creation, the existing per-line finance flow and the cross-service identity blocker.
