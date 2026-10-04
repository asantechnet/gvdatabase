# Visa Case Page Enhancements — Plan & Progress

> Living document. Scope: `resources/ts/pages/applicant-case.vue` (visa case page) —
> packages assignment, contracts print, applicant attachments, finance currency.
> Related: `customer-applicant-merge.md`, `service-contract-linkage-plan.md`.

## 1. Requests → diagnosis → decisions

### R1. Packages must be assignable to the applicant in the visa case
- **Diagnosis.** The backend already supports it: `POST /packages/:id/issue`
  (`PackageController::issue`, permission `issue_packages`) issues a package to an
  applicant and auto-generates one printable contract per packaged service via
  `PackageIssuanceService` + `ServiceContractGenerator`. The frontend API
  (`resources/ts/api/packages.ts` → `list` / `issue`) exists. What is missing is
  (a) any UI for it in the visa case, and (b) the applicant `show` response never
  includes `customerPackages`, so the case cannot display what was sold.
- **Decision.** Add a "Packages" section to the visa case (list + "Assign package"
  dialog). Package issuance stays the single issuance path *from the case* (see R2).

### R2. Contracts section: remove "add contract", add "print" exactly like contracts page
- **Diagnosis.** The case has two contract-creation buttons (contracts section +
  services-sold "New contract") opening the same `ContractForm` dialog. Keeping
  them alongside package issuance means two diverging issuance paths.
- **Decision.**
  - Remove both create buttons and the case `ContractForm` dialog. Contracts reach
    the case via package issuance (auto-generated) or via the contracts pages.
  - Add a per-row **print** button in the contracts section that is *literally the
    same flow* as the contracts page: extract the inline print pipeline of
    `contract_list.vue` (fresh fetch → template fallback → company settings →
    cover/pages/signature/party-details options dialog → A4 print window) into a
    shared `ContractPrintDialog.vue` used by **both** pages. No duplicated logic
    to drift apart.

### R3. Applicant attachments reject every file ("enter a valid value") + no roles imposed
- **Diagnosis (two bugs).**
  1. `ApplicantAttachmentApi.upload` posts `FormData` **without** a multipart
     `Content-Type`, so the axios `application/json` default wins, Laravel cannot
     parse the body, `$request->file('file')` is `null`, and validation 422s on
     every file. (`TaskApi.uploadAttachment` sets the header explicitly — that is
     why task documents work.)
  2. `resources/ts/utils/apiAuthorization.ts` has **no entries** for the five
     `applicants/:id/attachments` endpoints, and `restPermission()` only matches
     two-segment paths, so the client-side RBAC mirror imposes no roles on
     attachment list/upload/edit/download/delete (backend middleware + controller
     scope checks still enforce server-side).
- **Decision.** Send multipart headers; add the five authorization entries
  (`view_applicants` for read/download, `edit_applicants` for write) mirroring the
  backend route middleware; add a client-side validation schema for the upload;
  gate the UI card (view) and upload/delete controls (edit); add `accept` filter +
  size display to the file input. Backend `mimes` gains `csv` for parity with task
  attachments (10 MB limit unchanged).

### R4. Finance "new transaction" form only allows the base currency
- **Diagnosis.** `CurrencySeeder` intentionally leaves every non-base currency
  `is_active = false` (rates must be reviewed). `PendingTransactionController::options()`
  (served as `/loans/options`) filters `where('is_active', true)`, so only AFN is
  ever offered. `post()` additionally `findOrFail`s an *active* currency.
- **Decision.** The request form lists **all** currencies (active first, inactive
  marked), defaulting to base. Oversight is preserved by the approval chain
  (submit → review → approve → post, self-approval blocked), and `post()` now
  accepts any currency with a positive rate instead of requiring `is_active`.
  Base-currency resolution still requires the active base. Validation on `store`
  (`exists:currencies,id`) already permits any currency — unchanged.

## 2. Cross-cutting updates
| Area | Change |
|---|---|
| Translations (en / fa-AF / ps-AF) | New `educational.caseUi` keys: packages section, assign dialog, print action, attachment hints, inactive-currency mark, finance dialog strings (previously hardcoded English) |
| Audits | No model changes. `CustomerPackage` and `ApplicantAttachment` already use `Auditable`; package/attachment/finance writes keep flowing through audited paths |
| Notifications | Reused as-is: `packageIssued`, `applicantDocumentUploaded`, `pendingSubmitted` already fire from the touched backends |
| Permissions / RBAC | No new permissions, no seeder changes (`issue_packages`, `view/edit_applicants`, `create_pending_transactions` exist and are already granted to the right roles). Only the frontend mirrors are completed: attachment endpoints + `/loans*` aliases in `apiAuthorization.ts`, upload schema in `apiValidation.ts` |
| API validation mirror | `POST /applicants/:id/attachments` schema added (file/label/category) |

## 3. Implementation steps
- [x] Investigation + this plan
- [ ] A. New `components/contract/ContractPrintDialog.vue` (extracted print flow)
- [ ] B. `contract_list.vue` uses the shared dialog (no behavior change)
- [ ] C. Visa case: drop create-contract UI, add per-row print buttons
- [ ] D. Backend: `ApplicantController::show` loads `customerPackages.*` (gated by `view_applicant_services`); frontend packages section + assign dialog (`issue_packages`); `PackageApi.list({active})`
- [ ] E. Attachments: multipart header, auth/validation entries, UI gates + `accept`, backend `csv` mime
- [ ] F. Finance: `options()` returns all currencies, `post()` accepts any rated currency, frontend type + select, `/loans*` auth entries
- [ ] G. Translations, `php -l`, `vue-tsc` scope check, `i18n:check`

## 4. Progress log
| Step | Result |
|---|---|
| Investigation | Root causes confirmed for all four requests (see §1) |
| … | … |
