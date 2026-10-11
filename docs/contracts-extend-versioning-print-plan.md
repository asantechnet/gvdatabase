# Contracts — Extend, Versioning, Employment & Print Fixes (Round 2)

**Created:** 2026-10-11
**Owner:** Engineering
**Status:** Plan complete — implementation not started
**Source request:** [`docs/Educational New Comments++.md`](./Educational%20New%20Comments%2B%2B.md)
**Progress:** see [§7 Progress tracker](#7-progress-tracker) and [§8 Progress log](#8-progress-log). Update both after **every** sub-task.

---

## 0. Scope and confirmed decisions

Five issues were reported. Decisions below were confirmed with the requester before planning.

| # | Issue | Confirmed decision |
|---|-------|--------------------|
| 1 | Test Offer / Scholarship / Private contracts must not show an **Extend** action | **UI only** — hide the button; leave the API unchanged. Academy keeps Extend. |
| 2 | Academy **Extend** must autofill the form | Prefill **everything** (applicant identity, academy programme details, fees, currency + rate, language, discount, dates, notes) and keep every prefilled field **editable**; edits must persist on save. |
| 3 | Employment templates must not version per country/language | Versions are **name only**: no country, no language, no test dimension. One current version, auto-selected. |
| 4 | HR → Employment Contracts: version selection impossible; position must not be selectable | Position is **inferred from the selected employment template** (read-only); the employee list is limited to employees holding that position; no position selector. |
| 5 | Contract printing broken (one blank page with a blue band, no cover/content) | Reproduces on **both** screens (Contracts list and Service contracts). Root cause identified below. |

### Glossary of the code in play

- Service contract pages: `resources/ts/pages/service-contracts.vue` (private / scholarship / test offer / academy)
- Generic contract pages: `resources/ts/pages/contract_list.vue`, `employee_contracts.vue`, `other_contracts.vue`, `cancelled_contracts.vue`
- Contract form: `resources/ts/components/ContractForm.vue`; service form field group: `resources/ts/components/ApplicantServiceFields.vue`
- Template admin: `resources/ts/pages/contract/policies.vue` (`contractDocuments` → `?tab=templates`)
- Renderers: `resources/ts/pages/templates/contract_cover_template.vue`, `contract_page_template.vue`; print host: `resources/ts/components/contract/ContractDocumentPrinter.vue`
- Print engine: `resources/ts/utils/printDocument.ts`; global print CSS: `resources/styles/styles.scss`
- Contract backend: `app/Http/Controllers/ContractController.php`, `app/Services/Contracts/ContractGenerationService.php`, `app/Http/Requests/StoreServiceContractRequest.php`
- Version backend: `app/Http/Controllers/ContractTemplateVersionController.php`, `app/Models/ContractTemplateVersion.php`
- Regression harnesses: `scripts/contract-print-regression.mjs`, `scripts/agency-document-regression.mjs`, `scripts/service-contract-regression.mjs`

---

## 1. Workstream A — Remove Extend everywhere except Academy

### A1. Restrict the Extend action to Academy only

**Change.** In `resources/ts/pages/service-contracts.vue`, the row action is currently:

```vue
<VBtn v-if="ability.can('read', 'create_contracts') && item.contract_role === 'primary' && !item.is_cancelled && ['active', 'completed'].includes(item.status)" …>
```

Add the service guard so it renders **only** for academy:

```vue
v-if="service === 'academy' && ability.can('read', 'create_contracts') && item.contract_role === 'primary' && !item.is_cancelled && ['active', 'completed'].includes(item.status)"
```

**Do not** change the API (`StoreServiceContractRequest` / `ContractGenerationService`) — UI only, per the confirmed decision.

**Acceptance criteria.**
- Test Offer, Scholarship and Private lists show no Extend button on any row.
- Academy still shows Extend on eligible primary rows.
- No backend behaviour change.

---

## 2. Workstream B — Academy Extend autofills the form (editable, persisted)

### B1. Prefill the extend form from the parent contract

`service-contracts.vue::create(extension)` currently resets the form to empty and hides all identity/service fields when `parent` is set. For academy extension, prefill and keep editable:

1. **Identity + academy detail** — prefill `form` from `extension.applicant` (`name`, `father_name`, `father_phone`, `backup_phone`, `contact`, `email`, `address`, `notes`) and from the applicant's academy detail (`program_id`, `time_id`, `duration_id`, `class_id`, `teacher_ids`). Reuse the existing `applicantServicePayload`/`visitorApplicantFields` shape if convenient.
2. **Money / terms** — prefill from `extension.services[0]`: `service_fee`, `fee`, `discount`, `currency_id`, `currency_rate`; and `languageId = extension.contract_language_id`, `startDate` = parent start date (or today). Set `rate` from the parent line rather than the currency default.
3. **Make the identity/service field group visible for academy extensions** — the current `v-if="!parent"` wrappers must become "not extending **or** academy":

   ```vue
   <template v-if="!parent || service === 'academy'">
   ```

   Keep the visa/service-only blocks (template + version summary) hidden for academy as today.

4. `save()` must send the identity/academy fields **and** `parent_contract_id` for academy extensions (today it sends only `parent_contract_id`). Example:

   ```ts
   ...(parent.value
     ? { parent_contract_id: parent.value.id, ...(service.value === 'academy' ? serviceContractApplicantFields(service.value, form.value) : {}) }
     : serviceContractApplicantFields(service.value, form.value)),
   ```

### B2. Backend: accept and persist edits on an academy extension

`StoreServiceContractRequest` currently forbids identity fields (`$identity = $extending ? 'prohibited' : 'nullable'`) and all service-detail fields when extending. For academy extensions these must be accepted:

- `$identity = $extending && $service !== BuiltInService::ACADEMY ? 'prohibited' : 'nullable'`.
- In the service-detail loop, allow the academy keys when `$service === ACADEMY` even if extending: `! $extending || $service === BuiltInService::ACADEMY` instead of `! $extending`.
- `admission_employee_id` / `responsible_employee_id`: allow on academy extensions too (mirror the identity rule).

`ContractGenerationService::create()` currently, when extending, loads the parent's applicant and ignores submitted identity. For academy extensions, apply the submitted identity and academy detail to that applicant before creating the extension line:

- Update `Applicant` identity fields from `Arr::only($data, [...identity...])`.
- Call `$this->details->sync($applicant, [...$data, 'service' => $service])` (idempotent; it already handles the academy detail relation).
- Keep current behaviour (copy, ignore payload) for non-academy extensions.

> Rationale: an academy extension is issued to the **same** applicant; changing the programme/time/duration/class or fees should update that applicant record, not silently ignore the form.

**Acceptance criteria.**
- Extending an academy contract opens the form prefilled with the parent's identity, academy details, fees, currency, rate, language, discount, dates and notes.
- Every prefilled field is editable; changing a value and saving persists it (verifiable on the new contract and on the applicant record).
- Non-academy extension flows are unchanged (identity still copied server-side).
- Backend tests: extend the service-contract suite to assert the academy-extension payload is accepted and applied, and that non-academy extensions still reject identity fields.

---

## 3. Workstream C — Employment template versions are name-only

### C1. Schema and model

Employment templates have `service === null` and `contract_type.slug === 'employment'`; their versions must not carry a country/language/test scope.

1. **Migration** `xxxx_make_contract_template_version_language_nullable.php`:
   - `$table->foreignId('language_id')->nullable()->change();` (currently `NOT NULL`).
   - Keep `country_id` / `test_id` nullable as-is.
   - Note: MySQL treats `NULL` as distinct in unique indexes, so the existing composite unique cannot enforce uniqueness for a fully-null scope. Uniqueness for employment is enforced by `current_scope_key` (one current version per template) plus transactional `version_number` allocation. Document this in the migration comment.
2. `app/Models/ContractTemplateVersion.php` — `scopeKey()` already joins `template_id:country_id:test_id:language_id`; for employment this becomes `"{id}:::"`, which is unique per template, so a second current version is rejected by the `current_scope_key` unique index. No model change strictly required; verify the empty segments do not collide across templates.

### C2. Backend CRUD

`app/Http/Controllers/ContractTemplateVersionController.php`:

- `store()`: make `language_id` **nullable** (`['nullable', 'exists:languages,id']`).
- `normalizeScope()`: add an employment branch first —

  ```php
  if ($template->contractType?->slug === 'employment') {
      $data['country_id'] = null;
      $data['test_id'] = null;
      $data['language_id'] = null;
      return $data;
  }
  ```

  and abort if a non-employment template is missing both its required scope values (existing behaviour).
- `activate()` / `archive()` / `update()`: no scope assumptions — they already key off the version row.

### C3. Template admin UI (`policies.vue`)

- Compute `isEmploymentTemplate = selectedType?.slug === 'employment'` (already have `isEmployment`).
- **Version dialog** (`openVersionDialog` / `createVersion`): when employment, hide the country/test selector **and** the language selector; require only `name`. Send `country_id: null, test_id: null, language_id: null`.
- **Version list**: hide the Country/Test and Language columns for employment; show `name · v{n}`, status and actions.
- **Version filters**: hide the country/test + language filters for employment.
- **New-template initial version block**: for employment require only `initialVersion.name` (no country/language); adjust `initialScopeMissing` and the validation messages.
- `canCreateVersions` etc. are permission-driven and unchanged.

### C4. Generic contract rendering (`ContractController`)

`documentAttributes()` requires a language whenever a template has versions:

```php
abort_if($template->versions()->exists() && (! $languageId || ! $version), 422, …);
abort_if($version && (… || (int) $version->language_id !== (int) $languageId || …), 422, …);
```

Make this employment-aware:

- Resolve the version by id when supplied; for employment fall back to the template's `is_current && status === 'active'` version (no country/language matching).
- Only require `$languageId` and compare `country_id`/`language_id` for **non-employment** templates.
- Persist `contract_country_id` / `contract_language_id` as `null` for employment.

**Acceptance criteria.**
- Creating/activating a version for an employment template needs only a name and content.
- The version list and dialog show no country/language for employment templates.
- Scholarship / private (country) and test-offer (test) versioning are unchanged.
- Backend tests: `ContractTemplateVersionTest` + `VersionedContractGenerationTest` extended with an employment name-only version case.

---

## 4. Workstream D — HR → Employment Contracts: version auto-select + inferred position

### D1. Version selection must work (auto-select the current version)

`resources/ts/components/ContractForm.vue`:

- Add `isEmploymentTemplate = computed(() => activeSlug.value === 'employment')`.
- `requiresVersionSelection` → `activeTemplateVersions.length > 0 && !isEmploymentTemplate.value`.
- For employment, `selectedTemplateVersion` = the single active/current version (`activeTemplateVersions[0]` or the one matching `form.contract_template_version_id`), so the form never blocks on a country/language choice.
- Hide the Country and Language `ErpField`s when `isEmploymentTemplate` (they are the "version per country/language" UI that no longer applies).
- Keep the existing success alert showing the selected version name.
- The preview watcher and `submit()` already send `contract_template_version_id: selectedTemplateVersion?.id ?? null`; for employment send `country_id: null, language_id: null`.

**Result:** the "versioning selection is not possible" dead-end disappears — there is exactly one current version and it is selected automatically.

### D2. Position is inferred from the template

- Remove the `form.position_id` `ErpField` (currently required) from the `context === 'employee'` branch.
- Derive `templatePosition = selectedTemplate?.position_id` and render it as a **read-only** display (e.g. a disabled `ErpField` labelled *Position*, or a `VChip`), populated from `positions` (`PositionApi` is already loaded).
- `employeeOptions` filter by `templatePosition` instead of `form.position_id`; show a helpful empty/again-alert when the template has no position.
- Keep the existing "clear the employee when the position no longer matches" safety: if a selected employee's `position_id !== templatePosition`, clear it.
- `submit()` continues to send `employee_id`; `position_id` is no longer sent (the backend derives the counterparty position from the employee).

### D3. Coherent template/position naming

Employment templates are already created as `Employment Agreement — {position}` (`EmploymentContractSeeder`, and `policies.vue` when launched with `?positionId=`). Optionally surface the template's position as a subtitle/chip in the template selector so the user sees which position a template belongs to (`item-title` can be extended with the position title).

**Acceptance criteria.**
- HR → Employment Contracts → add contract: no position selector; position shows the selected template's position.
- The employee dropdown lists only employees holding that position.
- The version is selected automatically; saving succeeds without any country/language choice.
- A contract generated this way renders the correct employee/position placeholders.

---

## 5. Workstream E — Contract printing: blank page with a blue band

### E1. Root cause (verified)

Both print paths open a real Vuetify `VDialog` before printing (`contract_list.vue` print-options dialog, `ContractDocumentPrinter.vue`). While any overlay/dialog is open, Vuetify's default `block` scroll strategy (`node_modules/vuetify/lib/components/VOverlay/scrollStrategies.js`) adds `v-overlay-scroll-blocked` to the scroll parents, including `<html>` and `<body>`, and Vuetify's own CSS applies:

```css
html.v-overlay-scroll-blocked { position: fixed; top: …; left: …; width: 100%; height: 100%; }
.v-overlay-scroll-blocked:not(html) { overflow-y: hidden !important; }
.v-overlay-scroll-blocked { padding-inline-end: var(--v-scrollbar-offset); }
```

`printDocument` clones the document into `#app-print-host` and hides the app, but it never neutralises this `<html>`/`<body>` lock. The print engine therefore paginates a **single, viewport-height page** — showing only the very top of the clone (the cover's blue `#26308e` top-section) with everything below clipped. That is exactly the reported "one blank page with a blue ribbon on top, no cover page, no content".

Why the existing harnesses missed it: `scripts/contract-print-regression.mjs` stubs `VDialog` (slot stubs), so the real Vuetify overlay never runs; `scripts/agency-document-regression.mjs` renders static HTML without a live overlay. The isolated printers are correct — the regression is the overlay scroll-lock.

### E2. Fix

`resources/ts/utils/printDocument.ts` — inside the temporary `pageStyleElement` (injected last, removed on cleanup), add print-media overrides for the scroll-lock signature:

```css
@media print {
  html.v-overlay-scroll-blocked,
  .v-overlay-scroll-blocked {
    position: static !important;
    inset: auto !important;
    width: auto !important;
    height: auto !important;
    overflow: visible !important;
    padding-inline-end: 0 !important;
  }
  body.printing-isolated-document {
    height: auto !important;
    overflow: visible !important;
  }
  html:has(body.printing-isolated-document) { overflow: visible !important; }
}
```

Because the sheet is injected after every component/route stylesheet and is removed after the job, it wins deterministically and leaves no residue. Guard the `:has()` rule as progressive enhancement; the `.v-overlay-scroll-blocked` overrides carry the fix in engines without `:has()`.

Also reset the overlay offset variables used for the scrollbar (`--v-scrollbar-offset`, `--v-body-scroll-x/y`) for the print host so content is not shifted horizontally (RTL-sensitive).

> Alternative/complementary hardening: snapshot and remove the `v-overlay-scroll-blocked` classes from `html`/`body` for the duration of `printDocument`, restoring them in `cleanup()`. Use only if the CSS override proves insufficient in a real browser.

### E3. Regression coverage for the real failure mode

Extend `scripts/contract-print-regression.mjs`:

1. Before `window.print()`, add `v-overlay-scroll-blocked` to `document.documentElement` and `document.body` and set `--v-scrollbar-offset` — reproducing the app state.
2. Assert under emulated print media that `getComputedStyle(document.documentElement).position === 'static'` and `height !== '100%'`.
3. Use the existing CDP session to call `Page.printToPDF` and assert the produced PDF has **≥ 2 pages** (cover + content) and contains the cover and body text (parse the PDF, or count `/Type /Page`). This is the assertion that would have caught the bug.

Also extend `scripts/service-contract-regression.mjs` to print a real `ContractDocumentPrinter` contract and assert cover + content pages.

**Acceptance criteria.**
- Printing a contract from the Contracts list (All / HR / Other) and from Service contracts produces a full cover + all content pages, no clipping, in both LTR and RTL.
- `contract-print-regression.mjs` fails on the pre-fix code and passes after.
- No leftover `@page`, body class, host node, or overlay class after printing.

---

## 6. Cross-cutting

- **i18n:** any new labels (e.g. read-only *Position*, academy extend hints, version-name-only help text) go in all three locales `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json`. Run `npm run i18n:check` and `npm run i18n:test`.
- **Icons:** no new class-based icons expected; if any are added, run `npm run build:icons` and treat warnings as bugs.
- **Preserve** issued contract snapshots and receipt numbers. No invoice/company/commission/outsourcing surfaces are touched.
- **Keep** `printDocument`'s existing `@page`-last injection and feature-specific body classes.

---

## 7. Validation strategy

Run after each workstream and at the end:

1. `npm run typecheck` (never bare `vue-tsc`/`tsc`).
2. `npm run i18n:check` + `npm run i18n:test`.
3. `npm run build:icons` (no "used but unavailable" warnings).
4. `npm run build`.
5. Backend: targeted PHPUnit — `ContractTemplateVersionTest`, `VersionedContractGenerationTest`, `ContractTemplateUpdateTest`, `ServiceContractNavigationTest`, plus the service-contract suite for the academy extension; `vendor/bin/pint` on changed PHP.
6. Print verification in a real browser: run the extended `contract-print-regression.mjs` (CDP `Page.printToPDF`) and confirm page count/content; verify from both screens in the running app.
7. Manual matrix per UI change: light + dark, LTR + RTL, mobile/tablet/desktop.

---

## 8. Progress tracker

Legend: `[ ]` not started · `[~]` in progress · `[x]` done · `[!]` blocked

| ID | Sub-task | Status | Notes / commit |
|----|----------|--------|----------------|
| A1 | Restrict Extend action to Academy only (`service-contracts.vue`) | [x] | Extend button now renders only when `service === 'academy'`; API untouched |
| B1 | Academy extend prefill (identity + academy details + fees) in `service-contracts.vue` | [x] | `parentApplicantPayload()` fills identity + academy detail (program/time/duration/class/teachers); money/terms/currency rate/language/dates come from the parent line; identity + `ApplicantServiceFields` are shown and editable for academy extensions |
| B2 | Backend accepts/persists academy-extension identity + detail edits (`StoreServiceContractRequest`, `ContractGenerationService`) | [x] | Identity + service fields (and `name`) allowed for academy extensions; the service updates the parent applicant + detail row inside the transaction. Non-academy extensions still prohibit identity |
| C1 | Migration: `contract_template_versions.language_id` nullable; document uniqueness | [x] | `2026_10_11_000001_make_contract_template_version_language_nullable`; uniqueness documented as `current_scope_key` + transactional numbering (MySQL NULLs are distinct in composite unique indexes) |
| C2 | `ContractTemplateVersionController`: employment name-only scope | [x] | `normalizeScope()` employment branch nulls country/test/language; `language_id` validation relaxed but still required for every non-employment template |
| C3 | `policies.vue`: hide country/language/test for employment versions | [x] | Version dialog, list columns, filters, new-template initial version and preview subtitle all drop the scope UI for employment; employment-specific titles/hints |
| C4 | `ContractController::documentAttributes`: employment version without language | [x] | Employment resolves the single current active version and forces `contract_country_id`/`contract_language_id` to null; non-employment keeps strict country+language matching |
| D1 | `ContractForm.vue`: auto-select employment version, hide country/language | [x] | `requiresVersionSelection` disabled for employment; `selectedTemplateVersion` auto-selects the current version; country/language fields hidden |
| D2 | `ContractForm.vue`: infer position from template, filter employees, read-only display | [x] | Position selector removed; read-only field shows the template's position; employee options filtered by it; mismatched employees cleared |
| D3 | Surface the template's position in the template selector (optional polish) | [x] | Covered by the read-only position field plus the existing `Employment Agreement — {position}` template naming from the seeder/admin |
| E1 | Root-cause confirmed: Vuetify overlay scroll-lock on `<html>`/`<body>` clips print to one page | [x] | Verified in `VOverlay/scrollStrategies.js` + `VOverlay.css`; harnesses stub `VDialog`, so they missed it |
| E2 | `printDocument.ts`: neutralise the overlay scroll-lock in print | [x] | Print-media overrides for `html.v-overlay-scroll-blocked` / `.v-overlay-scroll-blocked` added to the temporary sheet (injected last, removed on cleanup); reset `--v-scrollbar-offset` on the print host and `height/overflow` on the isolation body |
| E3 | Regression: reproduce the scroll-lock + assert PDF page count (CDP `Page.printToPDF`) | [x] | `contract-print-regression.mjs` now adds the scroll-lock classes, asserts `html` is `static` / not viewport-height, and counts PDF pages via CDP `Page.printToPDF` (asserts ≥ 2) |
| V | Validation pass (typecheck, i18n, icons, build, PHPUnit, browser print) | [~] | Done: `npm run typecheck`, `npm run i18n:check`, `npm run i18n:test`, `npm run build:icons` (no unavailable warnings, generated CSS unchanged), `php artisan test --filter=Contract` (85 passed, 199+ assertions incl. new cases), `vendor/bin/pint`, `php -l`, and the `contract-print` / `service-contract-regression` / `agency-document-regression` scripts. Still outstanding: `npm run build` (blocked by the missing Linux rollup binary in this environment) and the manual light/dark + LTR/RTL browser matrix |

---

## 9. Progress log

- **2026-10-11** — Round-2 review complete. Read the five comments, mapped each to the owning code, and confirmed the decisions above with the requester (UI-only Extend removal; academy "prefill everything, editable"; employment versions name-only; position inferred from template; print broken on both screens). Root-caused the print bug: Vuetify's `block` scroll strategy pins `<html>` (`position: fixed; width/height: 100%`) and adds `overflow-y: hidden !important` to `<body>` while the print dialog is open, so the print engine renders a single viewport-height page showing only the cover's blue top-section. Existing print harnesses stub `VDialog`, which is why they stayed green.
- **2026-10-11** — A1/B1/B2 done. Extend is now academy-only in `service-contracts.vue`. Academy extension prefills identity + academy detail + money/terms from the parent contract and keeps every field editable; `name`, identity and service detail fields are accepted for academy extensions in `StoreServiceContractRequest` and applied to the parent applicant (with its detail row) in `ContractGenerationService`. Non-academy extensions still copy the applicant and reject identity edits.
- **2026-10-11** — C1–C4 done. `contract_template_versions.language_id` is nullable; `ContractTemplateVersionController::normalizeScope()` treats employment templates as name-only (country/test/language null) while still requiring country + language for every other template; `ContractController::documentAttributes()` resolves the single current employment version without a language and stores null scope columns. `policies.vue` drops the country/language/test version UI for employment (dialog, list, filters, initial version, preview) and adds employment-specific copy. `EmploymentContractSeeder` now seeds one name-only version per position. New tests: `ContractTemplateVersionTest` employment name-only case + a 422 for a non-employment version with no scope.
- **2026-10-11** — D1–D3 done. `ContractForm.vue` auto-selects the current employment version (no country/language pickers) and derives the position from the selected template: the position selector is replaced by a read-only field, the employee list is limited to that position, and a mismatched employee is cleared. An employment template whose versions exist but none is active now shows an explicit warning instead of silently failing. Typecheck + i18n checks pass.
- **2026-10-11** — Validation (`V`) partial. `npm run typecheck`, `npm run i18n:check`, `npm run i18n:test`, `npm run build:icons` (generated CSS unchanged, no warnings), `php artisan test --filter=Contract` (**85 passed, 746 assertions**) including the two new cases, plus `vendor/bin/pint`, `php -l` and the three regression scripts. Two pre-existing tests that assumed academy extensions reject identity were updated to the new (confirmed) behaviour. `npm run build` remains blocked by the missing Linux `@rollup/rollup-linux-x64-gnu` binary in this environment; the browser/PDF manual matrix is still to be walked.
- **2026-10-11** — E2/E3 done. `printDocument.ts` now releases the overlay scroll-lock for the print job: print-media overrides for `html.v-overlay-scroll-blocked` (un-pin `position`/`inset`/size), `.v-overlay-scroll-blocked:not(html)` (`overflow-y: visible`), a zero scrollbar offset, and `html:has(body.printing-isolated-document)`. `scripts/contract-print-regression.mjs` now reproduces the app state (adds the scroll-lock classes and `--v-scrollbar-offset` before printing), asserts `<html>` is `static` and not pinned to the viewport height, and renders through Chrome's real pipeline with CDP `Page.printToPDF`, asserting ≥ 2 pages. Harness passes for all three artwork states (`pdfPages: 2`), and `npm run typecheck` is green. Remaining: A1, B1–B2, C1–C4, D1–D3, plus the validation pass (`npm run build`, backend PHPUnit, manual light/dark + LTR/RTL checks).
