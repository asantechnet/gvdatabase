# Settings → Documents: artwork scoping, per-page sizing & print precedence

**Status:** in progress — plan written 2026-09-27
**Area:** `settings → documents` page (`resources/ts/pages/setup/settings.vue`), contract
document-design persistence (`contract_document_designs`), contract print layer
(`ContractDocumentPrinter.vue`, `contract_list.vue`, `contract_page_template.vue`)
**Related:** `contract-template-sanifier.md` already covers the backend resolution
and snapshot work; this document is the canonical plan for the **UI restructure**
requested in this iteration.

---

## 1. What was asked (intent → acceptance criteria)

| # | Request | Acceptance criteria |
|---|---------|---------------------|
| AC1 | Contract cover / letterhead must be added through the **existing file selects** at the bottom of the documents page, not a separate top section. The contract-type / service **scope selectors must live in the bottom artwork area**. | There is exactly one artwork editor: the bottom artwork cards (preview + Upload + Remove + sizing). The scope selectors are rendered inside that same bottom artwork area, with no separate top card. |
| AC2 | The **letterhead sizing** control must really affect **every page** of the final print layer, and work **differently per contract type**. | The safe area (`safe_x/y/width/height`) chosen for a scope is persisted per scope and applied to **every** printed page (party-details page + all content pages) of contracts of that type. |
| AC3 | The **service** select appears **only when a customer contract type is selected**. | The service select renders only for the type whose `slug === 'customer'`; hidden for `employment`, `other` and custom types. Backend rejects a service-scoped design for any other type. |
| AC4 | No service selected ⇒ the **customer-wide** cover/letterhead applies to **all services**. | Type-level customer design (`service_id = NULL`) resolves for every customer contract. |
| AC5 | A selected service **takes precedence** over the customer-wide design; the print layer uses that service's cover, letterhead **and sizing** when that service is on the contract. | Resolution order: service design of a service on the contract → customer-wide design → general settings artwork. |
| AC6 | **Employment / other** contracts have exactly **one** cover + letterhead that automatically applies to all contracts of that type. | No service scope for those types; one type-level design row applies to every employment / other / custom contract. |

---

## 2. Current state audit (evidence)

### 2.1 Already implemented (working tree, from the prior iteration)

* **Scoping rules** live in `app/Services/ContractDocumentDesignService.php`
  (`SERVICE_SCOPED_SLUG = 'customer'`, `resolve()`, `resolveForContract()`,
  `snapshot()`, `snapshotForContract()`), used by `ContractController::store/show`
  and `PackageIssuanceService::issue`. Backend tests exist in
  `tests/Feature/ContractDocumentDesignTest.php` (service precedence, customer-wide
  fallback, employment single design, read-time backfill, package issuance, file cleanup).
* **Print layer** (`ContractDocumentPrinter.vue`, `contract_list.vue`) passes the same
  resolved `company` (letterhead URL + safe area) to the cover and to **every**
  `ContractPageTemplate`; `contract_page_template.vue` applies the safe-area CSS vars to
  every page in both screen and `@media print`.
* **i18n** keys for the scope UI exist in `en.json`, `fa-AF.json`, `ps-AF.json`.

### 2.2 Gap this iteration closes (AC1)

`settings.vue` currently renders the artwork scope selectors in a **separate top
`VCard`** (`artworkScopeTitle`) above the artwork cards. The file selects themselves are
already only in the bottom cards, but the scope selection is visually a separate top
section. Per the chosen direction, the scope selectors move **into the bottom artwork
area** and the top card is removed.

### 2.3 Relevant files

* `resources/ts/pages/setup/settings.vue` — scope state + template (`v-else` documents branch).
* `resources/ts/api/contractDocumentDesigns.ts` — typed payload (`safeArea`, remove flags).
* `app/Services/ContractDocumentDesignService.php`, `app/Http/Controllers/ContractDocumentDesignController.php`.
* `resources/ts/components/contract/ContractDocumentPrinter.vue`, `resources/ts/pages/contract_list.vue`, `resources/ts/pages/templates/contract_page_template.vue`.

---

## 3. Target behaviour

### 3.1 One artwork editor (AC1)

The documents page keeps a single artwork section:

1. **Scope selectors** (contract type; service only for `customer`) — rendered inline in
   the bottom artwork area, not in a separate top card.
2. **Existing artwork cards** — Letterhead + Cover, each preview / Upload / Remove, with
   the print-safe-area editor on the letterhead card. These are the only file inputs and
   they route to the active scope.
3. Scope badge (chip) + `Save artwork for this scope` / `Reset artwork scope` actions for a
   design scope; the page-level `Save changes` keeps owning the general settings record.

| Scope | Persisted target |
|---|---|
| General default (no contract type) | `settings.contract_letterhead[_safe_*]`, `settings.contract_cover_design` |
| Contract type (no service) | `contract_document_designs` row `(type, service_id = NULL)` |
| Customer type + service | `contract_document_designs` row `(type, service_id)` |

### 3.2 Sizing & precedence (AC2, AC4, AC5, AC6)

Unchanged from the existing implementation, which this iteration verifies rather than
rewrites: service design → customer-wide design → general settings; snapshot frozen at
issue, resolved at read time only when a contract carries no design snapshot.

---

## 4. Implementation steps

### Frontend

- [ ] **F1** `settings.vue`: remove the separate top `VCard` (`artworkScopeTitle`), keep
  `documentArtworkHint`, and render the contract-type/service selects, scope hints, badge
  and save/reset inline in the bottom artwork section (the same `VRow` that holds the
  artwork cards).
- [ ] **F2** Confirm the artwork cards still route preview/upload/remove/sizing through the
  active scope (`activeArtworkPreview`, `handleArtworkUpload`, `removeArtwork`,
  `updateSafeArea`).

### Verification

- [ ] **V1** `npx eslint resources/ts/pages/setup/settings.vue`.
- [ ] **V2** Run `vendor/bin/phpunit --filter ContractDocumentDesignTest` and the
  neighbouring settings/contract tests.
- [ ] **V3** Walk the manual checklist in §6.

---

## 5. i18n

No new keys are required; the scope UI reuses `artworkScopeTitle`-adjacent keys already
present in `en.json`, `fa-AF.json`, `ps-AF.json` (`contractTypeScope`,
`serviceScopeOptional`, `serviceScopeHint`, `singleTypeScopeHint`, `artworkEditorScopeHint`,
`saveArtworkForScope`, `resetArtworkScope`, `artworkScopeSaved`, `printSafeArea*`).

---

## 6. Manual verification checklist

1. Documents page → no separate top artwork card; scope selects sit in the bottom artwork area.
2. General default scope → upload + resize → **Save changes** persists via the settings record.
3. Customer type, no service → upload + resize → **Save artwork for this scope**; reopening shows it.
4. Customer type + service → saved as its own row; the customer-wide row is untouched.
5. Print a customer contract with that service → that service's cover, letterhead and sizing on **every** page.
6. Print a customer contract with a different service → the customer-wide design is used.
7. Employment / other → no service select; one design; every contract of that type uses it.

---

## 7. Constraints & risks

* The customer → applicant merge has other uncommitted changes in the tree; edits here are
  surgical and confined to `settings.vue` + this document.
* No backend changes are planned; the design table, resolution service and print layer already
  satisfy AC2–AC6 and are test-covered.

---

## 8. Progress log

| step | result |
|---|---|
| Recon: settings page, design API, resolution service, printers, tests | done — evidence in §2 |
| Plan written | done |
| **F1** `settings.vue`: removed the separate top `VCard` (`artworkScopeTitle`), moved the contract-type / service selects, scope hints, badge and save/reset inline into the bottom artwork `VRow` (first full-width `VCol`), so the bottom artwork cards are the only file inputs and the scope selectors live with them | done |
| **F2** Artwork cards unchanged: `activeArtworkPreview` / `handleArtworkUpload` / `removeArtwork` / `updateSafeArea` still route preview, upload, removal and sizing to the active scope (design row vs general settings) | done — verified in source |
| **V1** `npx eslint resources/ts/pages/setup/settings.vue` | new template comment lint issues fixed; remaining reports are pre-existing project-wide resolver/padding noise unrelated to this change |
| **V2** `npm run typecheck` | no `settings.vue` errors — the remaining `error TS` lines are pre-existing in the customer→applicant merge files (`ContractForm`, `applicant-*`, `contract/policies`, `educational-master-data`, `expenses`, `recurring-events`) |
| **V3** Backend print/resolution verification | `ContractDocumentDesignService` + `ContractDocumentDesignController` + printer already satisfy AC2–AC6 and are covered by `tests/Feature/ContractDocumentDesignTest.php`; manual checklist in §6 pending |

### Not done (follow-up)

* **V2b** `vendor/bin/phpunit --filter ContractDocumentDesignTest` was not run in this session (DB-backed).
* **V3b** Browser walk-through of §6 (scope switching, per-page sizing on print) was not performed.
* `artworkScopeTitle` / `artworkScopeHint` i18n keys are now unused by the template (harmless; the checker reports 2081 unused keys already).
