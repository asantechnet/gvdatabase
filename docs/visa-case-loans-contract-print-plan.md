# Visa Case, Loans & Contract Print — Plan & Progress

> Living document. Source request: `docs/Educational New Comments++.md` (Task Scope:
> Visa Case Page, loans, contract template).
> Related: `visa-case-enhancements-plan.md`, `contracts-extend-versioning-print-plan.md`,
> `RECEIPTS_AND_DOCUMENT_LETTERHEADS_PLAN.md`.

## 1. Requests → diagnosis → decisions

### R1. Remove the "Services Sold" section from the visa case
- **Diagnosis.** The visa case page (`resources/ts/pages/applicant-case.vue`) renders a
  `t('educational.caseUi.servicesSold')` card gated by `canViewServices` (mapped to
  `view_applicant_contracts`). It lists `applicant.services` rows and, per row, a
  print-contract button that opens the shared `ContractDocumentPrinter` dialog
  (`openContractPrint`).
- **Decision (confirmed).** Remove the whole "Services Sold" list block (title, list,
  "Open contracts" button and empty state). Keep the applicant service details in the
  edit dialog (`ApplicantServiceFields`) untouched.
- **Cleanup.** The block was the only consumer of `openContractPrint`,
  `openServiceContracts`, `canViewServices`, `canOpenContracts`, `canViewContracts`,
  `feeOf`, `contractPrintDialog`, `contractToPrint`, `printingContractId`,
  `ContractDocumentPrinter` and `ContractApi` on this page, so all were removed to
  avoid dead code. `useRouter` became unused (only `openServiceContracts` used it) and
  was dropped from the import.

### R2. Loans need printing, mirroring income receipts
- **Diagnosis.** "Loans" are the public face of `PendingTransaction`
  (`/loans` routes → `PendingTransactionController`); there is **no `Loan` model**.
  Incomes already print through a fully built A5 workflow:
  `IncomeController::receipt` → `IncomeReceiptService::forIncome` (payload) →
  `resources/ts/api/receipts.ts` → `ReceiptPrinter.vue` → `receipt_template.vue` →
  `utils/printReceipt.ts`. Loans have no equivalent.
- **Decision (confirmed).** Replicate the income-receipt design for loans **without
  duplicating logic**:
  - A5-receipt-style document, same template/pipeline as income receipts.
  - Printable for **all** statuses, available to anyone who can view loans
    (`view_pending_transactions`).
  - Backend builds a loan-flavoured payload; frontend reuses the existing receipt
    template + print host. Only labels/data differ.
- **Serial.** `pending_transactions` has no number column. The service derives a
  display serial (e.g. `LN-<year>-<id padded>`) instead of adding a migration, so a
  reprint is always stable and no schema change is required.

### R3. Contract template print — party details take a whole page
- **Diagnosis.** In `ContractDocumentPrinter.vue`, when the "include party details"
  option is on, the party block is rendered as its **own** `ContractPageTemplate`
  instance before the content pages. Each `ContractPageTemplate` is a locked 297 mm
  A4 page (`height: 297mm; break-inside: avoid`), so the parties always occupy a full
  page even when they are short.
- **Decision (confirmed).** Merge the party block **onto the first content page**,
  inline above the contract text. `ContractPageTemplate` already supports a
  `partyDetails` prop rendered inside `.contract-body` (compact two-column cards,
  `break-inside: avoid`), so the standalone party page is removed and `partyDetails`
  is passed to the first printed page only. Page-number strings are recomputed since
  there is no extra page.

### R4. Cover zigzag bleeds onto every page
- **Diagnosis.** The default cover (`contract_cover_template.vue`) draws its signature
  zigzag with
  `clip-path: polygon(0% 0%, 100% 0%, 100% 70%, 82% 76%, 64% 71%, 50% 80%, 30% 73%, 14% 78%, 0% 72%)`
  on `.top-section`, and repeats it in the global print rule
  `:global(body.printing-contract-document) .top-section`. The clipped (transparent)
  band reveals the white/light-grey `.bottom-section` behind it, producing a white
  zigzag edge that reads as an overlay and is not confined to a single page.
- **Decision (confirmed).** **Remove the zigzag entirely** — drop both `clip-path`
  declarations so the blue section meets the light section on a clean straight edge.
  `.contract-cover--custom` already sets `clip-path: none`; that rule is kept (now
  redundant but harmless). Comment updated.

## 2. Cross-cutting updates
| Area | Change |
|---|---|
| Routing | R2 adds `GET /loans/{pendingTransaction}/receipt` (and the legacy `pending-transactions` alias) in `routes/api.php` (inside the existing loans/pending group, `permission:view_pending_transactions`). |
| RBAC mirror | R2 adds `'GET /loans/:id/receipt': ['view_pending_transactions']` to `resources/ts/utils/apiAuthorization.ts`. |
| API contracts | R2 adds `PendingTransactionApi.receipt(id)` and a loan payload type in `resources/ts/api/pendingTransactions.ts`. Response unwrap stays in the API layer (double `data`). |
| i18n | R2 adds `documents.loan.*` (or `documents.receipt.loan*`) strings in `en`, `fa-AF`, `ps-AF`. R1 removes no keys (unused keys are harmless; reuse elsewhere). |
| Print CSS | R2 reuses the namespaced `utils/printReceipt.ts` host/`@page` pipeline — no new global `@page` rules. R4 touches only the cover's own scoped print rules. |
| Documents | No new models/migrations. R2 is additive read-only. R4 is CSS-only. |

## 3. Implementation steps
- [x] Investigation + this plan
- [x] **R1** Remove "Services Sold" block + unused plumbing in `applicant-case.vue`
- [x] **R4** Remove cover zigzag `clip-path` (screen + print) in `contract_cover_template.vue`
- [x] Typecheck (`npm run typecheck`) after low-risk changes
- [x] **R2.a** Backend: `LoanReceiptService` + `PendingTransactionController::receipt` + `GET /loans/{id}/receipt` (and legacy `pending-transactions` alias) in `api.php`
- [x] **R2.b** Frontend: `PendingTransactionApi.receipt`, generalized `ReceiptPrinter.vue` (loader + recordId props), per-row print button on `pending-transactions.vue`, auth mirror, `ReceiptPayload`/`ReceiptLoan` types
- [x] **R2.c** i18n keys (`documents.loan.*` in en/fa-AF/ps-AF); reuses the namespaced A5 print host + `@page` pipeline
- [x] **R3** Merge party details onto the first content page in `ContractDocumentPrinter.vue`; recompute page numbering
- [x] **Tests** New frontend regression `scripts/loan-receipt-print-regression.mjs` (wiring, RBAC mirror, 3-locale loan-key parity, loan/income label switching via SSR of the real template)
- [x] **Cleanup** Removed 9 now-orphaned i18n keys from all 3 locales: `educational.caseUi.{contracts,noContracts,printContractFailed,servicesSold,serviceSummary,noServices,primaryUniversity,caseEditHint}` and `serviceContracts.open`
- [x] Verification: `npm run typecheck`, `npm run i18n:check`, `npm run i18n:test`, `node scripts/receipt-print-regression.mjs`, `node scripts/loan-receipt-print-regression.mjs`, `php -l`, `PendingTransactionTest` + `IncomeReceiptTest` (SQLite in-memory)

### Broader i18n cleanup method
`scripts/i18n/check.mjs` only sees literal `t()` calls in frontend source, so keys that are
resolved dynamically or from PHP are false-positive "unused". Before removing anything, a
temporary analysis pass built a protection set from every dotted token across
`resources/ts` (minus the locale files), `app`, `routes`, `config`, `database`, `lang`,
`resources/views`, `tests`, `scripts` and root configs:

- exact key tokens anywhere in those trees, and
- namespace tokens ending in `.` (e.g. ``educational.builtInServices.`` from
  ``t(`educational.builtInServices.${service}`)``, ``serviceContracts.`` from
  ``t(`serviceContracts.${status}`)``) protect every child key.

Only keys with **no** exact match and **no** dynamic-prefix ancestor were removed, and the
files are standard `JSON.stringify(obj, null, 2)` output so the edit is a clean line diff
plus empty-object pruning. `i18n:check` then acts as the safety net: if any removed key was
still used, it would surface as *Missing*. 318 keys were provably dead (legacy Vuexy demo
account/billing UI, `components.dataTablePageKpis.*`, legacy CRM pipeline `stageLabels.*`,
removed services/deadline copy, and so on). **1330 unused keys were deliberately kept**
because they are reachable through dynamic prefixes or backend/PHP usage — not removed.

## 4. Detailed design notes

### R2 — loan receipt payload (backend)
`LoanReceiptService::for(PendingTransaction $loan)` mirrors `IncomeReceiptService`
and reuses `DocumentLetterheads::resolve($setting, 'receipt')` and the `Setting`
company payload:

- `serial_number` → stable derived reference (`LN-<year>-<zero-padded id>`).
- `document` → `'loan'` so the template can switch labels.
- `income` block → mapped from the pending transaction: `id`, `name`/`type`
  (`transaction_type`), `description` (`reason`), `amount`, `quantity = 1`,
  `line_total = amount`, `rate` (currency rate), `currency`, `base_amount = amount / rate`.
- `applicant` → same `applicantPayload` fields as the income receipt (the loan's
  optional applicant).
- `contract` / `service` / `services` → `null` / `[]` (loans are contract-agnostic).
- `totals` → `total_fee` = requested amount; the template's "paid/balance" cards are
  replaced by loan-relevant cards (requested amount, approval status, requested date).
- Add approval metadata: `status` (`approval_status`), `requested_at`, `approver`,
  `requester`.

Frontend reuse: `ReceiptPrinter.vue` was generalised to take `recordId` +
`loader(id)` + optional `document` props. `income.vue` now passes
`ReceiptApi.getForIncome`, `pending-transactions.vue` passes
`PendingTransactionApi.receipt`. The paper-size/copies dialog and the namespaced
print host stay single-sourced. `receipt_template.vue` keeps one layout and swaps
section labels via a `labels` computed driven by `receipt.document === 'loan'`.

Implemented files: `app/Services/LoanReceiptService.php`,
`PendingTransactionController::receipt`, `routes/api.php`,
`resources/ts/api/receipts.ts` (`ReceiptLoan`, widened `document`),
`resources/ts/api/pendingTransactions.ts`, `resources/ts/utils/apiAuthorization.ts`,
`resources/ts/components/income/ReceiptPrinter.vue`,
`resources/ts/pages/templates/receipt_template.vue`,
`resources/ts/pages/pending-transactions.vue`, `resources/ts/pages/income.vue`.

### R3 — first-page merge (frontend)
- Delete the standalone `<ContractPageTemplate v-if="includePartyDetails && canShowPartyDetails" …>`
  block.
- On the first content page only (`idx === 0`), pass
  `:party-details="includePartyDetails && canShowPartyDetails ? partyDetailsFor(documentContract, company) : undefined"`.
- Page numbering becomes `page: idx + 1, total: printedPages.length`.

## 5. Progress log
| Date | Step | Result |
|---|---|---|
| 2026-10-11 | Investigation | Root causes confirmed for all four requests (see §1) |
| 2026-10-11 | R1 | "Services Sold" section and all its now-unused plumbing removed from `applicant-case.vue` |
| 2026-10-11 | R4 | Cover zigzag `clip-path` removed (screen + print rules); straight edge |
| 2026-10-11 | R2 | `LoanReceiptService`, `GET /loans/{id}/receipt` (+ legacy alias), typed API, generalized receipt printer, loans-page print button, `documents.loan.*` i18n in 3 locales |
| 2026-10-11 | R3 | Party details no longer a standalone page; rendered inline on the first content page with corrected page numbering |
| 2026-10-11 | Tests | Added `scripts/loan-receipt-print-regression.mjs`; `receipt-print-regression.mjs` still passes after the template generalization |
| 2026-10-11 | Cleanup | Removed 9 orphaned i18n keys across en/fa-AF/ps-AF (English 4548 → 4539 keys); `i18n:check` + `i18n:test` pass |
| 2026-10-11 | Broader cleanup | Removed **318** dead English keys (en 4539 → 4221) and their locale counterparts (fa-AF 313, ps-AF 204; the lower counts are pre-existing drift). `i18n:check` reports no missing keys; `i18n:test` + `typecheck` pass. |
| 2026-10-11 | Verify | `npm run typecheck`, `npm run i18n:check`, `npm run i18n:test` pass; `php -l` clean; `PendingTransactionTest` 5 passed / 33 assertions and `IncomeReceiptTest` 5 passed / 40 assertions with SQLite in-memory |
| 2026-10-11 | Note | The configured test DB is MySQL at `127.0.0.1:3306` (unavailable here); focused tests were run with `DB_CONNECTION=sqlite DB_DATABASE=":memory:"`. Physical A5/A4 print output still needs a manual browser check. |
