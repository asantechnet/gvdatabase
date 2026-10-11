# Applicant-first visa firm restructure

**Status:** planning — 2026-09-27  
**Related plans:** `customer-applicant-merge.md`, `service-contract-linkage-plan.md`  
**Goal:** complete the conversion from a generic software-services CRM into an educational-visa firm where one **Applicant** is the customer, payer, visa case, service owner, contract party, and invoice party.

---

## 1. Product rules

1. There is no customer entity or customer selector. `Applicant` is the commercial party and the visa case.
2. Applicant identity is person-first: `name`, contact details, address, tax number, marketer, and applicant portal identity. The legacy `company` field is removed from the applicant schema, API, forms, resources, reports, and presentation.
3. An applicant can be captured as an intake record before their file is complete. A labeled applicant document is required before the applicant can be promoted to an active case or sold a service. This is the proposed default pending product confirmation.
4. A document belongs directly to an applicant, has a human label/category, original filename, secure storage path, uploader, and audit history. It is not a task attachment pretending to be an applicant document.
5. Every purchased visa service belongs to one applicant. It generates exactly one template-backed service contract; a repeat purchase becomes an extension contract. See `service-contract-linkage-plan.md`.
6. Every invoice belongs to one applicant and invoices only that applicant’s active contracted service lines. Invoice snapshots preserve the applicant’s bill-to identity at issue time.
7. An applicant page is the operational workspace: identity, document readiness, case stage, services/contracts, invoices/payment state, universities, tasks, and finance appear together without a customer/case hand-off.

## 2. Decision gate

| Question | Proposed default | Why it matters |
| --- | --- | --- |
| When are documents mandatory? | Allow draft/intake creation; require at least one labeled document before case activation, service assignment, invoice issue, or portal invitation. | Avoids an impossible create flow while preserving a hard compliance gate before operational work. |
| Which document counts as the minimum? | Any non-empty labeled document initially; configure stricter country/service checklists later. | Supports all visa programs without guessing a universal passport checklist. |
| What replaces `company`? | Nothing on applicant. Company/legal details remain on company, employee, and immutable invoice snapshot records where appropriate. | Keeps the party model person-first and removes leftover generic-CRM meaning. |

**Implementation is paused at the gate only if the product owner requires documents to block the very first applicant POST.** In that case, the creation UI/API must switch to a multipart intake transaction that creates the applicant and first document atomically.

## 3. Workstreams and acceptance criteria

| #   | Workstream                 | Acceptance criteria                                                                                                                                                                        | Status                                             |
| --- | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------- |
| A   | Applicant model cleanup    | No applicant `company` DB column, payload field, resource field, frontend column, or report field remains.                                                                                 | Implemented; migration pending execution           |
| B   | Applicant documents        | Labeled uploads/list/download/delete; authorization, validation, audit events, notification hook, and document-readiness guard exist.                                                      | Implemented; focused tests pending DB availability |
| C   | Applicant directory        | Useful responsive table shows applicant, contact, case stage, document readiness, active services, unpaid invoice amount, owner, and concise actions.                                      | Planned                                            |
| D   | Applicant workspace        | Case page shows documents, commercial summary, contracts/services, invoice actions, and operational actions as coherent panels.                                                            | Planned                                            |
| E   | Service → contract linkage | Complete B1–B8/F1–F6/C1–C3/T1–V1 from `service-contract-linkage-plan.md`.                                                                                                                  | Implemented; validation pending DB availability    |
| F   | Applicant invoicing        | Invoice APIs, types, pickers, forms, snapshots, payment contexts, reports, and deep links use `applicant_id` only.                                                                         | Implemented; validation pending DB availability    |
| G   | Cross-system cutover       | Tasks, projects, packages, financial transactions, searches, CRM conversions, dashboards, reports, and audit presenters have no operational `customer_id`/`customer` relation assumptions. | Planned                                            |
| H   | Compliance                 | RBAC, policy checks, translations (`en`, `fa-AF`, `ps-AF`), notifications, audits, factories/seeders, and feature tests cover every new operation.                                         | In progress                                        |

## 4. Delivery plan

### Phase 1 — data integrity and documents

- [ ] Add `applicant_attachments` migration: `applicant_id`, `label`, optional controlled `category`, original/stored filename, mime type, size, `uploaded_by`, timestamps, soft delete.
- [ ] Add `ApplicantAttachment` model, applicant relation, secure storage/download controller, API resource, policy, and audit registry/presenter mapping.
- [ ] Add API routes: list/upload/download/update-label/delete at `/applicants/{applicant}/attachments`.
- [ ] Validate upload size/type and prevent cross-applicant access; never expose raw storage paths.
- [ ] Add `ApplicantDocumentReadiness` service. It reports count/labels and enforces the agreed operational gate in stage change, service assignment, contract generation, invoice issue, and portal invitation.
- [ ] Remove `applicants.company` in a migration only after every affected API/resource/seed/report/UI is updated.
- [ ] Backfill/retain existing identity data safely; do not delete attachment/task evidence.

### Phase 2 — applicant experience

- [ ] Rebuild the applicant directory as an applicant table, not the old customer directory renamed in place.
- [ ] Show document readiness as a clear chip with a direct upload action; distinguish intake from active case.
- [ ] Add quick actions: open workspace, edit applicant, upload document, assign service/contract, create invoice, and invite portal (permission-gated).
- [ ] Add table filters for owner, stage, readiness, country, university, and commercial state; retain mobile cards and meaningful empty/loading/error states.
- [ ] Add a Documents panel to the applicant workspace with drag/drop upload, label field, file metadata, secure download, rename label, and deletion confirmation.
- [ ] Add a Commercial panel for active services, linked contracts, extension status, invoice balances, and the next appropriate action.
- [ ] Restore coherent styles using existing workspace/table tokens; avoid relying on missing global CSS or hard-coded customer labels.

### Phase 3 — service contracts and packages

- [ ] Execute service-linkage B1–B8: contract service source/role relations, service template resolution, generator, assignment/package/controller wiring, and notification hook.
- [ ] Execute F1–F6: service template visibility, fluent assignment UX with editable fee and extension notice, contract selection, and applicant case display.
- [ ] Add document-readiness guard before `ServiceContractGenerator::createFor()` and package issuance.
- [ ] Ensure generated contracts use applicant identity for parties, placeholders, rendered content, document design, and template version snapshot.

### Phase 4 — applicant invoicing and finance

- [ ] Replace frontend `CustomerApi` invoice selection with applicant search/select and `applicant_id` request/response types.
- [ ] Invoice picker loads only the selected applicant’s active contract-service lines; block cross-applicant service lines server-side.
- [ ] Replace invoice/customer display fields with applicant identity while retaining immutable legacy snapshot column names only at the persistence boundary.
- [ ] Update income, expense, pending transaction, report filters, printable invoice, payment context, and invoice deep links to use applicant relation/type names.
- [ ] Ensure applicant workspace exposes invoice issue, invoice list, payment status, and unpaid balance with existing RBAC checks.

### Phase 5 — system-wide cleanup

- [ ] Remove operational `customer_id` input rules, TypeScript fields, API calls, routes, search groups, navigation wording, page guides, and stale customer relationship accessors.
- [ ] Convert lead/visitor conversion copy and CRM attribution to “applicant”; retain contract-type `customer` vocabulary only where documented in the merge plan.
- [ ] Update reports/dashboard metrics from generic customer wording to applicant/client wording suitable for visa operations.
- [ ] Review project/task/board/package linkage to require the same applicant wherever a relation has both a commercial and case context.
- [ ] Add `applicant.*` contract placeholder aliases while keeping documented `customer.*` compatibility aliases.

### Phase 6 — compliance and verification

- [ ] RBAC: define/reuse granular permissions for applicant documents, applicant commercial actions, invoices, and portal invitations; seed role defaults and front-end visibility checks.
- [ ] Notifications: applicant document uploaded/readiness achieved, service contract generated, invoice issued/paid/overdue, and case assignment/stage changes.
- [ ] Audits: applicant attachment lifecycle and all commercial links resolve labels in audit history.
- [ ] Translation keys: add matching `en`, `fa-AF`, and `ps-AF` keys; no raw English on changed applicant screens.
- [ ] Tests: migration preservation, attachment authorization/validation, readiness guard, service contract primary/extension, package generation, applicant invoice isolation, RBAC/audit/notification behavior, and applicant directory API shape.
- [ ] Verify with migrations/seeding, focused feature tests, full regression comparison against `customer-applicant-merge.md`, frontend typecheck, and locale-key validation.

## 5. Boundaries and migration safety

- Contract-template version/artwork work is shared with the parallel workstream. The service generator will consume its public model/API rather than rewrite template versioning or artwork code.
- Immutable invoice snapshot columns may retain `customer_*` names internally; all application-facing relations and requests are applicant-first.
- The migration that drops `applicants.company` must run only after application code no longer reads it. It will preserve no duplicate company value because that value is intentionally retired from applicant identity.
- Existing task attachments remain task evidence. They are not silently moved into applicant documents.

## 6. Progress log

| Date | Progress |
| --- | --- |
| 2026-09-27 | Read `customer-applicant-merge.md` and `service-contract-linkage-plan.md`; created this integrated implementation plan. |
| 2026-09-27 | Added applicant attachment migration/model/controller/routes, labeled secure upload/download/delete/rename API, and document readiness guards for case-stage changes, service-contract generation, and invoicing. Began removal of applicant `company`. |
| 2026-09-27 | Added applicant document panel/upload actions to the case workspace and migrated the invoice API/front-end request path to `applicant_id` with applicant-contracted service selection. |
| 2026-09-27 | Completed service-workspace applicant-first API/types/form conversion, added employee-scope authorization and notifications for applicant attachments, registered attachment audits, required a document before portal invitation, and aligned income/expense/package client payloads with applicant IDs. |
| 2026-09-27 | Added applicant-document and invoice-issued notification localization in all supported locales; completed applicant-only invoice service loading and applicant finance payload validation. PHP syntax and locale JSON validation pass; database-backed Laravel checks remain unavailable because the configured MySQL test database cannot be reached. |
