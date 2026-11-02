## Task: Finish the remaining issue task set (Task-006 and Task-016)

- **Task ID:** `finish-remaining-issue-tasks`
- **Created:** 2026-11-01
- **Last updated:** 2026-11-01
- **Status:** PARTIALLY COMPLETED — Task-006 `Review`; Task-016 `Blocked` by an explicit product deferral
- **Outcome:** Close the only non-`Review` tasks in `issues/tasks`: implement the remaining Task-006 display/feedback gaps, and resolve Task-016's commission-trigger ambiguity with the product owner.

### 1. Requirements and acceptance criteria
- [x] Implement every outstanding Task-006 acceptance criterion (staff base-currency salary, payroll subtitle, position-change confirmation, accessible action names) and record evidence.
- [x] Verify the already-claimed Task-006 layout criteria (timesheet KPI strip, attendance ledger, leave hint/skeleton, leave detail labels).
- [x] Obtain a product decision for Task-016's accrual trigger before changing behavior.
- [x] Keep Task-016 in its documented stop-condition state if the decision is deferred.
- [x] Keep `issues/tasks/INDEX.md` and both task `Progress.md` files synchronized; pass the task validator.

### 2. Scope and non-goals
- In scope: `resources/ts/pages/staff_list.vue`, `resources/ts/pages/payrolls.vue`, the three locale catalogs under the `pages.payrolls` and `pages.settings.staffList.staffList` subtrees, and the two task records plus `INDEX.md`.
- Out of scope: shared formatter (`utils/money.ts`), table primitives, salary/leave backend rules, commission trigger changes without a decision, and the pre-existing Task-013 i18n gap reported below.

### 3. Project findings
- `issues/tasks/INDEX.md` listed 18 tasks in `Review`, Task-006 `In Progress`, Task-016 `Blocked`. Those two were the only remaining implementation work.
- `staff_list.vue` rendered salary as `${{ Number(item.salary || 0).toFixed(2) }}` without loading any currency; the shared `formatActiveMoney` and `CurrencyApi.getAll()` (`isBase`) are the established pattern used by `asset.vue` and `payrolls.vue`.
- `pages.payrolls.description` was literally "Description" **and** is also the deduction form/column label, so renaming it would have renamed a legitimate field; a separate `subtitle` key was required.
- `applyPositionSalary` rewrote salary from `positionSalaryMap` with no warning.
- Staff and payroll icon-only `VBtn`s used tooltips/`title` but several lacked `aria-label`.
- Task-016's trigger decision is a hard precondition; its trigger-independent work (pluralization, settled-accrual guard) was already delivered.
- No Vue component test harness exists: zero `*.spec.ts`/`*.test.ts` under `resources/ts`, and `package.json` has no unit/component test script.

### 4. Graph and impact review
- Tools actually used: repository search (`code_search`), targeted file reads, JSON catalog key assertions via Node, `git diff`/`git status`, `npm run typecheck`, `npm run i18n:check`, and `issues/tasks/scripts/validate_tasks.py`. No graph skill/artifact exists in this repository; fallback was symbol/call-site search.
- Examined: staff list → `EmployeeApi`/`PositionApi`/`CurrencyApi`; payroll register → `SalaryApi` and `formatActiveMoney`; Task-016 `CommissionService`/`CommissionController`/`commissions.vue` and `CommissionLifecycleTest`.
- Downstream effects: salary display only (values unchanged); detail viewer and print templates untouched; commission behavior unchanged, so contract/case workflows and payroll source amounts are unaffected.
- Limitation: no browser rendering/AT run was performed; layout criteria rest on source/CSS inspection.

### 5. Clarifications, decisions, and assumptions
- **Open question asked:** contract completion vs case completion vs leaving Task-016 blocked.
- **User decision (2026-11-01):** "Leave blocked; only keep the pluralization fix." No trigger, observer or copy rename.
- Assumption: `{ code: 'AFN' }` is the documented fallback when no base currency exists, matching `payrolls.vue`'s existing fallback.
- Decision: add `pages.payrolls.subtitle` rather than overwrite the dual-purpose `description` key.
- Decision: prompt only when the catalogue salary differs from the current salary; Escape/outside-click counts as cancel and restores the prior position.

### 6. Implementation plan
- [x] Format staff salary with `formatActiveMoney` and the loaded base currency; load `CurrencyApi.getAll()` with the other reference data.
- [x] Replace the payroll KPI description with a new localized `subtitle` key in `en`, `fa-AF`, `ps-AF`.
- [x] Add a position/salary confirmation dialog with explicit confirm, cancel, `positionBeforeChange` restore and Escape handling.
- [x] Add `aria-label`s to remaining staff and payroll icon actions.
- [x] Verify timesheet KPI/ledger CSS and markup, leave hint/skeleton and leave detail label keys.
- [x] Record evidence in both `Progress.md` files and `INDEX.md`; leave Task-016 `Blocked`.
- [x] Run typecheck, locale assertions, validator and diff check.

### 7. Validation plan and results
- [x] `npm run typecheck` — planned and run: **PASS** (exit 0) on the final state.
- [x] `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` — **PASS**: 20 tasks, required sections/statuses/dependencies/ownership/INDEX valid.
- [x] Locale key/placeholder assertions across `en`, `fa-AF`, `ps-AF` — **PASS** (three new keys, `{position}`/`{salary}` preserved).
- [x] `git diff --check` — **PASS**.
- [ ] `npm run i18n:check` — **FAIL, pre-existing and unrelated**: `common.messages.scrollForMoreColumns` is missing from all three catalogs and `LoginForm.vue` has an untranslated "Recovery code". The missing key is used by `resources/ts/components/TableScrollAffordance.vue` (Task-013) and is absent in `git show HEAD:` catalogs; this change adds only its own three keys.
- [ ] Browser viewport/AT checks at 390/768/1280 px in LTR/RTL and dark/light — **not run**; left as reviewer verification (no screenshot/visual-check request, and the repository has no component test harness).

### 8. Risks and mitigations
- Payroll/staff values could look different despite equal numbers — mitigation: currency code shown once, numeric values untouched, formatter shared with Task-012.
- Position confirmation could block routine edits — mitigation: no prompt when the catalogue salary equals the current salary or no catalogue salary exists.
- Task-016 trigger could drift by inference from current code — mitigation: record the explicit deferral and the settled-accrual guard, and keep the decision with the product owner.
- Pre-existing `i18n:check` failure could be mistaken for a regression — mitigation: documented here and in the Task-006 record with the HEAD comparison.

### 9. Progress log
- 2026-11-01 — DISCOVERY: Confirmed only Task-006 and Task-016 are non-`Review`; traced staff salary, payroll description, position logic, accessible names, timesheet CSS, leave detail keys and the commission service.
- 2026-11-01 — PLAN: Chose the minimal shared-formatter adoption, a separate subtitle key, and a confirmation dialog; prepared the commission-trigger question.
- 2026-11-01 — PROGRESS: Implemented all Task-006 edits; asked the product owner about the commission trigger.
- 2026-11-01 — DECISION: Product owner deferred the commission trigger; Task-016 unchanged and remains `Blocked`.
- 2026-11-01 — VALIDATION: `npm run typecheck` PASS; task validator PASS (20 tasks); locale assertions PASS; `git diff --check` PASS; `npm run i18n:check` FAIL on pre-existing Task-013/LoginForm gaps.

### 10. Completion summary
- Delivered: Task-006 implementation and evidence (status `Review`); Task-016 decision record and explicit blocker (status `Blocked`); synchronized `INDEX.md`.
- Acceptance criteria: all Task-006 criteria satisfied at source/type level; Task-016 trigger criteria remain blocked exactly as the task's stop condition requires.
- Tests/checks passed: `npm run typecheck`, task validator, locale key assertions, `git diff --check`.
- Remaining limitations: browser viewport/AT confirmation for Task-006; pre-existing `i18n:check` gaps in Task-013's component and `LoginForm.vue`; Task-016 awaiting a recorded product/HR decision.
