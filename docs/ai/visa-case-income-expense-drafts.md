## Task: Record income and expense drafts from a visa case

- **Task ID:** visa-case-income-expense-drafts
- **Created:** 2026-11-08
- **Last updated:** 2026-11-08
- **Status:** COMPLETED
- **Outcome:** The visa case finance card offers separate **Record income** and **Record expense** actions. Each opens the correct form, uses the matching finance type catalogue, and saves the record as a draft for approval through the existing income/expense APIs.

### 1. Requirements and acceptance criteria
- [x] The old single **Record draft finance** action is replaced by separate **Record income** and **Record expense** actions.
- [x] Both actions are shown only to users with `create_draft_finance_records`.
- [x] Each action opens a dialog explicitly for income or expense; the transaction kind is not selected again inside the dialog.
- [x] The dialog offers the matching finance type, description, amount, and currency.
- [x] Submitting posts to the existing `POST /incomes` or `POST /expenses` endpoint with `status: "draft"`.
- [x] Successful submission closes the dialog, reloads the case, and shows a draft-saved confirmation.
- [x] No stale `reason`, `employee_id`, or `financeOptions.employees` bindings remain.
- [x] New labels exist in English, Dari, and Pashto.
- [x] The case finance list now labels income/expense rows and shows a **Draft** status chip.
- [ ] The dialog remains usable in RTL/LTR, light/dark, and narrow layouts — LTR desktop was visually verified; no direction-specific CSS was added, but RTL/dark/mobile were not separately screenshot-tested.

### 2. Scope and non-goals
- In scope: `resources/ts/pages/applicant-case.vue` finance form, dialog, buttons, draft rows and draft submission; locale keys directly required by that UI; focused verification.
- Out of scope: backend finance behaviour, approval/rejection UI, new endpoints, direct (approved) recording from the case, changes to the income/expense ledger pages, or unrelated typecheck cleanup.

### 3. Project findings
- The finance card rendered one button labelled “Record draft finance” in `applicant-case.vue`.
- `openFinanceDialog()` loaded currencies plus income/expense types and opened one shared dialog.
- `createFinanceRequest()` already called `createIncome()` / `createExpense()` with `status: "draft"`.
- The dialog was stale and did not match the form model:
  - Template bound `financeForm.reason`, but the form defines `description`.
  - Template bound `financeForm.employee_id`, which did not exist.
  - Template mapped `financeOptions.employees`, which was never populated.
  - The form defined `type`, but the dialog never rendered a type selector.
- Opening the dialog evaluated missing properties (including `.trim()` on `undefined`), so the action did not work reliably.
- Backend contracts already support drafts: `IncomeController::store` and `ExpenseController::store` accept `status: draft`; `DraftFinanceRecordTest` covers both ledgers and approval.
- `ApplicantController::show` loads `incomes` and `expenses` when the viewer has `view_applicant_financials`, so a new draft is returned in the case payload.

### 4. Graph and impact review
- Graph artifacts were present in `graphify-out/` but built from `c4c97435`, while the workspace was at `00a4ef5c`; they were treated as stale navigation aids.
- Graph nodes for `openFinanceDialog()` and `createFinanceRequest()` confirmed the page-level call path.
- Repository searches then traced the current income/expense APIs, controllers, routes, permissions, resource loading and feature tests. This was the authoritative fallback because the graph was stale.
- Impact is limited to the case page and locale files. No backend contract changed; the existing finance tests remain the regression boundary.

### 5. Clarifications, decisions, and assumptions
- **Decision:** Use two buttons but one shared dialog. The button presets `financeForm.transaction_type`; the dialog title, finance type options and submit call follow that preset. This avoids duplicating a form while making the two actions explicit.
- **Decision:** Keep draft-only recording from the case page. Direct posting requires different permissions and belongs on the finance ledger pages.
- **Decision:** Keep the existing API layer (`createIncome`, `createExpense`) and explicit `status: "draft"`; no new endpoint or service.
- **Decision:** Show a status chip on each case finance row so a saved draft is not mislabelled as posted.
- **Assumption:** “Record income” and “Record expense” mean the existing ledger records tied to this applicant, not a new contract-service payment flow. Existing validation and active-contract rules remain authoritative.

### 6. Implementation plan
- [x] Update the finance form/open function: preset kind, matching type catalogue, correct default type.
- [x] Replace the single finance action with two labelled buttons that open the dialog in income or expense mode.
- [x] Rewrite the dialog body to use `type`, `description`, `amount`, and `currency`; remove stale transaction/reason/employee controls; show the draft hint.
- [x] Submit the existing payload with `status: "draft"` and use existing draft-saved feedback.
- [x] Add the missing income-type label and finance-row labels in all three locales.
- [x] Show each case finance row with a translated status chip.

### 7. Validation plan and results
- [x] SFC compile check for `applicant-case.vue` — passed.
- [x] Locale-key audit for every new/changed key in `en`, `fa-AF`, `ps-AF` — passed.
- [x] `php artisan test --filter=DraftFinanceRecordTest` — **5 tests passed, 45 assertions**.
- [x] `git diff --check` — passed.
- [x] `npm run typecheck` — ran and still exits 2 for pre-existing issues. This change removed the two finance template errors (`financeForm.reason`/`employee_id`). Remaining diagnostics are: `applicant-case.vue` line 241 `countries`, line 950 `TaskApi.uploadAttachment`; the existing `staff_list.vue` nullability errors; and `support.vue` locale typing errors. None are finance-related.
- [x] Playwright smoke test on `/applicants/1` — both buttons rendered; income dialog showed Income type, Description, Amount and Currency; saved a 125 income draft; expense dialog showed Expense type, Description, Amount and Currency; saved a 75 expense draft; confirmation toast and status chips rendered. The two disposable test drafts were then deleted.
- [ ] RTL/dark/mobile screenshot pass — not separately run; the change reuses existing responsive form/list components and adds no direction-specific styling.

### 8. Risks and mitigations
- **Expense draft needs an active contract** — the server rule remains in place; its error reaches the existing API notifier.
- **Permission differences** — both actions remain behind `create_draft_finance_records`; no direct-posting path was added.
- **Existing typecheck baseline** — documented exactly rather than claiming a clean whole-project typecheck.
- **Dev-data side effects during validation** — used an existing case, then removed the two test drafts by exact description and status.

### 9. Progress log
- 2026-11-08 — DISCOVERY: Located the broken case-page action and traced the stale form bindings, existing draft APIs, permissions, controller validation and `DraftFinanceRecordTest`. Graph artifacts were stale, so repository search/static tracing was used as the authoritative fallback.
- 2026-11-08 — PLAN: Plan saved before implementation.
- 2026-11-08 — PROGRESS: Replaced the single action with income/expense buttons, rewrote the dialog, added locale labels and finance status chips.
- 2026-11-08 — VALIDATION: SFC/locale/diff checks passed; draft finance test passed; Playwright created both drafts and confirmed toast/status display. Test drafts removed afterward.
- 2026-11-08 — COMPLETED: Implementation and required validation finished.

### 10. Completion summary
- Delivered: Separate **Record income** and **Record expense** actions on the visa case page, both saving drafts through the existing APIs; corrected form fields; draft status chips; translations.
- Acceptance criteria satisfied: All functional criteria satisfied. The RTL/dark/mobile visual criterion was not separately screenshot-tested.
- Tests/checks passed: SFC compile, locale audit, `DraftFinanceRecordTest` (5 tests/45 assertions), `git diff --check`, Playwright income/expense draft creation.
- Remaining limitations: `npm run typecheck` still exits 2 for pre-existing unrelated diagnostics; RTL/dark/mobile were not separately exercised.
