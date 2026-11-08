## Task: Make the completed-task bonus calculation understandable and useful

- **Task ID:** task-bonus-calculation-usability
- **Created:** 2026-11-07
- **Last updated:** 2026-11-07
- **Status:** COMPLETED
- **Outcome:** A manager completing a task can see what the task contributes to a monthly
  bonus, whether the employee will qualify, and the projected amount; saving the inputs is
  never blocked by unrelated task state; and completed task work can actually produce a
  nomination under the configured rules.

### 1. Requirements and acceptance criteria

- [x] The completed-task drawer shows a live projection: per-assignee task credit,
      month-to-date credit, case wins, projected amount, and which minimums are met or missing.
- [x] Saving bonus inputs works when the task has no assignees (quality and rejection are
      still recorded) and the UI explains that no credit can be earned.
- [x] Updating a task from the compact Kanban editor never removes its existing assignees.
- [x] The rules dialog accepts and explains a zero case-win minimum.
- [x] The `/bonuslist` evidence dialog shows case wins and the per-task math, not just titles.
- [x] Task credit alone produces a nomination under the default rules; a configured
      case-win minimum still gates it.
- [x] No regression to the monthly job, grant immutability, payroll inclusion, or the
      preview being read-only.

### 2. Scope and non-goals

- **In scope (final scope depends on the open question):** read-only bonus projection
  endpoint and drawer UI, rules validation/defaults, Kanban edit-drawer assignee fix,
  bonus evidence dialog, i18n (en/fa/ps), focused tests.
- **Out of scope:** changing the monthly schedule or cron semantics, auto-granting,
  removing manual nominations, changing payroll currency conversion, adding new bonus
  sources, websockets, schema changes to `worthy_employees`.

### 3. Project findings

**Current behavior**

- The "Bonus calculation inputs" block is rendered by
  `resources/ts/components/task-manager/components/TaskDetailDrawer.vue` only when the
  task is at 100% and the user has `edit_all_tasks`.
- It records: `manager_quality_score` (1–5, 0.5 steps), per-assignee
  `task_user.contribution_percent` (must total 100), and an optional rejection increment
  (`tasks.rejected_count`). It shows no amount and no eligibility feedback.
- `PATCH /api/tasks/{task}/bonus-review` → `TaskController::reviewBonusInputs`.
  `contributions` is `sometimes`, and the 100% check only runs when it is supplied.
  The task's assignees must match the contribution keys exactly.
- The monthly job `bonuses:calculate` (1st at 02:00 company time, previous month) calls
  `TaskBonusCalculationService::calculate()`:
  - credit per assignee = `(contribution / 100) × qualityFactor × revisionFactor`
  - `qualityFactor = clamp(quality / 3, 0.5, 1.5)` when quality weighting is enabled
  - `revisionFactor = max(0.5, 1 − 0.1 × rejected_count)`
  - amount = `min(credit × amount_per_task + case_wins × amount_per_case, cap)`
  - **Eligibility requires `credit >= minimum_completed_tasks` AND
    `case_wins >= minimum_cases_won`.** Employees outside the exclusions that fail either
    minimum are counted `ineligible` and get no nomination.
- Nomination rows are review-only (`WorthyEmployee`, `is_automatic = true`), keyed
  `task-case-bonus:{month}:{employeeId}`, and hold `calculation_inputs` plus
  `rule_snapshot`. Granting is a separate `grant_bonuses` action on `/bonuslist`;
  payroll only consumes granted rows for the matching month.
- Active rules are normalised by `BonusRuleService`. Defaults:
  `minimum_completed_tasks = 1`, `amount_per_completed_task = 10`,
  `minimum_cases_won = 1`, `amount_per_case_won = 100`,
  `task_quality_weighting_enabled = true`, `maximum_monthly_bonus = 1000`.

**Observed defects (reproduced in the running app on 2026-11-07)**

1. `KanbanBoardEditDrawer` builds `localKanbanItem.members` by looking up
   `currentTask.assignees` inside the async `TaskAssigneeApi.getAll()` result. Before that
   list loads, and again on the initial `localKanbanItem`, members resolve to `[]`.
   `updateKanbanItem` then sends `assignees: []` and `TaskController@update` syncs the
   pivot empty. Confirmed: `task_user` for task 10 became empty after pressing Update,
   twice.
2. With no assignees the drawer renders no participation fields, the total is 0, and
   `saveBonusReview()` returns early with "Contribution percentages must total 100%".
   The backend would accept omitted contributions, so the block is client-only.
3. The section gives no projected amount, no eligibility status, and no explanation that
   the case-win minimum also applies. In this database `case_wins = 0`, so a run with two
   completed tasks returned `ineligible: 2, created: 0`.
4. The bonus evidence dialog on `/bonuslist` lists tasks only; case wins are stored in
   `calculation_inputs.case_wins` but never shown.
5. `minimum_cases_won` is validated `min:1` in `AutomationController` and
   `resources/ts/utils/apiValidation.ts`, so a task-only policy cannot be configured.
6. The active rule row in this installation is legacy-shaped (`amount_per_point`,
   `priority_weights`, …); `BonusRuleService` bridges it and drops the legacy weights.

**Relevant paths**

- `app/Services/TaskBonusCalculationService.php`, `app/Services/BonusRuleService.php`
- `app/Http/Controllers/TaskController.php`, `app/Http/Controllers/AutomationController.php`
- `app/Http/Controllers/WorthyEmployeeController.php`, `app/Http/Controllers/SalaryController.php`
- `app/Models/Task.php`, `app/Models/WorthyEmployee.php`, `app/Models/CaseWin.php`
- `routes/api.php` (`/tasks/{task}/bonus-review`, `/automation/bonus-rules`),
  `routes/console.php`, `app/Console/Commands/CalculateTaskBonuses.php`
- `resources/ts/components/task-manager/components/TaskDetailDrawer.vue`
- `resources/ts/pages/kanban/KanbanBoardEditDrawer.vue`,
  `resources/ts/components/forms/KanbanTaskEditForm.vue`,
  `resources/ts/components/task-manager/views/KanbanView.vue`
- `resources/ts/pages/bonuslist.vue`, `resources/ts/components/bonus/BonusRulesDialog.vue`
- `resources/ts/api/task.ts`, `resources/ts/api/automation.ts`,
  `resources/ts/utils/apiValidation.ts`, `resources/ts/utils/apiAuthorization.ts`
- `tests/Feature/PhaseNineBonusAutomationTest.php`

### 4. Graph and impact review

- **Tools actually used:** `code_search`/ripgrep call-site tracing plus full reads of the
  affected frontend and backend files. `graphify-out/` artifacts exist
  (`GRAPH_REPORT.md`, `graph.json`, manifest) but were built from commit `c4c97435` and are
  stale relative to the working tree; no `graphify` CLI was available or run.
- **Traced call chain (edit):** `KanbanView.vue` → `KanbanBoard.vue` →
  `KanbanBoardEditDrawer.vue` → `KanbanTaskEditForm.vue` → `useTaskStore.updateTask` →
  `TaskApi.update` → `TaskController@update` → `task_user` sync.
- **Traced call chain (bonus):** `TaskDetailDrawer.vue` → `TaskApi.reviewBonusInputs` →
  `TaskController@reviewBonusInputs` → `TaskBonusCalculationService`.
- **Traced call chain (monthly):** `routes/console.php` → `CalculateTaskBonuses` →
  `AutomationRunService` → `TaskBonusCalculationService@calculate` → `WorthyEmployee` +
  `Notification::deliverTo`.
- **Downstream consumers:** `WorthyEmployeeResource` → `bonuslist.vue`;
  `SalaryController::availableGrantedBonusesQuery`; `ReportService`; `SyncAnalytics`.
- **Affected tests:** `tests/Feature/PhaseNineBonusAutomationTest.php` (expected
  113.33, both-minimums test, rule versioning).
- **Limitations:** the graph artifact does not cover this session's uncommitted changes;
  no static type graph was available for the Vue components, so the scan is textual.

### 5. Clarifications, decisions, and assumptions

- **User decision (2026-11-07): Option A — tasks alone earn; case wins add extra.**
  `minimum_cases_won` defaults to 0 and the rules API/UI accepts 0. Existing explicitly
  saved rule values are respected; historical nominations are not recalculated; case wins
  stay in the formula and evidence as an additive reward.
- **Rejected alternatives:** B (keep both minimums — leaves the feature unable to pay for
  the task work it scores) and C (remove case wins — destructive to an existing,
  documented reward path).
- **Assumptions:** the monthly cadence, human grant step and payroll inclusion are
  unchanged; the projection is read-only and never writes a nomination; a saved input only
  affects a run whose month has not been calculated yet.
- **Assumption (all options):** the monthly cadence and human grant step stay unchanged;
  the projection is read-only and never writes a nomination.
- **Low-risk decisions:** projection uses the task's completion month (company timezone),
  not "today"; the preview endpoint is gated by `edit_all_tasks`, matching the section.

### 6. Implementation (completed)

All steps below were implemented, including the Option A steps 7–8:

1. Add a read-only preview endpoint `GET /api/tasks/{task}/bonus-preview`
   (`edit_all_tasks`) that returns the canonical rules, base currency, the task's completion
   month, and per assignee: month-to-date credit excluding this task, case wins, existing
   nomination state, and exclusion status. No writes.
2. Replace the input-only block in `TaskDetailDrawer.vue` with a projection panel:
   rule summary, per-assignee computed result (this task credit, month total, wins,
   projected amount, cap note), eligibility checklist, and a clear "saved" state.
   Recompute locally from the returned month-to-date figures while the slider/percentages move.
3. Only enforce "contributions total 100" when the task has assignees; otherwise save
   quality/rejection and explain that the task earns no credit.
4. Fix `KanbanBoardEditDrawer`: build members directly from `currentTask.assignees` without
   requiring the employee list, and omit `assignees` from the update when unchanged.
5. Show case wins and the amount breakdown in the `/bonuslist` evidence dialog.
6. Update en/fa/ps strings and `apiValidation.ts` mirrors.

If A only:

7. Set `minimum_cases_won` default to 0, allow `min:0` in `AutomationController` and the
   rules dialog, and rewrite the explanation as "task credit is the base; each won case adds".
8. Update `PhaseNineBonusAutomationTest`: task-only nomination test, zero-case rules test,
   and keep a case-win additive test.

### 7. Validation plan and results

- `php artisan test --filter=PhaseNineBonusAutomationTest` — **passed, 5 tests / 53 assertions**
  after resetting the shared `testing` database (`DB_DATABASE=testing php artisan db:wipe --force`).
  The suite includes the new task-only nomination test and the read-only preview/authorization test.
- New feature coverage: task-only nomination, 113.33 additive case-win result, rules
  versioning/validation (0 case minimum accepted), preview month/credit/eligibility,
  preview does not write `worthy_employees` or `automation_runs`, and 403 without
  `edit_all_tasks`.
- `npm run typecheck` — **no errors in any changed frontend file**. The command exits 2 on
  74 pre-existing errors in unrelated files (`applicant-case.vue`, `staff_list.vue`,
  `support.vue`), which are outside this task and were already present.
- PHP lint on the changed backend files — clean. Locale JSON parses in en/fa/ps.
- `php artisan route:list --path=bonus-preview` — the route is registered.
- Manual Playwright pass — **not run**; the change was validated through the HTTP feature
  tests and the type check. The panel is not browser-verified.
- Full suite and production build — not run (not required for this change).

### 8. Risks and mitigations

- **Changing payout policy (A/C) is money-affecting** — mitigation: keep the change to the
  default and validation floor, preserve explicitly saved rule sets, cover with tests,
  and state it clearly in the completion report.
- **Projection drifts from the batch job** — mitigation: share credit/eligibility logic in
  `TaskBonusCalculationService` so the preview and the run use the same code.
- **Past-month tasks** — the preview must use the completion month and say when that month
  was already processed, instead of implying the input affects the current run.
- **Stale graph artifacts** — mitigation: rely on direct call-site tracing and record the
  limitation.

### 9. Progress log

- 2026-11-07 — DISCOVERY: traced the full bonus chain, reproduced the assignee-wipe and
  blocked-save defects in the running app, confirmed a task-only month yields no nomination,
  and recorded the three payout-policy options. No code changed yet.
- 2026-11-07 — WAITING FOR CLARIFICATION: asked whether completed tasks alone should earn.
- 2026-11-07 — PLAN: user chose Option A; plan finalized and implementation started.
- 2026-11-07 — PROGRESS: `minimum_cases_won` now defaults to 0 and accepts 0 in the API,
  validation mirror and rules dialog. Added `TaskBonusCalculationService::preview()` sharing
  the credit/quality/revision helpers with the monthly run, a `GET
  /api/tasks/{task}/bonus-preview` route and TaskController action, and the new
  `TaskBonusPanel.vue` (live per-assignee projection, eligibility reasons, cap note,
  no-assignee save path). Fixed the Kanban editor assignee wipe by mapping members from the
  task itself and only sending `assignees` when the selection changed. Expanded the
  `/bonuslist` evidence dialog with case wins and per-task credit/quality.
- 2026-11-07 — VALIDATION: focused suite passed 5/5 after wiping the shared testing DB;
  typecheck reports no errors in changed files; locales parse; route registered.

### 10. Completion summary

- **Delivered:** task credit alone can earn a monthly bonus; case wins remain an additive
  reward; the completed-task drawer shows a live, explained projection and saves even when
  the task has no assignees; the Kanban editor no longer clears assignees; the evidence
  dialog itemizes task and case-win inputs.
- **Acceptance criteria:** all met.
- **Checks passed:** `PhaseNineBonusAutomationTest` (5 tests, 53 assertions), PHP lint on
  changed files, locale JSON parse, route registration, typecheck with no errors in changed
  files.
- **Limitations:** the repo-wide typecheck still fails on 74 pre-existing errors in
  unrelated files; the class run requires a clean `testing` database because a re-run over
  the leftover schema races the migrations (reproduced on unrelated tests in the same
  class). The new panel was not browser-verified.
- **Follow-up:** run the panel once in the running app with a completed task; consider an
  on-demand “calculate this month now” action for admins.
