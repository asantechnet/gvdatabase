# Progress — Task-031

## Status snapshot
- Status: `Ready`
- Owner: `Full-stack Laravel/Vue implementer with finance-flow review`
- Last updated: `2026-11-05`
- Current focus: `Task contract only; no implementation started. Plan B keeps the wrapper and fixes the service-case task/finance gap.`
- Blocker: `Plan selection is not made; Task-030 and Task-031 are mutually exclusive.`
- Next action: `Product owner selects Plan B. Then verify the existing finance preselection path, implement line-scoped task creation first, and add the finance shortcut or the documented link-only fallback.`

## Acceptance criteria progress
- [ ] Given a viewer with `create_tasks` and case access, when the service-case page renders, then a New task action is available and it creates a task with the open `applicant_contract_id` and the resolved `applicant_id`; the task appears in that line's task list and in the shared wrapper/board view, with no duplicate line-less task.
- [ ] Given a task create request with a line belonging to another applicant, a deleted/cancelled line, or a line outside the actor's case scope, when it is submitted, then it is refused before insert with a field-level 422/403 and no task is created.
- [ ] Given a request with only `applicant_contract_id`, when it is valid, then the server derives the applicant from the line and stores both ids; given both ids, a mismatch is refused rather than silently corrected.
- [ ] Given a viewer with the existing task update grant, when a task is completed from the service case, then it uses the existing task update endpoint, the existing subtask/dependency blockers are shown, and the line task list refreshes without a second completion implementation.
- [ ] Given a viewer with the existing income/payment grant, when Record payment is used from the service case, then the existing per-line finance flow opens with the applicant and line preselected and the resulting receipt is written by `IncomeController`/`ApplicantContractPaymentService` with the line id; the case receipts and line balance update.
- [ ] Given the existing finance flow cannot accept the preselection without changing `/income` or `ServicePaymentsDialog` semantics, when the implementer reaches that point, then the task ships only a navigation link to the existing flow, records the limitation, and adds no duplicate finance route, table, calculation or form.
- [ ] Given the wrapper and Applications pages, when Plan B is complete, then the applicant rows, wrapper stage panel, Open case flow, pipeline and stage writers are unchanged, and the new actions do not create a wrapper task or a second case.
- [ ] Given a viewer without `create_tasks`, without the task update grant or without the finance grant, when the service case renders, then the corresponding action is hidden or disabled, and a direct request is refused by the existing authorization.
- [ ] Given the three frontend locales, when `npm run typecheck`, `npm run i18n:check`, the focused task/finance suites and the 390/768/1280 px LTR/RTL light/dark pass run, then the new copy exists in all three locales, the actions remain usable, and no line's sibling data is exposed.

## Work completed with evidence
- Task contract only. No application source, tests, migrations, routes, locale files or configuration were changed.
- Investigation completed: `TaskController::store()` accepts `applicant_id` but not `applicant_contract_id`; `applicant-case.vue::createTask()` sends only `applicant_id`; `Task` already has the line column/relation. Finance already exists through `IncomeController::store()`/`applicantContracts()`, `/income`, `ServicePaymentsDialog.vue` and `createIncome()`, so no duplicate finance writer is planned.

## Files changed and why
- `issues/tasks/dilemma/Plan-B-service-case-operations/Task-031-service-case-operations/Task.md` — the Plan B contract.
- `issues/tasks/dilemma/Plan-B-service-case-operations/Task-031-service-case-operations/Progress.md` — this record.
- No product file was modified.

## Checks run and results
| Date | Command/check | Result | Evidence | Notes |
|---|---|---|---|---|
| 2026-11-05 | Source investigation of task and finance paths | Completed | `TaskController.php`, `applicant-case.vue`, `Task.php`, `IncomeController.php`, `income.vue`, `ServicePaymentsDialog.vue` | Confirms task creation is missing and finance already supports a line. |
| 2026-11-05 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks/dilemma/Plan-B-service-case-operations` | PASS — 1 task, 0 errors | Terminal | Validated as an isolated alternative because Plan A and Plan B intentionally claim overlapping files. Implementation checks are not run. |

## Decisions and deviations
- Plan B keeps the wrapper and Applications rows unchanged. It does not absorb the wrapper's calling, portal, documents, notes or university applications into the service case.
- Finance is reused, not duplicated. The only new finance work is an optional shortcut/preselection; the existing controller and payment service remain the writer.
- Task creation is the genuine missing capability. The API will accept and validate a line id, and the service case will create against its own line only.
- If the shared finance dialog cannot be extended safely, the task allows a link-only fallback to `/income` rather than forcing a risky change.

## Blockers and open questions
- **Plan selection:** Task-030 and Task-031 are alternatives; only one may be implemented.
- **Finance shortcut depth:** Should the case page preselect the line in `ServicePaymentsDialog`, or is a link to `/income` with the applicant context sufficient? The task allows both, with the link-only fallback if the shared dialog cannot change safely.
- **Task status on cancelled cases:** The contract rejects a cancelled/deleted line by default. Confirm whether tasks must remain creatable on a closed/cancelled service case.
- **Viewport/Dari/Pashto:** Native copy review and the 390/768/1280 px LTR/RTL light/dark pass remain future implementation checks.

## Handoff notes
- Do not start coding until Plan B is selected. Plan A changes the case model; this task deliberately does not.
- Implement the task API validation before the UI so the service case cannot attach a task to a sibling line.
- Reuse the existing task dialog/API and the existing income flow; do not add a second finance screen.
- Keep the wrapper's task section and the board working; the new line task must appear once there.
- Record any finance fallback and any unrun viewport/RTL check explicitly.

## Activity log (append-only)
- `2026-11-05` — Created Task-031 as the Plan B alternative. No product code was edited. Recorded that manual line-task creation is missing while per-line finance already exists and must be reused.
