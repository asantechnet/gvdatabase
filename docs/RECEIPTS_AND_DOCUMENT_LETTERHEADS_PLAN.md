# Receipts, document letterheads, and finance clean-up

Requested by the user on 2026-09-29. This file is the plan **and** the progress log.

## Request (verbatim intent)

1. Make the income receipt carry the formal data an **educational visa firm** puts on a receipt.
2. The receipt template must print **two receipts per page** and look good on **A4, A5 and Letter**.
3. Receipt letterhead must be uploadable from **Settings → Documents**, placed **just under the
   contract template letterhead + cover section**, in the same style, with a way to **select** a
   letterhead, **preview** it and define a **safe-zone** for auto-filled text.
4. Every income record must be individually printable.
5. Applicants must be selectable from a list; the income name and id must be shown clearly so they
   are not confused.
6. Invoice selection stays optional, but when an invoice is chosen its applicant is auto-selected
   and the applicant picker becomes **disabled** while the invoice is linked.
7. Associated services/packages must appear on the receipt.
8. Translation and RTL/LTR logic must work for the receipt (payment date, applicant data such as
   phone, etc.).
9. A **signature/stamp** section is required.
10. A **serial number** such as `RCPT-S0001` (mirroring invoices).
11. Show **total fee** and **paid amount**, where paid amount tracks *all* payments for that
    applicant and service.
12. Invoices must also get a **selectable letterhead** in Settings → Documents, respected by the
    invoice template and the invoice printing page.
13. Income/expense no longer carry a **company** column/field (frontend + backend).
14. Income/expense must offer **“save in loans”** or **save directly**.
15. The receipt is **A3-sized and printed landscape** so two receipts fit on one A4 page,
    bottom-to-bottom.
16. Update the surrounding systems: translation, audits, notifications, permissions, RBAC, etc.

## Recon — what already exists

- **Contract letterhead artwork** lives in two places:
  - `settings.contract_letterhead` (+ safe-zone `contract_letterhead_safe_{x,y,width,height}`) with
    per-contract-type overrides in `contract_document_designs`
    (`ContractDocumentDesignController`, `ContractDocumentDesignService`).
  - Settings → Documents UI in `resources/ts/pages/setup/settings.vue` renders a scope picker plus
    two cards (letterhead / cover) and a pointer-resizable safe-zone box over an A4 aspect preview.
  - Print layers: `resources/ts/components/contract/ContractDocumentPrinter.vue`,
    `resources/ts/pages/templates/contract_page_template.vue`.
- **Invoices** (`InvoiceController`, `InvoiceResource`, `resources/ts/api/invoice.ts`,
  `resources/ts/pages/templates/invoice_template.vue`, `resources/ts/pages/innvoice.vue`) have **no
  letterhead** today.
- **Incomes** (`IncomeController`, `IncomeResource`, `resources/ts/api/incomes.ts`,
  `resources/ts/pages/income.vue`) already mint `receipt_number` via
  `HasDocumentNumber` with prefix `RCP`, but there is **no receipt document** and no print action.
- **Expenses** (`ExpenseController`, `ExpenseFormDialog.vue`) and incomes both expose a **company**
  field (`company_id`, company column) that must be removed.
- **Loans** are the public face of `PendingTransaction` (`/loans` routes,
  `PendingTransactionController`); posting an approved loan creates an `Income`/`Expense`/`Salary`.
  There is no `Loan` model — “loans” === pending transactions.
- i18n: UI strings in `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json`, Laravel messages in
  `lang/{en,fa,ps}.json`; audit via `app/Support/AuditRegistry.php`; permissions in
  `database/seeders/PermissionSeeder.php` / `RolePermissionSeeder.php`.

## Design decisions

- **Letterheads stay settings-backed** (same shape as contracts) rather than a new library table:
  `receipt_letterhead` and `invoice_letterhead` plus per-document safe zones.
- Each document also gets a **`*_letterhead_source`** select with the options
  `none | contract | custom`, which is the “way to select a letterhead”: reuse the company contract
  letterhead, upload a dedicated one, or leave the built-in design.
- The receipt is a **half-sheet landscape card repeated `copies` times per sheet** (default 2,
  stacked bottom-to-bottom with a cut guide). Page size is configurable
  (`A3 landscape` default, plus `A4`, `A5`, `Letter`) through `printDocument`'s `@page` injection.
- Receipt totals: `total_fee` = the applicant’s contracted service/package fees converted into the
  receipt currency; `paid_amount` = **every** income recorded for that applicant converted into the
  receipt currency. `balance = max(0, total_fee - paid_amount)`.
- “Save in loans” = create a `PendingTransaction` (type `income`/`expense`) instead of writing the
  ledger row directly; direct save keeps today’s behaviour.
- Removing `company` from income/expense is done in validation, resources, TS contracts and UI.
  The DB columns stay (existing data, other features reference them) — the intent is “no company
  column/field”, not a destructive migration.

## Implementation steps & progress

- [x] Plan documented (this file).
- [x] **S1 Migration** — `2026_09_29_000002_add_receipt_and_invoice_letterheads_to_settings.php`
      adds `receipt_letterhead*` and `invoice_letterhead*` (file, source, safe x/y/width/height).
- [x] **S2 Backend settings** — `Setting` fillable + casts, `SettingResource` (raw + resolved
      `letterheads.receipt|invoice`), `UpsertSettingRequest` rules/messages,
      `SettingController` file dirs + removable-file flags; shared resolver in
      `app/Support/DocumentLetterheads.php`.
- [x] **S3 Settings → Documents UI** — receipt + invoice letterhead cards below the contract
      letterhead/cover cards: source select (none / company contract letterhead / custom upload),
      upload + preview, and a pointer-resizable safe-zone editor mirroring the contract editor.
- [x] **S4 Receipt backend** — `app/Services/IncomeReceiptService.php` +
      `GET /incomes/{income}/receipt` (`IncomeController::receipt`, `permission:view_incomes`)
      returning company, applicant, invoice, contracted services/packages, totals, letterhead and
      signature. `total_fee` = contracted service + package fees; `paid_amount` = **every** income
      for the applicant, converted into the receipt currency.
- [x] **S5 Receipt frontend** — `resources/ts/api/receipts.ts`;
      `resources/ts/pages/templates/receipt_template.vue` (letterhead background + safe zone,
      N copies stacked bottom-to-bottom, RTL/LTR via logical CSS, signature/stamp block,
      serial, total/paid/balance, services & packages); `ReceiptPrinter.vue` dialog with
      paper-size (A3 landscape default, A4, A4 landscape, A5, Letter) and copies; a print button on
      every income row; `ApplicantSelect.vue` (disabled while an invoice is linked).
- [x] **S6 Invoice letterhead** — `invoice_template.vue` accepts `letterheadUrl` +
      `letterheadSafe`; `innvoice.vue` passes the resolved invoice letterhead from the company
      profile. The container drops its built-in padding when a letterhead is used and the content
      is positioned inside the safe zone.
- [x] **S7 Income/Expense company removal** — dropped from validation, resources, TS contracts,
      income/expense forms and filters. The DB columns are intentionally kept for existing rows
      and other consumers; the product requirement was “no company column/field”.
- [x] **S8 Save-as-loan** — income and expense add-forms offer **Record directly** vs
      **Send to loans**; the loan path calls `POST /loans` (pending transaction) instead of writing
      the ledger row.
- [x] **S9 Cross-cutting** — receipt printing reuses `view_incomes`; settings artwork changes are
      audited through the existing `Setting` `Auditable` trait; receipt serial prefix changed from
      `RCP` to `RCPT` (`RCPT-{year}-{000123}`, the invoice shape); en / fa-AF / ps-AF strings added
      for all new UI and receipt labels.
- [x] **S10a Audit, permissions & notifications (second pass)** — see the section below.
- [ ] **S10 Verification** — see the verification section below.

## Second pass — audit, permissions & notifications (2026-09-29)

### RBAC
- No new permissions were invented. The receipt endpoint and the letterhead settings reuse the
  existing finance/settings permissions, and the loans (pending-transaction) route group already
  ships in the seeder for `manager`, `staff` and `finance_officer`.
- `resources/ts/utils/apiAuthorization.ts` now mirrors the routes this feature actually calls so
  the frontend does not fire obviously-forbidden requests:
  - `GET /incomes/:id/receipt` → `view_incomes`
  - `GET|POST /loans`, `PUT /loans/:id` → `view_`/`create_pending_transactions`
  - `POST /loans/:id/review|approve|reject` → `approve_pending_transactions`
  - `POST /loans/:id/post` → `post_pending_transactions`

### Notifications
- `EducationalWorkflowNotificationService::incomeRecorded()` mirrors `invoiceIssued()`: when an
  income (receipt) is recorded, the applicant's responsible employee is notified with the receipt
  number, applicant label and amount, deep-linked to `/income`, deduplicated via
  `income.recorded.{id}` and filtered by the existing `finance_updates` notification preference.
  Fired from `IncomeController::store` (the actor never receives their own notification). The loan
  path is already covered by `pendingSubmitted` / `pendingStatusChanged`.
- `notifications.income.recorded.{title,message}` added to en / fa-AF / ps-AF.

### Audits
- `Setting` writes audits via the existing `Auditable` trait, so letterhead uploads, source
  switches and safe-zone edits are all captured; `AuditPresenter` already hides
  `letterhead`/`signature` columns from change payloads, so artwork bytes never leak into audit
  rows.
- `AuditPresenter` now resolves three previously opaque ids into human labels:
  `base_currency_id` → currency, `recorded_by` → user (both common on incomes/expenses), and
  `pending_transaction_id` → pending transaction (the loan a posted income/expense came from).
- `AuditRegistry::subjectLabel()` gains a `pending_transactions` candidate list
  (`reason`, `transaction_type`) so loan-related audit rows read as text, not ids.

### Tests
- New `tests/Feature/IncomeReceiptTest.php` covers: the formal receipt payload (serial, party,
  services, totals, signature), letterhead source switching + safe-zone round trip, the
  `view_incomes` permission gate (forbidden without, allowed for `finance_officer`), the
  `income.recorded` notification, and both income + settings-letterhead audit rows.

## Progress log

- 2026-09-29 — Recon complete, design decided, plan written.
- 2026-09-29 — S1–S9 implemented (see steps above). Remaining verification (S10) and the
  follow-ups listed at the end of this file.

## Verification performed (2026-09-29)

- `php -l` clean for every changed/new PHP file (migration, support, service, controller,
  request, resources, model, notification service, audit presenter/registry, tests).
- `node -e JSON.parse(...)` clean for `en.json`, `fa-AF.json`, `ps-AF.json`.
- `npm run typecheck` (`vue-tsc --noEmit`) → **26 errors, all pre-existing** and unchanged across
  runs (same set before and after this work). No error is reported in any file touched here,
  including `apiAuthorization.ts`.
- `node scripts/i18n/check.mjs` — no missing translation keys for the new UI; the previously
  untranslated literals in `ReceiptPrinter.vue` were moved to locale keys (the remaining
  untranslated-literal backlog is pre-existing across the app).
- Feature tests attempted (`vendor/bin/phpunit --filter CompanySettingsTest`) but the local MySQL
  test database is unavailable in this environment (`SQLSTATE[HY000] [2002] Connection refused`)
  — the same limitation documented in `CONTRACT_OPTIONS_VARIANTS_PROGRESS.md`. The new
  `IncomeReceiptTest` must be run against a disposable database before merging.
- ESLint on the touched files could not complete within the command timeout in this environment;
  it should be run locally before merging (`npx eslint <files>`).
- **Not yet run:** migrations against a disposable database and the feature suite. Run
  `php artisan migrate` in a disposable DB and exercise the receipts/letterheads flows in the
  browser before merging.

## Follow-ups / deliberately left for a next pass

- **Receipt totals for a specific service**: `paid_amount` currently tracks *all* payments for the
  applicant (per the request), not a per-service allocation, because `incomes` has no service line
  foreign key. A per-service breakdown needs a new `incomes.contract_service_id` (or an allocation
  table) — out of scope for this pass.
- **Invoice letterhead selection in the invoice form**: the invoice template honours the configured
  letterhead, but there is no per-invoice override yet.
- **Receipt letterhead source “contract”** reuses the general company contract letterhead; it does
  not resolve per-contract-type artwork (`contract_document_designs`).
- The settings UI stores the safe zone in percentages of the document card, so the receipt’s safe
  box is relative to each half-sheet copy.
- ESLint + migration/test verification (S10) as described above.
