# Packages as first-class catalogue products

## The request

1. **Packages need a fee.** A package is *a group of services sold to an applicant for one
   (different) fee*, so the packing list has to carry both the package price and an optional
   per-service share of that price.
2. **Other than that, packages must act exactly like services**: they get their own contract
   template and they are assigned to applicant cases.
3. **Move the packages workspace** out of *Options → Contract → Educational data* and into
   *Options → Services*, separated with tabs exactly like the educational-data page does.
4. **"Package type" is deprecated.** The entity is a package: table `packages`, API `/packages`,
   vocabulary "package" everywhere in the UI and translations.

## Vocabulary and the deprecation

| Before | After | Note |
| --- | --- | --- |
| `package_types` table | `packages` table | the table always held packages, not types |
| `PackageType` model | `Package` model | `PackageType` kept as a deprecated alias class |
| `package-types` master-data catalog | removed | replaced by the Packages tab on Options → Services |
| `scholarships.package_type_id`, `test_offers.package_type_id` | `package_id` | deprecated `package_type_id` still accepted on input |
| `*_package_type` permissions | `view_packages` / `manage_packages` / `issue_packages` | legacy permission rows stay seeded so existing role grants keep working, but nothing reads them any more |
| `options.package_types` (API payload) | `options.packages` | |

## What a package is (model)

* Catalogue identity: `name`, `code`, `description`, `is_active`, `currency_id`.
* **Price**: `fee` = the price of the whole package. It is *not* the sum of the member fees;
  the difference is the package discount (`sum(member fees) - package fee`).
* **Packing list**: `package_service` pivot rows `(service_id, allocated_fee, sort_order)`.
  `allocated_fee` is the share of the package price that belongs to that service. When a sale
  overrides the price, the shares are scaled proportionally and the rounding remainder lands on
  the last service (existing behaviour, kept).
* **Contract template**: `contract_templates.package_id` — one template per package, exactly like
  `contract_templates.service_id` for services.
* **Sales**: `customer_packages` (one row per applicant + package, guarded by `issuance_key`)
  plus one `contract_service` line per member service, each carrying `customer_package_id`.

## Where the functionality lives

```
Options -> Services
|- Services  (unchanged service catalogue + variants + contract template)
`- Packages  (NEW: fee, currency, code, packing list with allocated fees, template, active)
```

* Definition: `/api/packages` (create/update/delete) + `POST /api/packages/{package}/contract-template`.
* Assignment: `POST /api/packages/{package}/issue` (records the purchase and issues the member
  services for the negotiated fee) — surfaced on the applicant case page as **Sell package**.
* Contract generation: `ServiceContractGenerator::createForPackage()` renders **one** agreement
  from the package's bound template with one line per member service. A package without a bound
  template keeps the historical behaviour (one contract per member service) so existing sales and
  data continue to behave identically.


## Plan

1. Migration: rename `package_types` -> `packages`, `*.package_type_id` -> `package_id`, add
   `packages.code`, add `contract_templates.package_id`.
2. Models: `Package` (+ code, packing list, template relations), `PackageType` deprecated alias,
   `Scholarship`/`TestOffer` package relations, `ContractTemplate::package()`.
3. API: package validation (code, fee <-> currency, packing list with allocated fees, active),
   `PackageResource` (client shape incl. `contract_template` and `total_service_fee`), package
   contract-template binding endpoint, package-aware contract generation, package options rename,
   master-data `package-types` catalog removal.
4. UI: Options -> Services tabs, package table + editor (fee, currency, code, packing list with
   allocated fees and the derived discount, template link, active), package column on the
   scholarship/test-offer workspace, **Sell package** dialog on the applicant case, educational
   data page cleaned of the packages tab.
5. Permissions/audit: keep `view_packages` / `manage_packages` / `issue_packages`, let the route
   accept either catalogue permission, keep the `packages` audit key.
6. Translations: English, Dari, Pashto for every new control; drop the package-type labels that
   are no longer reachable.
7. Tests + verification: update the affected feature tests, add a focused package test (fee,
   allocated fees, template binding, single-agreement issuance, case assignment), run the suite
   against a disposable MySQL instance, plus `vue-tsc`, ESLint and the i18n audit.

## Progress

- [x] Recon complete: packages, services, contract templates, master data, routes, i18n, tests.
- [x] Plan documented.
- [ ] S1 Migration + models.
- [ ] S2 API (package controller/resource/routes, contract generation, issuance service).
- [ ] S3 Educational master data cleanup + educational-service options/presentation.
- [ ] S4 UI (Options -> Services tabs, package editor, case assignment, educational data cleanup).
- [ ] S5 Translations (en / fa-AF / ps-AF).
- [ ] S6 Tests updated + new package test.
- [ ] S7 Verification (migration up/down, feature tests, type check, lint, i18n audit).

## Verification performed

- _(filled in as each step lands)_

## Deliberate decisions / follow-ups

* A package is **not** a service row: it stays its own table so a case, a contract line and a
  report can always tell "the bundle I sold" from "one deliverable inside it".
* Single-agreement mode only kicks in when the package has an **active bound template**; that
  keeps the historical per-service contracts for packages that were never given a template.
* Deferred: per-member-service commission attribution from `allocated_fee` (the bonus ledger still
  attributes the sale to the sold-by employee).
