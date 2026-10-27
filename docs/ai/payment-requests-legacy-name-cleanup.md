# Payment requests: rename legacy labels and drop the legacy URL aliases

- **Task ID:** payment-requests-legacy-name-cleanup
- **Created:** 2026-10-26
- **Last updated:** 2026-10-26
- **Status:** COMPLETED
- **Outcome:** The `PendingTransaction` workflow is called **Payment requests** everywhere the user can see it (UI labels, all three locales, printable documents, notifications, audit business-area label, applicant-case labels), and the legacy `/loans` and `/pending-transactions` URL aliases are gone from the frontend router, the Laravel API routes and the client RBAC mirror, so `payment-requests` is the only public path.

## 1. Requirements and acceptance criteria

- [x] No user-visible label anywhere in the product still calls this concept a "loan", "pending transaction" or "financial request".
- [x] The payment-requests page, its add dialog and its edit dialog are titled from the new name ("Payment request(s)"): `educational.newPending`/`editPending` now read "New/Edit payment request"; the page header and the "New" button already read "Payment requests"/"New payment request".
- [x] The income and expense save-mode button reads "Send to payment requests" (was "Send to loans"), with matching hint and toast text.
- [x] The printed A5 payment-request document is titled "Payment Request" with "Payment request ID" / "Payment request details" and the matching footer note, in all three locales (asserted by the print regression script).
- [x] Notification titles, the audit business-area label, the applicant-case "new / linked" labels and the workspace link all use the new name.
- [x] `en.json`, `fa-AF.json`, `ps-AF.json` stay key-for-key in sync; `npm run i18n:check` reports no missing keys.
- [x] Frontend router: `/loans` and `/pending-transactions` no longer exist as routes or redirects; no code references the removed route names.
- [x] Backend: only `/api/payment-requests*` is registered; the legacy paths are gone (asserted by a new feature test).
- [x] Client RBAC mirror no longer maps any `/loans*` or `/pending-transactions*` path.
- [x] Backend feature tests updated to the canonical path; 24 tests / 151 assertions pass.
- [x] `npm run i18n:check`, the print regression script and the four touched PHP feature test files pass on `main`; `npm run typecheck` reports no error in any touched file (it is red only on the parallel HR work, see §7).
- [x] The same behavior is mirrored into the multi-tenancy worktree and validated there.
- [x] Nothing is committed in either worktree (explicit user instruction).

## 2. Scope and non-goals

- **In scope**
  - i18n values in all three locales (`en.json`, `fa-AF.json`, `ps-AF.json`), 23 values renamed and 2 dead legacy-named navigation keys removed.
  - Frontend router `/loans` and `/pending-transactions` redirect entries; the applicant-case link that used the `pending-transactions` route name.
  - `routes/api.php`: a single `/api/payment-requests` prefix group.
  - `resources/ts/utils/apiAuthorization.ts`: the `/loans*` and `/pending-transactions*` permission mirrors.
  - Stale "loans" code comments in `income.vue`, `expenses.vue`, `receipt_template.vue`, `ReceiptPrinter.vue` and the RBAC mirror.
  - PHP feature tests and the print regression script that encoded the removed paths or the old printed labels.
  - This record and its row in `docs/ai/README.md`.
- **Out of scope (explicit decisions)**
  - Code identifiers: the `PendingTransaction` model/controller/resource, permission keys (`view_pending_transactions`, …), the `pending-transactions.vue` page file, the route component key, `api/pendingTransactions.ts`, the `document: 'loan'` payload discriminator and the `receipt.loan` shape, the `LoanReceiptService` class, and the `scripts/loan-receipt-print-regression.mjs` filename. The user chose "user-facing text only" plus dropping the URL aliases.
  - Renaming i18n *key* names that embed the old word (`documents.loan.*`, `saveModeLoan`, `loanSubmitted`, `pendingUi`): their values are what users read, and the keys are bound to the backend `'document' => 'loan'` contract and the `document="loan"` prop.
  - The seeded finance-type *data* value `'Pending transaction'` (migration `2026_10_09_000002`, `FinanceTypeSeeder`, `FinanceType::EXPENSE_TYPES`, the expense created by `PendingTransactionController::post`). It is a stored value, not a label; `financeTypeLabel`/`translateBackendValue` renders it through `common.statuses.pendingTransaction`, whose value is now "Payment request", so the UI already shows the settled name without a data migration.
  - Historical records under `docs/` that describe past renames.
  - Generated output (`public/build`, `public/sw.js`, `public/version.json`, icon CSS) and the tenant-only surface of the multi-tenancy branch.
  - Unrelated HR work in the working tree (see §8).

## 3. Project findings

- **The concept.** One model, `App\Models\PendingTransaction`, exposed under the canonical public name *payment requests*: `GET|POST|PUT /api/payment-requests…` served by `PendingTransactionController`, printed by `LoanReceiptService`, viewed on `resources/ts/pages/pending-transactions.vue`, contracted in `resources/ts/api/pendingTransactions.ts`.
- **Rename history in code.** `/payment-requests` was already the canonical route (the API looped `['payment-requests', 'loans', 'pending-transactions']`; the router had two redirects). The old names survived as labels and as aliases.
- **User-visible legacy labels found (before):** `saveModeLoan` "Send to loans" and its hint/toast in both the income and expense pages; `documents.loan.title|reference|details|footerNote|print` ("Loan Request", "Loan ID", "Loan Details", "This is a system-generated loan request document.", "Print loan"); `educational.noTransactions` "No pending transactions."; `educational.newPending`/`editPending` "New/Edit pending transaction"; `educational.pendingUi.actions.edit` "Edit this loan request"; `educational.caseUi.pendingWorkspace` "Pending workspace"; `common.ui.newFinancialRequest` / `unableToLoadFinancialRequestOptions` / `financialRequestLinkedToThisVisaCase` ("financial request" wording on the applicant case); `pages.audits.resources.pending_transactions` "Financial requests"; `notifications.finance.pendingSubmitted.title` / `pendingStatus.title` "Financial request …"; `common.statuses.pendingTransaction` "Pending transaction"; plus two dead navigation keys `common.navigation.loans` ("Loans") and `common.navigation.pendingTransactions` ("Pending transactions").
- **Legacy URL aliases.** Router: `/pending-transactions` and `/loans` redirects, and an `applicant-case.vue` link by route name `'pending-transactions'`. API: the `foreach` loop in `routes/api.php`. Client RBAC mirror: nine `'/loans*'` and eight `'/pending-transactions*'` entries. Tests hitting them: `PendingTransactionTest`, `ApplicantCaseViewTest`, `EducationalWorkflowIntegrationTest`, `RealtimeNotificationTest`.
- **Regression scripts.** `scripts/loan-receipt-print-regression.mjs` asserted that the alias list existed, that the RBAC mirror kept `GET /loans/:id/receipt`, and that the document rendered the "Loan …" labels. `scripts/support-contact-regression.mjs` only reads the page file (unaffected).
- **Conventions.** Locale values are edited in all three locales together; `npm run i18n:check` fails on missing keys and only lists unused ones; the static router is authoritative; generated output is never hand-edited.

## 4. Graph and impact review

- **Tools actually used:** no `graphify` skill was loaded. `graphify-out/` holds only an unrelated cached AST for a historical document, so it was not used as evidence. Review used repository-wide `ripgrep`, a flattened-key scan of the three locale JSON files (plus a byte-identical round-trip check before rewriting them), direct reads of the router/nav/API-contract/page files, a flattened-key parity script across both worktrees, and `diff` between the worktrees.
- **Dependency chain examined:** route name → router → nav config → page → API contract → controller → service → print payload → print template → i18n keys; plus the client RBAC mirror consulted before each API call, plus the PHP tests and Node regression script that encode the paths and labels.
- **Downstream effects handled:** removing a route name broke `applicant-case.vue`'s `:to` binding; removing API aliases affected four PHP test files; changing printed labels affected the print regression script's assertions; renaming `common.statuses.pendingTransaction` changes how the auto-posted expense type renders via `financeTypeLabel`.
- **Checked and deliberately untouched:** `LoanReceiptService`, the `document: 'loan'` payload value, the `document` prop, the `receipt.loan` payload shape, permission keys, and `app/Support/AuditRegistry.php` / `AuditPresenter.php` (already-modified HR working-tree files).
- **Limitations:** the change is text and routing only, so it was verified by i18n parity checks, the print regression render and PHP feature tests rather than a browser; no visual/PDF layout verification was performed.

## 5. Clarifications, decisions, and assumptions

- **Open questions:** none.
- **User decisions (2026-10-26, via `ask_user`):** the settled name is **"Payment requests"**; the page and its add/edit dialogs still said "Pending transactions" and had to be fixed; the code scope is *user-facing text only plus dropping the `/loans` and `/pending-transactions` URL aliases*. The user's first message contained a contradiction ("settled on payment requests" but "(pending request)"); asked to confirm, the user corrected it: "I meant to name it Payment Requests. it is the settled name."
- **Assumptions and rationale:**
  - "Financial request" is another historical name for this concept (`common.ui.*`, `pages.audits.resources.pending_transactions`, `notifications.finance.*.title`), so it was renamed too, as the user asked for every previous name to be replaced by the settled one.
  - The two removed navigation keys were provably unreferenced in any source file; `common.statuses.pendingTransaction` was kept (it is reachable through the dynamic `translateBackendValue()` lookup used by `financeTypeLabel` and the audit presenter) and only its value changed.
  - The fa-AF and ps-AF wording reuses the established translations of "payment request" already present at `common.navigation.paymentRequests` (`درخواست پرداخت` / `د تادیې غوښتنه`) so the three locales stay consistent.

## 6. Implementation plan

- [x] Step 1 — locale values renamed in `en.json`, `fa-AF.json`, `ps-AF.json`; the two dead navigation keys removed.
- [x] Step 2 — `routes.ts` redirect entries removed; `applicant-case.vue` links to the `payment-requests` route name.
- [x] Step 3 — `apiAuthorization.ts` legacy `/loans*` and `/pending-transactions*` mirrors removed.
- [x] Step 4 — `routes/api.php` replaced the alias loop with a `Route::prefix('payment-requests')` group (matching the `attendances` group style).
- [x] Step 5 — stale "loans" comments corrected in the income/expense pages, the receipt template, the shared printer and the RBAC mirror.
- [x] Step 6 — four PHP feature test files updated; a new test asserts the legacy paths 404 and the canonical one still answers; the print regression script updated to assert the new labels and the absence of the aliases.
- [x] Step 7 — this record added and indexed in `docs/ai/README.md`.
- [x] Step 8 — `main` validated.
- [x] Step 9 — ported into the multi-tenancy worktree.
- [x] Step 10 — the mirror validated.
- [x] Step 11 — record closed; no commits made.

## 7. Validation plan and results

**`main` (production worktree)**

- `npm run i18n:check` — **pass**: `298 source files, 3189 used keys, 4620 English keys`, no "Missing in …" section; the 1435 unused English keys are informational and unchanged in kind.
- `node scripts/loan-receipt-print-regression.mjs` — **pass**: "Payment request wiring, RBAC mirror, i18n parity (3 locales) and receipt/voucher layout switching passed. Browser/PDF layout is not verified."
- `php artisan test --filter='PendingTransactionTest|ApplicantCaseViewTest|EducationalWorkflowIntegrationTest|RealtimeNotificationTest'` — **pass**: 24 tests, 151 assertions, 87.95s. `PendingTransactionTest` now includes `legacy loans and pending transactions paths are gone`.
- Baseline before the change: `PendingTransactionTest` alone passed 6 tests / 36 assertions (51s), so the new assertion is the only test-count delta.
- `npm run typecheck` (`node scripts/typecheck.mjs`) — **fails, but not on this change**: exit code 2 with 35 `error TS` diagnostics, all in `resources/ts/components/paid-time-off/MyPaidTimeOffDialog.vue`, `components/setup/DirectoryCrudPage.vue`, `components/setup/OfficeSettingsPanel.vue`, `pages/paid_time_offs.vue`, `pages/setup/settings.vue`, `pages/staff_list.vue`, `pages/staff_salary.vue`, `pages/timesheet.vue`, all about `paid_leave_days`/`off_day`/`AttendanceStatus` — the parallel HR work in this working tree. A grep of the full report for `payment|loan|receipt|applicant-case|apiAuthorization|1.router|routes.ts|pending-transactions` returns **no** error line, so no file touched by this task contributes a diagnostic.
- Flattened-key scan of the three locales after the edit: `en` 4620 keys, `fa` missing 9 / `ps` missing 827 — unchanged, pre-existing gaps (`ps-AF` deliberately falls back to `fa-AF` in `scripts/i18n/check.mjs`); `fa` has no extra keys. Zero remaining values matching the legacy terms (the fa/ps matches that remain are unrelated words: "awaiting", "report").
- Repository greps: no `path: '/loans'`, `name: 'pending-transactions'`, `'/loans`, `'/pending-transactions`, `foreach (['payment-requests'` or "Send to loans"/"Loan Request"/"Print loan"/"No pending transactions" remains in `resources/ts`, `routes`, `tests` or `scripts`. The only surviving "loan" tokens are the deliberately kept identifiers listed in §2.

**Multi-tenancy worktree (`../educational-multi-tenancy`)**

- Ported by applying the identical, verified string replacements and the same locale script to the tenant tree, after confirming every pre-image string existed there (the tenant's `routes/api.php` and `routes.ts` differ only in tenancy-owned surrounding code; the payment-requests blocks were byte-identical). No merge and no cherry-pick was used.
- `npm run typecheck` — **pass** (exit 0, 0 errors).
- `npm run i18n:check` — **no missing keys**; exit code 1 is pre-existing and caused by two literal UI strings (`MySQL`, `SQLite`) in the tenant-only `resources/ts/pages/saas/offices.vue`, a file this task does not touch (confirmed unmodified via `git status`).
- `node scripts/loan-receipt-print-regression.mjs` — **pass** (same message as on `main`).
- `php artisan test --filter='PendingTransactionTest|ApplicantCaseViewTest|EducationalWorkflowIntegrationTest|RealtimeNotificationTest'` — **pass**: 24 tests, 151 assertions, 19.44s.
- Parity script across both worktrees: locale values for all 24 touched keys are identical, no legacy route/alias reference remains in either tree, and both trees carry the canonical prefix, the `payment-requests` link and the new regression test.
- Not run on the tenant: `pnpm run build`. The branch commits its own generated bundle, but nothing is being committed in this task, so the rebuild is deferred to whoever commits the port (a bundle built now would only add uncommitted noise).

## 8. Risks and mitigations

- **A removed route alias breaks an unknown deep link** — Mitigation: the user's explicit decision ("so only /payment-requests remains"); the new feature test asserts the aliases are gone while the canonical path still answers.
- **Removing a dead i18n key that is in fact referenced dynamically** — Mitigation: only the two navigation keys with no reference in any source file were removed; the dynamic-lookup key `common.statuses.pendingTransaction` was kept, value-only.
- **Locale drift** — Mitigation: all three locales edited together, key parity verified, `npm run i18n:check` green on `main`.
- **Printed-document wording regression** — Mitigation: the print regression script renders the A5 document in all three locales and asserts the new labels and the absence of the old ones.
- **Collision with the parallel HR work** — Mitigation: no HR file was edited (`app/Models/*`, `app/Support/AuditRegistry.php`, the HR migration and HR docs are untouched). `routes/api.php` and the locale files are shared with that work, so the port avoided `git apply` of the whole file diff and used exact string replacements instead; `routes/api.php` on `main` also carries unrelated in-flight HR route edits that were left alone. No commit was made, so nothing is published on top of the other agent's work.
- **Tenancy mirror conflict** — Mitigation: pre-image strings were verified in the tenant tree before editing, and the tenant's differently-wrapped `ReceiptPrinter.vue` / shifted route files were edited with the same content-level replacement rather than a line-based patch.
- **`npm run typecheck` is red** — Mitigation: documented as pre-existing HR work; verified that no diagnostic references a file this task touched. It will go green when the HR agent lands their type changes.

## 9. Progress log

- 2026-10-26 — DISCOVERY: mapped the concept, its canonical `/payment-requests` surface, every legacy label and alias, the client RBAC mirror and the affected tests/scripts. Confirmed the tenancy worktree is clean, shares the labels, and differs only in tenancy-owned surrounding code. Captured the PHP test baseline (6 passed).
- 2026-10-26 — CLARIFICATION: asked the user which name is final and how deep the code rename should go; recorded both answers in §5.
- 2026-10-26 — PLAN: plan written to this record before implementation.
- 2026-10-26 — PROGRESS: verified the three locale files round-trip byte-identically through `JSON.stringify(…, null, 2)`, then applied 23 value renames plus 2 key removals per locale by exact key path. Removed the router redirects, the applicant-case route-name link, the RBAC mirror entries and the API alias loop; corrected stale comments; updated four test files and the print regression script; added this record and its index row.
- 2026-10-26 — VALIDATION: `npm run i18n:check` green and the print regression green on `main`; four PHP feature test files pass (24 tests / 151 assertions); `npm run typecheck` red only on the parallel HR files, with no diagnostic in any touched file. Normalized the file list and re-verified by grep.
- 2026-10-26 — MIRROR: pre-image strings verified in the tenancy worktree, then the same content-level edits and the same locale script applied there; `docs/ai/README.md` row added and this record copied. Tenant validation: typecheck clean, print regression green, 24 PHP tests pass, i18n has no missing keys, and a cross-worktree parity script confirms identical locale values and no remaining legacy aliases.
- 2026-10-26 — REPORT: record closed. Nothing committed in either worktree.

## 10. Completion summary

- **Delivered:** the `PendingTransaction` workflow is labelled **Payment requests** across all three locales, the payment-requests page and its add/edit dialogs, the income/expense save-mode control, the printed A5 document, notifications, the audit business-area label and the applicant-case controls; and the `/loans` and `/pending-transactions` URL aliases are gone from the router, the API and the client RBAC mirror in both `main` and the multi-tenancy worktree.
- **Acceptance criteria satisfied:** all of §1, verified per §7.
- **Tests/checks passed:** i18n check and print regression and 24 PHP feature tests on `main`; typecheck and print regression and 24 PHP feature tests plus i18n parity on the tenant. `npm run typecheck` on `main` fails only on the parallel HR work (see §7).
- **Remaining limitations / follow-up:**
  - Nothing is committed, per instruction. When committing: make the `main` commit(s) first, port them to `feature/multi-tenancy-per-office` as its own commit, record the ported `main` SHAs in the tenant's `docs/ai/mirror-main-into-multi-tenancy.md`, and rebuild the tenant bundle (`pnpm run build`) as `docs/facts/branch-mirroring.md` requires.
  - The deliberately kept identifiers (`PendingTransaction`, `LoanReceiptService`, `pending-transactions.vue`, `documents.loan.*` keys, `document: 'loan'`, the `scripts/loan-receipt-print-regression.mjs` filename, the seeded `'Pending transaction'` finance-type value) still carry the old word in code; renaming them was explicitly out of scope.
  - `npm run typecheck` on `main` stays red until the HR agent's type changes land.
