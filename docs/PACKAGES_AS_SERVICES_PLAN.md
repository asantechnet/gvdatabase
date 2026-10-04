# Packages as services — full plan and progress

## Request (user, 2026-09-29)

1. **Educational packages need a fee.** A package is *a group of services sold to an
   applicant for one (different) fee* — not the sum of member fees.
2. **Other than that, packages act exactly like services**: contract templates can be
   created for them, and they can be assigned to applicants.
3. **Move the packages page** from the educational-data page to **Options → Services**,
   separated by tabs just like the educational-data page does.
4. **"Package type" is deprecated**: the entity is a *package* (`packages` table,
   `/packages` API, "package" vocabulary in UI + translations).

## Recon (actual state found)

- Migration `2026_09_28_000003_promote_packages_to_first_class_catalogue` already did:
  `package_types` → `packages`, `*.package_type_id` → `package_id`,
  `packages.code`, `contract_templates.package_id`. `Package` model + deprecated
  `PackageType extends Package` alias exist. Contract-template create/update already
  enforces the exclusive **service XOR package** binding (`ContractTemplateController`).
  `PackageIssuanceService::issue()` exists but still mints **one contract per member
  service** (no single-agreement mode yet). `PackageController` has
  index/show/store/update/destroy + `POST /packages/{package}/issue` but: no `code`
  validation, no `allocated_fee` packing-list input (pivot supports it, controller
  ignores it), no template-binding endpoint, index/show return raw models instead of
  `PackageResource`.
- UI: packages still live on `educational-master-data.vue` under the `package-types`
  tab (name + included-services only — no fee/currency/code/template). `options/services.vue`
  is services-only, no tabs. `contract/policies.vue` already has the package picker.
- Naming debt: `options.package_types`, `package_type_id`/`package_type` in
  `EducationalServiceController`, `ApplicantController`, `EducationalReportController`,
  frontend `educationalServices.ts` / `educational-services.vue`, `package-types`
  master-data catalog, `view/create/edit/delete_package_type` permissions, locale keys
  `packageType` / `masterData.packageTypes` / `reportColumns.package_type`.
- Uncommitted work in progress (kept): `GET /packages` also allowed for
  `view_contract_templates` (route + `apiAuthorization.ts` + test), package-bound
  templates hidden from the manual `ContractForm`, `code` on the TS `ServicePackage`
  type, `packages` (not `package_types`) in `EducationalMasterDataTest` assertions.

## Decisions

- A package is **not** a service row: it stays its own `packages` table so a case, a
  contract line and a report can always tell "the bundle sold" from "one deliverable
  inside it". Everything else mirrors services: fee + currency, one contract template,
  assignment to an applicant case.
- **Fee model**: `packages.fee` = price of the whole bundle. `package_service.allocated_fee`
  = each member's share of that price (sums to the package fee; the bundle discount is
  `sum(member service fees) − package fee`). On a negotiated-price sale the shares scale
  proportionally with the rounding remainder on the last line (existing behaviour, kept).
- **Single-agreement mode**: `ServiceContractGenerator::createForPackage()` renders **one**
  agreement from the package's bound template with one `contract_service` line per member
  service. A package **without** a bound template keeps the historical one-contract-per-service
  behaviour so existing sales keep working identically.
- **Deprecation, not a flag day**: backend accepts legacy `package_type_id` /
  `service_ids` inputs and still emits `package_type*` aliases in responses; new code and
  UI use `package_id` / `package` / `services[]`. Legacy `view/create/edit/delete_package_type`
  permission rows stay seeded; nothing new reads them. The `package-types` master-data
  catalog endpoint is removed (packages are managed at `/api/packages` now); frontend
  types drop it.
- Packages tab lives on **Options → Services** with `VTabs` exactly like
  `educational-master-data.vue`. Route stays `/options/services` (permission gate
  `view_services`; package actions additionally check `view/manage/issue_packages` via
  the API layer, same as services do for `manage_services`).

## Implementation steps

### S1 — Backend: package catalogue acts like services
- `PackageController`: validate `code` (nullable, unique, max 80); fee↔currency paired
  both ways (`fee required_with currency`, `currency required_with fee`); accept packing
  list as `services: [{service_id, allocated_fee?}]` (new) **and** legacy `service_ids`;
  persist `allocated_fee` + `sort_order` in pivot; return `PackageResource`; add
  `POST /packages/{package}/contract-template` (`bindContractTemplate`, mirrors
  `ServiceController::bindContractTemplate`, permission `manage_packages`).
- `PackageResource`: add `contract_template {id,name,is_active}`, `total_service_fee`,
  `discount`, pivot `allocated_fee`/`sort_order` on services.
- `Service::contractTemplate` shape already exists — mirror it for packages.
- `routes/api.php`: add the bind route; keep `GET /packages` readable by
  `view_packages,view_contract_templates`; keep `issue` on `issue_packages`.

### S2 — Backend: one agreement per package + assignment
- `ServiceContractGenerator::createForPackage(Package, Applicant, options)`: require
  package's primary (active) template → resolve version (country/language) → render
  placeholders with all member services → create **one** `Contract`
  (`source_service_id = null`, `snapshot_data.entity.package_id` + services list) + one
  `contract_service` line per member (fee split, `customer_package_id` set).
- `PackageIssuanceService::issue()`: if package has an active bound template → single
  agreement path; else legacy per-service path (unchanged). Keep `issuance_key`
  idempotency, fee/currency override, `packageIssued` notification.
- `CustomerPackage` response: include package + services + contract(s).

### S3 — Backend: kill `package-types`, rename to `package`
- `EducationalMasterDataController`: remove `'package-types'` from `MODELS`, validation,
  `restoreOrCreate`, `isInUse`, `loadCatalog`, `authorizeCatalog` singular map.
- `routes/api.php`: remove `package-types` from the master-data `{catalog}` whereIn.
- `EducationalServiceController`: `options.package_types` → `options.packages` (+ legacy
  alias in payload); `present()` emits `package_id`/`package` (+ legacy
  `package_type_id`/`package_type` aliases); validation accepts `package_id` (legacy
  `package_type_id` folded in, already half-done).
- `ApplicantController::presentService()`: same `package_id`/`package` rename + aliases.
- `EducationalReportController`: accept `package_id` (alias `package_type_id`), column
  `package` (alias `package_type`).
- `Scholarship`/`TestOffer`: `package()` is canonical; `packageType()` stays as
  deprecated alias (already the case). No schema change (columns already `package_id`).

### S4 — Frontend: Options → Services tabs + package editor
- `resources/ts/api/packages.ts`: extend `ServicePackage` (`code`, `currency`,
  `contract_template`, `total_service_fee`, `discount`, pivot `allocated_fee`), `PackageInput`
  (`code`, `services: [{service_id, allocated_fee?}]` + legacy `service_ids`),
  `bindContractTemplate()`, `issue()` stays.
- `options/services.vue`: `VTabs` → **Services** (existing table/form as tab 1) |
  **Packages** (new: table name/code/fee/currency/discount/#services/template/active;
  editor dialog with fee+currency, code, active switch, packing-list multi-select with
  per-row allocated_fee + live totals/discount, "manage contract template" deep-link to
  `contract-documents?tab=templates&packageId=`; delete confirm).
- `contract/policies.vue`: support `packageId` query pre-select (deep-link target).
- `educational-master-data.vue`: remove `package-types` tab + `PackagesApi`/`ServiceApi`
  usage + package form fields. `api/educationalMasterData.ts`: drop `'package-types'`
  from `EducationalCatalog`. `utils/apiAuthorization.ts`: drop the `package-types`
  master-data mapping.

### S5 — Frontend: assignment + workspace rename
- `applicant-case.vue`: **Sell package** button + dialog (package select with fee preview,
  sold-by, purchased_at, negotiated fee/currency override, notes) → `PackagesApi.issue()`;
  package-issued lines show the package name (already via `customerPackage.package`).
- `educational-services.vue` + `api/educationalServices.ts`: `package_types` →
  `packages` (tolerate both), `package_type_id`/`package_type` → `package_id`/`package`
  (tolerate both), column header uses `educational.package`.
- `ContractForm.vue`: keep excluding package-bound templates (already in working tree).

### S6 — Translations (en, fa-AF, ps-AF)
- New keys: tabs (`services`/`packages`), package editor (code, fee, currency, packing
  list, allocated fee, discount, total, template link, active), sell-package dialog
  (title, package, fee preview, sold-by, purchased date, negotiated fee, notes, success/
  fail), validation (`packageRequired`, fee/currency pairing).
- Rename: `educational.packageType` → deprecated alias kept only if referenced;
  `masterData.packageTypes` dropped with the tab; `reportColumns.package_type` →
  `package` (+ alias); workspace `educational.package` already exists — reuse.
- Every new en key gets fa-AF + ps-AF equivalents (via existing translation conventions;
  Dari/Pashto from neighbouring keys).

### S7 — Tests + verification
- Update `EducationalMasterDataTest`: `package-types` catalog cases → `/api/packages`
  CRUD (fee/code/packing list/allocated fees/validation/soft-delete restore).
- Extend `UnifiedPackageServiceTest` (or new `PackageAsServiceTest`): package CRUD with
  fee + code + allocated fees; `bindContractTemplate`; issue with bound template → **1
  contract + N lines**; issue without template → legacy N contracts; duplicate issue →
  422; applicant-case assignment visible on case + workspace.
- Update `EducationalServiceAttributionTest` (`options.packages`), report tests
  (`package_id`), `ContractTemplateUpdateTest` already covers package binding.
- Run: `php artisan test` (disposable MySQL), `vue-tsc --noEmit`, ESLint on touched
  files, i18n audit script (no missing keys).

## Progress

- [x] Recon complete (backend, frontend, i18n, tests, working-tree deltas).
- [x] Plan documented (this file).
- [ ] S1 Backend package catalogue (controller/resource/bind route).
- [ ] S2 Single-agreement generation + issuance switch.
- [ ] S3 `package-types` catalog removal + `package` rename with aliases.
- [ ] S4 Options → Services tabs + package editor; educational-data cleanup.
- [ ] S5 Sell-package on applicant case; workspace rename; apiAuthorization.
- [ ] S6 Translations en / fa-AF / ps-AF.
- [ ] S7 Tests updated + new package test.
- [ ] S8 Verification (migration up/down, feature tests, type check, lint, i18n audit).

## Verification performed

- (filled in as each step lands)
