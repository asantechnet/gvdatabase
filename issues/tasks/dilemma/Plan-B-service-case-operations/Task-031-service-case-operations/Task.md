# Task Task-031 — Complete service-case task and finance actions without flattening

## Metadata
- Task ID: `Task-031`
- Title: `Complete service-case task and finance actions without flattening`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack Laravel/Vue implementer with finance-flow review`
- Estimated size: `M`
- Source plan/version: `User request 2026-11-05, alternative plan: keep the applicant wrapper and the isolated service cases, but fix the operational gap reported on the service case. The user asked to be told where existing functionality lives instead of creating a duplicate. Repository state 2026-11-05.`
- Requirement / behavior IDs: `CASE-OPS-01` line task create, `CASE-OPS-02` line task scope, `CASE-OPS-03` task completion parity, `CASE-OPS-04` finance reuse, `CASE-OPS-05` no duplicate finance writer, `CASE-OPS-06` wrapper unchanged, `CASE-OPS-07` permissions and feedback, `CASE-OPS-08` locale and responsive parity

## Objective

Make the existing service-case workspace operable without changing the current architecture. A user with `create_tasks` can create a task for the open service line and complete it from the case page. A user with the existing income/payment grant can reach the existing per-line payment flow from the case page with the applicant and line preselected. The applicant wrapper, Applications list, stage writers and case isolation remain unchanged, and no second finance endpoint, table or form is introduced.

## Context and problem

The service-case page (`/service-cases/{line}`) renders the line stage, line history, line tasks and line finance, but it is read-only for tasks and finance. The product owner reported that the page shows task and finance statistics without offering a way to create a task or record money for that service.

The investigation result is split:

- **Manual task creation for a line is genuinely missing.** `Task` already has an `applicant_contract_id` column and `applicantContract()` relation, and `ContractTaskService` sets it for automatic delivery tasks. But `TaskController::store()` validates only `applicant_id`; `applicant-case.vue::createTask()` sends only `applicant_id`; `TaskApi.create()`/`CreateTaskData` do not expose the line id. There is no UI or API path that lets a user create a manual task against a contracted service. This task must add that path, not a duplicate task screen.
- **Finance recording for a service already exists.** `IncomeController::store()` accepts `applicant_contract_id`, resolves the applicant from the line, enforces the line balance and writes the receipt. `IncomeController::applicantContracts()` returns the applicant's payable lines. `/income` has a Record Income dialog with an applicant and service-line selector, plus a `Service payments` dialog implemented by `ServicePaymentsDialog.vue` that calls `createIncome()` once per selected line. `ApplicantContractPaymentService` owns the balance calculation. The missing part is only a shortcut from the service case to this existing flow. This task must not add a second finance endpoint, a second payments table, a parallel balance calculation or a duplicate finance form.

The wrapper-preserving plan is intentionally smaller than the flattening alternative. It does not add calling, portal, documents, notes or university applications to the service case; those remain on the applicant wrapper. It fixes the two operational actions the user identified.

## Scope

### Included

- Extend `POST /api/tasks` to accept an optional `applicant_contract_id` alongside `applicant_id`.
- Validate that a supplied line belongs to the supplied applicant (or derive the applicant from the line when only the line is supplied), is not deleted, and is inside the actor's existing case ownership scope.
- Reject a line that belongs to another applicant, a line outside the actor's scope, or an inconsistent applicant/line pair with a field-level 422/403 before insert. The create permission remains `create_tasks`.
- Persist `tasks.applicant_contract_id` and return it in `TaskResource`.
- Add a New task action to the service-case workspace for viewers with `create_tasks`, using the existing board/title/priority/due-date fields and the existing task API. Preselect the open line and send both ids.
- Refresh the line task list after creation, and keep the task visible in the shared wrapper/board view without creating a second task.
- Add a completion action to the service-case task list using the existing `TaskApi.update(id, { progress: 100 })` path and the existing subtask/dependency blockers. Do not add a second completion endpoint or duplicate the blocker rules in the page.
- Add a Record payment action to the service-case finance section for viewers with the existing income/payment grant. It must open the existing `ServicePaymentsDialog` or the existing `/income` flow with the applicant and line preselected; the actual write remains `createIncome()`/`IncomeController`/`ApplicantContractPaymentService`.
- If the existing shared finance flow cannot accept the preselection without destabilizing `/income` or the dialog, stop and record a navigation-only fallback (link to `/income` filtered by applicant) instead of building a duplicate form. The acceptance criteria explicitly allow this fallback.
- Add English, Dari and Pashto copy for the new actions and any new empty/disabled states.
- Add focused tests for task line scoping, completion reuse and finance reuse; update the existing service-case and task suites.

### Excluded

- No flattening: the applicant wrapper, `/applicants/:id`, Applications applicant rows, the wrapper stage panel and the wrapper Open case flow remain.
- No change to the Applications list, pipeline, stage writers, contract generation, portal, documents, notes, university applications or calling.
- No new finance route, table, calculation, receipt format or payment model. The existing `IncomeController`, `ApplicantContractPaymentService`, `/income` and `ServicePaymentsDialog` remain the only writers.
- No new task board, task status model, notification producer or assignment model. The existing `/tasks` and `TaskApi` are reused.
- No automatic task creation on every service case; automatic delivery tasks remain owned by `ContractTaskService`.
- No new permission names or role changes.
- No removal of the wrapper's task/finance sections; the service-case actions are additive.
- No bulk task creation or bulk payment.

## Preconditions and assumptions

- The product owner chooses Plan B and explicitly keeps the current wrapper + service-case architecture.
- The reported task gap is accepted as real: line-scoped manual task creation has no API/UI path today and is not a duplicate of an existing feature.
- The reported finance gap is accepted as discoverability/navigation, not a missing writer: `/income` and `ServicePaymentsDialog` already record against `applicant_contract_id`. This task must not reimplement them.
- A task may be created against a line whose contract is active or paused; a cancelled/deleted line is rejected. If the product wants tasks on cancelled cases, that is a separate rule.
- The existing case ownership scope (`view_all_applicants`/`EmployeeScope`) and `create_tasks` permission remain the authority. No new grant is created.
- The service case remains the line-scoped read; the wrapper remains the party-level screen. No sibling line data may leak into the service case through the new actions.
- Tests create their own boards, tasks, applicants, lines, users and permissions; demo rows are not fixtures.

## Dependencies

None for execution. This is a mutually exclusive alternative to the flattening plan, not a follow-up. It must not be implemented together with that plan.

## Contracts and boundaries

- **Task create request:** `POST /api/tasks` accepts `board_id`, `title`, optional `description`, `results`, `priority`, dates, `assignees`, `tags`, `applicant_id` and optional `applicant_contract_id`.
- **Task scope:** when `applicant_contract_id` is present, the server loads the non-deleted line and its applicant. If `applicant_id` is also present it must match. The actor must pass the same case ownership check used by `GET /api/applicant-contracts/{line}` (`view_all_applicants` or the existing `EmployeeScope` manage/view rule) in addition to `create_tasks`. A foreign line, mismatched applicant or out-of-scope line yields a field-level 422/403 and writes no task.
- **Task persistence:** the created task stores `applicant_contract_id` and `applicant_id`; `TaskResource` exposes both. The task remains readable from the line relation, the wrapper's task list and the board. No second task is created for the wrapper.
- **Task completion:** the service case calls the existing `PUT /api/tasks/{id}` with `progress: 100`. The server's existing subtask and dependency checks remain authoritative; the page shows the existing blocker copy and does not pre-empt the server with a new rule.
- **Task UI:** the New task action is hidden without `create_tasks`; the service case preselects the current line and does not offer a line picker that could target a sibling. Board selection remains the existing board list. After success, reload the line payload and show the existing success toast.
- **Finance shortcut:** the Record payment action is hidden without the existing income/payment grant. It opens the existing `ServicePaymentsDialog` or `/income` with the applicant preselected and the line preselected where the component supports it. The create call remains `createIncome({ applicant_id, applicant_contract_id, ... })`; the receipt, balance, overrides and notification remain owned by the existing services.
- **Finance fallback:** if `ServicePaymentsDialog` cannot accept the preselection without changing its behaviour for `/income`, the task may ship a link to `/income` with the applicant context and must record the limitation. It must not add a duplicate finance form or endpoint.
- **Wrapper contract:** `GET /api/applicants/{id}`, `POST /api/applicants/{id}/change-stage`, the Applications applicant rows, the wrapper stage panel and the pipeline remain unchanged. The service-case actions are additive and must not alter wrapper counts or stage writes.
- **Isolation:** the case page may show party identity and line tasks/finance only; the new actions must not fetch or display a sibling line's tasks, receipts or balance. The task create response may include the new task, but the page reloads its own line payload.
- **Permissions:** `create_tasks` gates task creation; the existing task update gate gates completion; the existing income/payment grants gate the finance shortcut. The case ownership scope is enforced server-side; a hidden button is not the security boundary.
- **Shared files:** the three frontend locales are shared; add only the `educational.caseUi` keys used by the new actions. `resources/ts/utils/apiValidation.ts` is shared; add only `applicant_contract_id` to the `POST /tasks` schema. Do not reorder or reformat unrelated entries.
- **No duplicate writer:** no new route under `/incomes`, no new finance table, no new balance method. The task's acceptance tests must prove the resulting row has `applicant_contract_id` and is produced by the existing controller.

## Exclusive file ownership

- `app/Http/Controllers/TaskController.php` — accept and validate `applicant_contract_id`, resolve the applicant, enforce case scope and return the line id.
- `resources/ts/pages/service-case.vue` — New task and Complete actions, Record payment shortcut, dialogs/state and task/finance refresh.
- `resources/ts/api/serviceCases.ts` — typed line task/finance action helpers if the page needs them; keep the single-case read contract.
- `resources/ts/api/task.ts` — line-scoped create method/type usage.
- `resources/ts/api/kanban.ts` — `CreateTaskData.applicant_contract_id` and task type fields.
- `resources/ts/utils/apiValidation.ts` — allow `applicant_contract_id` on `POST /tasks`.
- `resources/ts/components/income/ServicePaymentsDialog.vue` — optional applicant/line preselection for the case shortcut without changing the default `/income` behaviour.
- `resources/ts/pages/income.vue` — accept the case shortcut context if the preselection is routed through the page; otherwise record the navigation-only fallback.
- `resources/ts/plugins/i18n/locales/en.json` — new service-case task/finance action copy.
- `resources/ts/plugins/i18n/locales/fa-AF.json` — Dari copy for the new actions.
- `resources/ts/plugins/i18n/locales/ps-AF.json` — Pashto copy for the new actions.
- `tests/Feature/ServiceCaseTaskActionsTest.php` — new task create/complete and finance reuse coverage.
- `tests/Feature/ServiceCaseIsolationTest.php` — keep the case read isolated while the new actions are used.
- `tests/Feature/ApplicantTaskIntegrationTest.php` — line task appears in the wrapper/board without duplication.
- `tests/Feature/IncomeReceiptTest.php` — existing receipt/income contract remains the writer for the case shortcut.

## Acceptance criteria

- [ ] Given a viewer with `create_tasks` and case access, when the service-case page renders, then a New task action is available and it creates a task with the open `applicant_contract_id` and the resolved `applicant_id`; the task appears in that line's task list and in the shared wrapper/board view, with no duplicate line-less task.
- [ ] Given a task create request with a line belonging to another applicant, a deleted/cancelled line, or a line outside the actor's case scope, when it is submitted, then it is refused before insert with a field-level 422/403 and no task is created.
- [ ] Given a request with only `applicant_contract_id`, when it is valid, then the server derives the applicant from the line and stores both ids; given both ids, a mismatch is refused rather than silently corrected.
- [ ] Given a viewer with the existing task update grant, when a task is completed from the service case, then it uses the existing task update endpoint, the existing subtask/dependency blockers are shown, and the line task list refreshes without a second completion implementation.
- [ ] Given a viewer with the existing income/payment grant, when Record payment is used from the service case, then the existing per-line finance flow opens with the applicant and line preselected and the resulting receipt is written by `IncomeController`/`ApplicantContractPaymentService` with the line id; the case receipts and line balance update.
- [ ] Given the existing finance flow cannot accept the preselection without changing `/income` or `ServicePaymentsDialog` semantics, when the implementer reaches that point, then the task ships only a navigation link to the existing flow, records the limitation, and adds no duplicate finance route, table, calculation or form.
- [ ] Given the wrapper and Applications pages, when Plan B is complete, then the applicant rows, wrapper stage panel, Open case flow, pipeline and stage writers are unchanged, and the new actions do not create a wrapper task or a second case.
- [ ] Given a viewer without `create_tasks`, without the task update grant or without the finance grant, when the service case renders, then the corresponding action is hidden or disabled, and a direct request is refused by the existing authorization.
- [ ] Given the three frontend locales, when `npm run typecheck`, `npm run i18n:check`, the focused task/finance suites and the 390/768/1280 px LTR/RTL light/dark pass run, then the new copy exists in all three locales, the actions remain usable, and no line's sibling data is exposed.

## Verification plan

- Automated: add `tests/Feature/ServiceCaseTaskActionsTest.php` covering line task create, derived applicant, mismatch/foreign/out-of-scope rejection, completion via the existing update path, and the finance shortcut's `applicant_contract_id` result. Extend the service-case isolation and applicant-task integration suites. Run the focused suites, then `npm run typecheck`, `npm run i18n:check` and `git diff --check`.
- Fixtures: one applicant with two lines, one line task with a completed predecessor/subtask case, one viewer with `create_tasks` and case access, one viewer outside the case scope, one viewer without `create_tasks`, and one income/payment grant holder. Non-production only.
- Finance check: assert the new action does not call a new route; inspect the network call for `POST /incomes` or the existing `createIncome` path; assert one `incomes` row with the line id and one receipt number. If the fallback link is used, assert it opens `/income` with the applicant context and no duplicate form is present.
- Task check: assert `tasks.applicant_contract_id` on the created row, assert the wrapper/board sees the same task once, and assert the line payload shows it only on the owning line.
- Manual: 390, 768 and 1280 px in LTR and RTL under light and dark; the New task dialog, the complete action and the finance shortcut must not overflow or overlap, and the existing wrapper remains usable.
- Regression: existing wrapper task creation, board task creation, automatic contract tasks, income recording, receipt printing and case isolation must stay green. Report any check that could not run.

## Implementation notes

Reuse the existing task dialog pattern from the wrapper, but bind it to the current line. Do not copy the wrapper's task dialog into a second component with divergent validation; if extraction is needed, extract one shared component and let both call the existing `TaskApi.create`. The service case must not offer a line selector because the line is the page identity.

In `TaskController::store()`, add `applicant_contract_id` to validation and validate it with a closure or after-validation rule so the applicant/line pair and the ownership scope are checked before `Task::create`. Use the same `EmployeeScope` and `view_all_applicants` logic as `ApplicantContractStageController`. Add the relation to the resource load list and the response type. Do not change the existing task assignment gate or notification path.

For completion, call `TaskApi.update(String(task.id), { progress: 100 })` and reuse the wrapper's blocker copy. The server already rejects subtask-derived progress and incomplete dependencies; do not duplicate those rules in a second endpoint.

For finance, first verify the current `ServicePaymentsDialog` and `/income` preselection. Add optional props or query context rather than a second dialog. The dialog currently selects an applicant and then lists payable lines; preselecting the line should select the matching entry, not auto-submit it. Keep the server's balance validation and the existing receipt flow. If the shared component changes would affect the normal `/income` flow, use a plain link to `/income` with the applicant context and record the fallback.

Add the new copy under `educational.caseUi` in all three frontend locales. Do not invent backend language keys; the existing task/finance errors are already localized. Update the `POST /tasks` client schema in `apiValidation.ts` so the new field is accepted by the request interceptor. Check whether the task board's task type needs the new field; if it does, add it to the shared type rather than casting.

## Risks and rollback

- **Task scope bypass.** A line id could be used to attach a task to another applicant's case. Mitigation: server-side applicant/line pair and ownership checks; direct-request tests, not just hidden UI.
- **Duplicate task.** The wrapper's task dialog and the new service-case dialog could both run and create two tasks. Mitigation: one create path, one API call, and an integration test asserting a single row.
- **Duplicate finance writer.** The largest risk is building a second payment form because the case page lacks a button. Mitigation: reuse `createIncome`/`IncomeController`; the acceptance test inspects the row and route. If reuse is not possible, ship the link only.
- **Shared dialog regression.** Preselecting a line in `ServicePaymentsDialog` could change `/income` behaviour. Mitigation: opt-in props/context, default unchanged, regression test the normal flow; fall back to the link if needed.
- **Wrapper divergence.** The wrapper remains the party-level screen, but the service case may show different task counts. Mitigation: both read the same task rows; no new task status or copy source.
- **Rollback.** Code-only change; redeploy the previous build. Any task rows created under the new contract remain valid because `applicant_contract_id` already exists and is nullable. Do not delete them as part of rollback.
- **Stop conditions.** Stop if line-scoped task creation is not approved, if the finance shortcut requires a new writer, or if the shared dialog cannot be extended without changing `/income` semantics. Record the decision and ship the link-only fallback rather than guessing.

## Handoff checklist

- [ ] The task explicitly reuses finance and does not add a duplicate writer.
- [ ] Task create validation covers applicant/line mismatch, foreign line and out-of-scope line.
- [ ] The wrapper, Applications list and stage writers are unchanged.
- [ ] Every acceptance criterion has evidence in `Progress.md`.
- [ ] Any finance fallback or unrun viewport/RTL check is recorded as a limitation.
- [ ] The task is ready for review/integration, not merely code complete.
