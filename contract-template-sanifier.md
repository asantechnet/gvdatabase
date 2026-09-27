# Contract Template & Document Artwork Sanifier

**Status:** in progress — plan written 2026-09-27 (see §9 Progress log)
**Area:** `settings → documents` page (contract artwork), contract document-design persistence, contract print layer

---

## 1. What was asked (intent → acceptance criteria)

| # | Request | Acceptance criteria |
|---|---------|---------------------|
| AC1 | The documents page must **not** add contract cover / letterhead through a *separate* file-picker block on top; it must reuse the **existing file selection** (preview + Upload + Remove) at the bottom of the page. | Only one set of artwork file selectors exists. The top card becomes a *scope picker* (contract type + optional service) plus save/reset actions; uploading / removing / previewing happens in the existing bottom artwork cards. |
| AC2 | The **letterhead sizing** control at the bottom of the page must **really affect every page** of the final print layer, and must **work differently per contract type**. | Safe area (x / y / width / height) edited on the documents page is persisted **per scope** (`contract_document_designs.safe_*` for a type/service scope, `settings.contract_letterhead_safe_*` for the general fallback) and consumed by *every* printed page (party-details page + all content pages) of that contract. Today a design row exists with DB-default safe values that silently **override** the page's chosen sizing, so the drag handles "do nothing" — that is the bug. |
| AC3 | The **service** select must appear **only when a customer contract type** is selected. | Service select renders only for `contract_types.slug === 'customer'`; hidden for `employment`, `other` and custom types. Backend rejects a service-scoped design for any other type. |
| AC4 | No service selected ⇒ the **customer-type** cover/letterhead applies to **all services**. | Type-level customer design (`service_id = NULL`) is the customer default and resolves for every customer contract. |
| AC5 | A selected service **takes precedence** over the customer-type default, and the print layer must use the cover, letterhead **and sizing** chosen for that service when that service is on the contract. | Resolution order: service-level design of a service on the contract → type-level design → general settings artwork. The resolved design (paths **and** safe area) is what the printer uses on every page. |
| AC6 | For **employment** and **other** contracts there is **one** cover + letterhead per type, applied automatically to all contracts of that type. | No service scope for those types; a single type-level design row is auto-applied to all employment / other (& custom) contracts. |

---

## 2. Current state (evidence)

### 2.1 Documents page — `resources/ts/pages/setup/settings.vue` (1318 lines)

* Scope state: lines 84-92 (`selectedDesignTypeId`, `selectedDesignServiceId`, `designLetterhead`, `designCover`, `designSaving`, `selectedDocumentDesign`).
* `loadDocumentDesigns()` (263-269) loads contract types + services + all designs, defaults `selectedDesignTypeId` to the first active type.
* `saveDocumentDesign()` (271-287) posts only `contract_type_id`, `service_id`, `letterhead`, `cover_design` — **never `safe_*`**, never the remove flags.
* The top "Contract-specific artwork" card (1079-1102) uses two **`VFileInput`s** — the duplicate file picker AC1 wants gone.
* The bottom artwork cards (1103-1220) render the *existing* selectors (preview + `file-overlay` upload + remove) bound to `letterheadPreview` / `coverDesignPreview`, i.e. to the **settings record** (`contract_letterhead`, `contract_cover_design`).
* The safe-area editor (1174-1218, styles 1268-1285) is bound to `letterheadSafeAreaStyle` / `resizeLetterheadSafeArea` (344-388), which write **only** `form.contract_letterhead_safe_*` (settings; page field list line 146).
* Consequence: as soon as a *design row* exists for a type/service the printer reads `design.safe_*` (DB defaults 8.5 / 15 / 83 / 70) and the sizing chosen in the UI is ignored → AC2 failure. There is also no UI to remove a design's letterhead/cover although the API supports `remove_letterhead` / `remove_cover_design`.

### 2.2 Design API

* `app/Http/Controllers/ContractDocumentDesignController.php`
  * `index` (12-18): supports `contract_type_id` / `service_id` filters, returns `letterhead_url`, `cover_design_url`.
  * `store` (20-31): `firstOrNew([contract_type_id, service_id])`; `update` (33-38): partial.
  * `validated()` (40-56): accepts letterhead/cover images, `safe_x|safe_y` (0-95), `safe_width|safe_height` (5-100) and remove flags — **but** allows `service_id` for **any** contract type (AC3/AC6 gap) and never deletes superseded files (orphans).
* `resources/ts/api/contractDocumentDesigns.ts`: `getAll()` / `save(id|null, FormData)`; TS interface exposes urls + safe values only.
* Model `app/Models/ContractDocumentDesign.php`; table created in `database/migrations/2026_09_21_000002_...php` lines 24-37, unique `(contract_type_id, service_id)`.

### 2.3 Resolution & snapshot

* `app/Http/Controllers/ContractController.php` 549-555 (create): uses the **first service line only**, ordering `service_id IS NULL` so a service design wins. Snapshot written at 576-583.
* `ContractController::show()` (162-175): returns the raw snapshot, so a contract issued before a design existed keeps `document_design = null` and never receives per-type artwork/sizing.
* `ContractController::update()` (275-305): re-snapshots parties/version but never the design.
* `app/Services/PackageIssuanceService.php` 58-92: package sales create **customer** contracts (one per packaged service) whose `snapshot_data` has **no** `document_design` at all.
* Vocabulary confirmation: `Contract` `getTypeSlugAttribute()`, `ContractForm.vue` `CONTEXT_BY_SLUG` (143-147) → `employment` / `customer` / `other`; `ContractController` line 105 → customers audience = `customer` + `EDUCATIONAL_SLUGS`.

### 2.4 Print layer

* `resources/ts/components/contract/ContractDocumentPrinter.vue` (the "final print page"): `documentDesign` (21); `company` (22) prefers `design.letterhead_url` / `design.safe_*` per field with a settings fallback; the same `company` object goes to the cover and to **every** `ContractPageTemplate` (105-124 preview, 156-184 hidden print body) → per-page sizing works when the design values are right.
* `resources/ts/pages/contract_list.vue`: `snapshotDesign` (171) → `company` (188-192) → every page (939-984). `printContract()` (241-273) prints the **row from the list**; a debug `console.log('CONTRACT ', ...)` is left at line 242.
* `resources/ts/pages/templates/contract_page_template.vue`: safe area → CSS vars (66-70) applied on `.has-letterhead .content-wrapper` (474-479) and again in `@media print` (846-851); letterhead image on every page (218-224). The cover page is full-bleed, sizing does not apply (expected).

### 2.5 Tests

* `tests/Feature/CompanySettingsTest.php` covers settings artwork upload/replace/remove; **nothing** covers `contract-document-designs` or the design snapshot (grep over `tests/` returns no matches).
* Suite runs against MySQL `laravel_test` (`phpunit.xml` + `.env`), PHP 8.5, `vendor/bin/phpunit`.

---

## 3. Target behaviour

### 3.1 One artwork editor, several scopes

The documents page keeps exactly **one** artwork editor — the existing bottom cards (preview / upload / remove + safe-area box). A new **scope picker** on top decides what that editor writes to:

| Scope picker | Persisted target | Save action |
|---|---|---|
| *General default* (`selectedDesignTypeId = null`) | `settings.contract_letterhead[_safe_*]`, `settings.contract_cover_design` | existing page **Save changes** (unchanged wiring) |
| Contract type (no service) | `contract_document_designs` row `(type, service_id = NULL)` | **Save artwork for this scope** (design endpoint) |
| Customer type + service | `contract_document_designs` row `(type, service_id)` | **Save artwork for this scope** |

* Contract type select lists the general option first, then active contract types.
* Service select appears **only** when the picked type is the customer type (AC3). It is optional; leaving it empty edits the customer-wide design (AC4).
* For `employment` / `other` / custom types the scope card states that the design applies to every contract of that type (AC6) and no service select is rendered.
* The scope card keeps "remove letterhead" / "remove cover" affordances for the selected design row (the API already supports the flags; today they are unreachable).
* Artwork previews and the safe-area box always show **the scope being edited** (design row values when a type/service is picked, settings values for the general scope).

### 3.2 Single resolution rule (backend, one implementation)

```
design(contract) =
  service-scoped design for the first service (in contract service order)
      that has a design row with the contract's type + that service      -- only when the type is customer
  ?? type-level design (type, service_id = NULL)
  ?? null                                                               -- printer then falls back to the general settings artwork
```

* Applied **at contract creation** (`ContractController::store`, `PackageIssuanceService::issue`) → the resolved design (paths + safe area) is frozen into `snapshot_data.document_design`.
* Applied **at read time** (`ContractController::show`) **only when the snapshot has no design yet** (contracts issued before designs existed, package-issued contracts, legacy rows) → they immediately print with the type/service artwork and sizing. Issued contracts that already have a snapshot keep their frozen design (legal-document rule, consistent with `content`).
* Service scope is rejected for non-customer types (`422`), so employment/other can never end up with a service design.

### 3.3 Print layer

* Both print paths (`ContractDocumentPrinter.vue` dialog and `contract_list.vue` hidden print body) keep passing the same resolved `company` (letterhead URL + safe area) to the cover and to **every** page, so the sizing picked per scope applies to each printed page.
* `contract_list.vue` will fetch the fresh contract before printing so read-time resolution is included (it already does this for the `?print=1&id=` entry path).
* No page template change is required: `contract_page_template.vue` already renders the safe-area vars on every page (`@media print` block included).

---

## 4. Design decisions

1. **Snapshot + read-time backfill** rather than live re-resolution: documents that were already issued keep the artwork they were issued with; contracts *without* a design snapshot pick up the current per-type/service design. Documented in §8.
2. **Resolution lives in one place** — a new `App\Services\ContractDocumentDesignService` used by the contract controller, the package issuance service and the read-time backfill. No duplicated query logic.
3. **General default scope is kept** as the ultimate fallback (it is what printers already use when no design row exists), so nothing that works today regresses.
4. **Page-level `Save changes`** continues to own the settings record; the design scope gets its own explicit save button, because designs live in a different table/endpoint.
5. **Fail-safe removal**: removing artwork on a design scope sets the row's column to `null` (printer then falls back to the general default per field, which is the existing behaviour).

---

## 5. Implementation steps

### Backend

- [x] **B1** `app/Services/ContractDocumentDesignService.php` (new):
  - `const SERVICE_SCOPED_SLUG = 'customer'`
  - `resolve(?int $contractTypeId, array $serviceIds = []): ?ContractDocumentDesign` — customer-first, service-precedence, type fallback; loads/caches the small design table once per instance.
  - `resolveForContract(Contract $contract): ?ContractDocumentDesign` — uses loaded `contractServices` when available.
  - `snapshot(?ContractDocumentDesign $design): ?array` — `{ id, letterhead, cover_design, safe_x, safe_y, safe_width, safe_height }`.
  - `snapshotForContract(Contract $contract, ?array $existingSnapshot = null): ?array` — returns the existing snapshot when it already carries a `document_design`, otherwise the resolved design (read-time backfill).
- [x] **B2** `app/Http/Controllers/ContractController.php`:
  - `store()` — replace the inline 549-555 query with the service; snapshot via `->snapshot($design)`.
  - `show()` — backfill `snapshot_data.document_design` (in-memory only, no DB write) when the snapshot has none.
- [x] **B3** `app/Services/PackageIssuanceService.php` — add `document_design` to each issued contract's `snapshot_data`.
- [x] **B4** `app/Http/Controllers/ContractDocumentDesignController.php`:
  - reject `service_id` unless the type slug is `customer` (ValidationException),
  - force `service_id = null` for non-service-scoped types,
  - delete superseded letterhead/cover files on replace/removal (mirror `SettingController`),
  - keep `safe_*` validation; expose `safe_*` through `present()` (already via `toArray`).

### Frontend

- [x] **F1** `resources/ts/api/contractDocumentDesigns.ts` — extend the interface (`letterhead`, `cover_design`, `contract_type_id`, `service_id`, `safe_*`), typed `getAll(params?)`, and a `save` that accepts the remove flags.
- [x] **F2** `resources/ts/pages/setup/settings.vue`
  - scope state: `selectedDesignTypeId` (null = general default), `selectedDesignServiceId`, design-side files/previews/safe area, `designDirty`, `supportsServiceScope` computed (`slug === 'customer'`), `activeLetterheadPreview` / `activeCoverPreview` / `activeSafeArea` computed.
  - route uploads, removals and safe-area edits to the active scope (`handleArtworkUpload`, `removeArtwork`, `letterheadSafeAreaStyle`, `resizeLetterheadSafeArea`, `updateSafeArea`).
  - `saveDocumentDesign()` sends files + remove flags + `safe_*`; `resetDesignScopeChanges()`.
  - template: scope card without `VFileInput`s + hint text + per-scope save/reset; bottom artwork cards show the active scope badge and numeric size fields.
- [x] **F3** `resources/ts/pages/contract_list.vue` — fetch the fresh contract in `printContract()` (so read-time design resolution reaches the print body) and drop the stray debug `console.log`.
- [x] **F4** i18n keys in `en.json`, `fa-AF.json`, `ps-AF.json` under `pages.settings.settings` (see §6). `npm run i18n:check` must pass.

### Tests

- [x] **T1** `tests/Feature/ContractDocumentDesignTest.php`:
  - service-scoped design allowed for the customer type / rejected for employment-other,
  - create snapshot: service design wins over the customer type design (paths + safe area),
  - employment contract without a service design gets the single type design,
  - `GET /api/contracts/{id}` backfills a missing snapshot design,
  - design `safe_*` round-trip through the API, superseded file cleanup.
- [x] **T2** package issuance snapshot assertion (customer + service design).
- [ ] **T3** run `vendor/bin/phpunit` for the touched feature tests; `npm run lint`, `npm run typecheck`, `npm run i18n:check`.

---

## 6. i18n keys (en / fa-AF / ps-AF)

| key | purpose |
|---|---|
| `artworkScopeTitle` | title of the scope card |
| `artworkScopeHint` | explains which scope the bottom uploaders edit |
| `generalArtworkScope` | "General default (used when a type has no artwork)" |
| `contractTypeScope` | contract type label |
| `serviceScopeOptional` | "Service (optional)" |
| `serviceScopeHint` | no service ⇒ applies to every service of that contract type |
| `singleTypeScopeHint` | employment/other: one design for the whole type |
| `saveArtworkForScope` | design-scope save button |
| `resetArtworkScope` | discard unsaved artwork/size edits |
| `artworkScopeSaved` | confirmation toast |
| `printSafeAreaSizing` | label above the numeric size fields |
| `printSafeAreaWidth` / `printSafeAreaHeight` / `printSafeAreaOffsetX` / `printSafeAreaOffsetY` | numeric size fields |

---

## 7. Verification

```bash
cd /mnt/c/Users/Mortaza/Desktop/educational
vendor/bin/phpunit --filter 'ContractDocumentDesignTest|CompanySettingsTest|EducationalContractIntegrityTest|VersionedContractGenerationTest|ContractServiceLinesTest'
npm run i18n:check && npm run lint && npm run typecheck
```

Manual checklist (documented, not automatable here):
1. Documents page → General default scope → uploads/sizing still save through *Save changes*.
2. Pick Customer → upload + resize + save → reopen → previews and box persist.
3. Pick Customer + a service → save → separate row; the customer-wide row is untouched.
4. Print a customer contract with that service → cover, letterhead and sizing of the service design on **every** page.
5. Print a customer contract with another service → the customer-wide design is used.
6. Employment / other → no service select; one design; every contract of that type uses it.

---

## 8. Constraints, risks & coordination

* **Another agent is mid-flight on the customer → applicant merge.** Files it already touched in the working tree (`ContractController.php`, `ContractResource.php`, `contract_list.vue`, `ContractDocumentPrinter.vue`, `ContractForm.vue`, `routes/api.php`, i18n files, `AppServiceProvider.php`, …) receive **surgical, minimal** edits only, and each file is re-read immediately before editing.
* The design table stores **paths**; superseded-file cleanup must not delete files still referenced by another row.
* Existing contracts keep their issued artwork (by design) — only contracts *without* a design snapshot get read-time resolution.
* The merge renames the "customer" *party* entity; contract **types** (`slug = customer`) are explicitly out of that rename, so `SERVICE_SCOPED_SLUG = 'customer'` stays valid.
* Print-layer "size affects every page" is bounded by what CSS already supports: the safe-area box is applied to every `ContractPageTemplate` page that carries a letterhead; the full-bleed custom cover image is intentionally unaffected.

## 9. Progress log

| step | result |
|---|---|
| Recon: settings page, design API, resolution, printers, tests | done — evidence in §2 |
| Plan written | done |
| **B1** `app/Services/ContractDocumentDesignService.php` created (`resolve` / `resolveForContract` / `snapshot` / `snapshotForContract` / `forget`, customer-only service scope, per-instance caches) | done — `php -l` clean |
| **B2** `ContractController`: constructor injection, `store()` resolves through the service, `show()` backfills a missing snapshot design in-memory (no DB write) | done — `php -l` clean |
| **B3** `PackageIssuanceService`: every issued customer contract now snapshots the design resolved for its service | done — `php -l` clean |
| **B4** `ContractDocumentDesignController`: service scope rejected for non-customer types (422), forced to `null` otherwise, superseded artwork deleted on replace/remove, deterministic index ordering | done — `php -l` clean |
| **F1** `api/contractDocumentDesigns.ts`: typed payload + `safeArea`/remove flags, `getAll(params)` | done |
| **F2** `settings.vue`: scope picker (general default / type / customer service), single artwork editor, scope-routed upload / remove / sizing, per-scope save + reset, numeric print-safe-area fields, `VFileInput` block removed | done |
| **F3** `contract_list.vue`: prints the freshly fetched contract (so read-time resolution reaches the print body), stray debug `console.log` removed | done |
| **F4** i18n keys added to `en.json`, `fa-AF.json`, `ps-AF.json` (17 keys each) | done — JSON parses |
| **T1/T2** `tests/Feature/ContractDocumentDesignTest.php` (6 tests: service-scope enforcement, service-precedence snapshot, customer-wide fallback, employment single design, read-time backfill, file cleanup, package issuance) | written — suite executing |
| Verification: `npx eslint` on the three touched TS/Vue files | clean |
| Verification: `npm run typecheck` | no new errors — the 13 remaining `error TS` lines are pre-existing, in files the customer→applicant merge agent owns (`ContractForm.vue`, `applicant-case.vue`, `applicant-stages.vue`, `contract/policies.vue`, `educational-master-data.vue`); none in the files touched here |
| Verification: `vendor/bin/phpunit --filter ContractDocumentDesignTest` | 7/7 green (42 assertions) |
| Verification: neighbours (`CompanySettingsTest`, `EducationalContractIntegrityTest`, `VersionedContractGenerationTest`, `ContractServiceLinesTest`, `UnifiedPackageServiceTest`, `ReportedIssueRegressionTest`) | 28/30 green; 2 failures are **pre-existing on the current working tree** — both come from changes the other agent is making to `ContractTemplateController` (422 "A service is required for a customer template." in tests that create customer templates without `service_id`) and are unrelated to the design/snapshot code touched here |
| Follow-up | harden our own test helper so it always passes a catalog service when creating a customer template (mirrors the enforced rule) — done, re-ran **7/7 green** |
| Follow-up | `saveDocumentDesign()` now refreshes the design catalogue on failure so a stale type list cannot mask a 422; `npx eslint` on `settings.vue` still clean; `vue-tsc` re-run shows **no errors in the touched files** (17 errors remain, all in files the merge agent owns — `ContractForm`, `applicant-*`, `contract/policies`, `educational-master-data`, `expenses`, `recurring-events`) |

