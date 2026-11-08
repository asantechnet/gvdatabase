## Task: Printable standard commission slip

- **Task ID:** printable-commission-slip
- **Created:** 2026-11-08
- **Last updated:** 2026-11-08
- **Status:** COMPLETED
- **Outcome:** Every commission settlement can be previewed and printed as a standard A5 commission slip that matches the existing academy-slip and income-receipt print experience.

### 1. Requirements and acceptance criteria
- [x] A commission settlement row exposes a print action in the commissions workspace.
- [x] The action opens a preview dialog with paper-size options and a fixed two-copy (employee + office) layout, matching receipts and academy slips.
- [x] The slip is built from the settlement, its employee and its accrual lines; it shows company branding, letterhead/safe area, employee details, settlement period, status, commission entries, total and signatures.
- [x] The slip renders correctly in LTR and RTL and under both light and dark app themes (printed document stays ink-on-white).
- [x] Printing uses the project's `printDocument` pipeline with a feature-specific body class and print host; no global print selectors and no `@page` in the route/template stylesheet.
- [x] A4, A5, A4 2× A5 and A5 2-up options behave like receipts/academy slips; paper overflow is reported through translated toast messages.
- [x] Backend endpoint is permission-scoped, returns the Laravel double-wrapped payload, and does not change settlement data or numbers.

### 2. Scope and non-goals
- In scope: `CommissionSlipService`, one read endpoint, typed frontend API contract, print utility, A5 document template, preview/print dialog, commissions-page action, English/Dari/Pashto translations, one backend feature test.
- Out of scope: database migrations, permission changes, settlement lifecycle changes, receipts/academy slip refactors, new PDF generation, changes to accrual calculation or expense booking.

### 3. Project findings
- Commission settlements live in `app/Models/CommissionSettlement.php` with employee, base currency, period, status, gross amount, notes and `accruals()`.
- `CommissionController@show` already loads accruals, contract and currency for one settlement; the workspace page uses `resources/ts/api/commission.ts` and `resources/ts/pages/commissions.vue`.
- Receipts (`resources/ts/pages/templates/receipt_template.vue`, `utils/printReceipt.ts`) and academy slips (`academy_slip_template.vue`, `utils/printAcademySlip.ts`) establish the print pattern: a card sheet cloned into a body-level print host, a namespaced print body class, a temporary `@page` rule injected by `printDocument`, and overflow/artwork checks.
- Both existing documents use the receipt letterhead settings through `DocumentLetterheads::resolve($setting, 'receipt')` and the firm signature from settings.
- `CommissionSettlementResource` already exposes the settlement fields; no resource change is needed for the list.
- Localization lives in `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json`; commission UI labels are under `common.crmUi.commissions`, document labels under `documents.*`.
- Project instructions require logical CSS properties for RTL, no hand-edited icon CSS, and `npm run typecheck` instead of bare `vue-tsc`/`tsc`.

### 4. Graph and impact review
- Graph skills/tools: no `graphify` skill or `graphify-out/` artifact is present in this checkout, so the review used repository search, symbol/reference tracing and direct reads of the route, controller, model, API contract, print utilities and page.
- Examined: `routes/api.php` commission routes; `CommissionController` settlement lifecycle; `CommissionSettlement`/`CommissionAccrual` relations; `CommissionSettlementResource`; `commissions.vue` table actions; receipt/academy slip dialog+template+print utility; `DocumentLetterheads`; locale files.
- Downstream effects: one new controller action and read route, additive API type/method, additive template/dialog, additive action button. No schema, permission or ledger changes; existing settlement flows untouched.
- Limitations: no graph database available; runtime browser/PDF layout is not verified by unit tests.

### 5. Clarifications, decisions, and assumptions
- Open questions: none blocking. The request names "commissions" and "slip"; the workspace's payable record is the settlement, so the slip is per settlement (not per accrual).
- Decisions:
  - Slip is a receipt-like A5 document, reusing the receipt letterhead and firm signature.
  - Two copies per job: employee copy and office copy, matching receipt/academy slip convention.
  - Every settlement status is printable; the document prints the current status so draft/approved/paid/cancelled is unambiguous. No separate immutable snapshot is introduced.
  - Read access follows the existing `view_commissions` permission.
  - The settlement number already exists at creation, so the slip uses it as reference. No numbering change.
- Assumptions: `gross_base_amount` is the slip total; individual accrual lines are the eligible base, rate and commission amounts.

### 6. Implementation plan
- [x] Step 1 — Add `app/Services/CommissionSlipService.php` building the document payload (company, employee, settlement, accrual lines, totals, letterhead, signature).
- [x] Step 2 — Add `CommissionController@slip` and `GET /commission-settlements/{commissionSettlement}/slip` under `view_commissions`.
- [x] Step 3 — Extend `resources/ts/api/commission.ts` with slip types and `CommissionApi.slip()`.
- [x] Step 4 — Add `resources/ts/utils/printCommissionSlip.ts` mirroring the receipt/academy print host, layout and failure checks.
- [x] Step 5 — Add `resources/ts/pages/templates/commission_slip_template.vue` (A5, two-up variants, logical properties, namespaced print CSS).
- [x] Step 6 — Add `resources/ts/components/commission/CommissionSlipPrinter.vue` preview/print dialog with paper options.
- [x] Step 7 — Wire a "Print slip" action into `resources/ts/pages/commissions.vue`.
- [x] Step 8 — Add `documents.commissionSlip.*` and commission print labels to en/fa-AF/ps-AF locales.
- [x] Step 9 — Add a focused backend slip payload test to `tests/Feature/CommissionLifecycleTest.php`.

### 7. Validation plan and results
- [x] PHP syntax check every changed PHP file — `php -l`: all four changed PHP files reported no syntax errors.
- [x] Focused backend test — `php artisan test --filter=test_commission_settlement_slip_serializes_the_printable_document`: **1 passed, 64 assertions, 45.51 s**. The first run failed only because the final `assertJsonStructure` was rooted at the response root instead of `data`; the payload path assertions had already passed. Fixed and rerun to green.
- [ ] Frontend type check — `npm run typecheck`: the command exits 2 with existing diagnostics in `resources/ts/pages/applicant-case.vue`, `resources/ts/pages/staff_list.vue` and `resources/ts/pages/support.vue`. **No diagnostic references any file added or edited by this task.** `applicant-case.vue` already had working-tree modifications before this task; `staff_list.vue` and `support.vue` are unmodified in the working tree.
- [x] SFC/template compile and print-CSS namespace check — compiled the new template, dialog and commissions page with `@vue/compiler-sfc`; no template/script/style errors, no `@page` in any SFC style, and every `@media print` rule was namespaced under `body.commission-slip-print-mode`.
- [x] Locale JSON parse — all three locale files loaded through `JSON.parse` after adding the new keys.
- Checks not run and why: browser/PDF paper rendering is not available in this environment; the template follows the already-validated receipt mount and print host contract.

### 8. Risks and mitigations
- Long accrual lists can overflow a small 2-up card — the print utility reuses the receipt overflow guard and surfaces `documents.commissionSlip.paperTooSmall`; the user can choose A4/A5.
- Letterhead safe-area content can collide with the artwork — reuse the exact safe-area binding and clone behaviour proven by receipts/academy slips.
- RTL/localization regressions — use logical properties only and add keys to all three locales.
- Print CSS leaking to other routes — all global print selectors are namespaced under `body.commission-slip-print-mode`; `@page` stays out of the SFC.
- Draft/cancelled documents could be mistaken for payable vouchers — the current settlement status is printed prominently on the slip.

### 9. Progress log
- 2026-11-08 — DISCOVERY: inspected commission settlement backend/frontend, receipt and academy slip templates, print utilities, letterhead resolution, routes, locale structure and existing commission lifecycle test.
- 2026-11-08 — PLAN: saved this record before implementation; no blocking ambiguity found.
- 2026-11-08 — PROGRESS: implementation started.
- 2026-11-08 — PROGRESS: added `CommissionSlipService`, `CommissionController@slip`, the read route under `view_commissions`, typed API method, print utility, A5 template, preview dialog, commissions-page print action and en/fa/ps translations.
- 2026-11-08 — VALIDATION: `php -l` passed for all changed PHP files; locale JSON parsed; SFC compile and print-CSS namespace checks passed; focused slip test passed with 64 assertions; `npm run typecheck` still exits 2 on existing diagnostics outside the changed files.

### 10. Completion summary
- Delivered: a printable commission slip per settlement, reachable from the settlements table. Backend payload includes the firm branding, letterhead safe area, employee identity, settlement period/status/payment date, accrual lines, total and signature. Frontend includes the A4/A5/2-up preview dialog, two fixed employee/office copies, translated error handling, and RTL/LTR-safe print CSS.
- Acceptance criteria satisfied/not satisfied: all acceptance criteria above are satisfied in code and by the focused checks; visual browser/PDF paper verification was not possible in this environment.
- Tests/checks passed: PHP lint, locale JSON parse, SFC compile + namespaced print CSS check, focused commission slip test (64 assertions). Typecheck has unrelated existing failures outside this task's files.
- Remaining limitations or follow-up work: confirm the printed A5/2-up layout in a browser with real company letterhead artwork and a settlement containing many accrual lines; if a long list overflows, the existing paper-size guard prompts the user to choose a larger paper.
