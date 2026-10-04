# Contract options and service variants

## Plan

1. Move the Templates & Policies workspace into Options → Contracts while retaining old links.
2. Keep the contract vocabulary fixed to Customer, Employment, and Other; enforce the same vocabulary for reusable clauses.
3. ~~Make customer templates support multiple service scopes and optional service-variant scopes; retain the old `service_id` association for existing records.~~ **Superseded 2026-09-29:** a customer template now binds to exactly **one** subject — a single service (one optional variant) *or* one package — see “one catalogue subject per customer template” below. Legacy multi-scope rows still load; saving narrows them to the single selection.
4. Add service codes and optional flat service variants. A selected variant fee must take precedence over the service fee in the issued contract snapshot.
5. Ensure the editor, contract-creation form, printing payload, permissions, auditing, validation, and translations remain coherent.

## Progress

- [x] Moved the canonical document-workspace URL to `/options/contracts/documents`; `/contract-documents` redirects to it.
- [x] Added service code, nested variants, template-service scopes, and contract-line variant storage through a migration.
- [x] Added audited service-variant API endpoints, protected by the existing `manage_services` permission.
- [x] Added service code and flat variant management UI with optional fee overrides.
- [x] Added customer-template multi-service selection and optional per-service variant selection.
- [x] Added contract-form variant selection for template-bound customer contracts and persisted selected variants in service lines.
- [x] Expanded Other template/clause placeholders to all contract-relevant party, employee, service, and company fields while excluding system metadata.
- [x] Added safe frontend fallback to the server-provided placeholder label, preventing untranslated/missing placeholder keys from appearing in the editor.
- [x] Added English, Dari, and Pashto labels for the new service-variant controls.
- [x] Enforced template service/variant scope again when a contract is issued, not only in the editor.
- [x] Flattened the catalogue by removing variant-of-variant support and safely promoting existing child variants to direct service variants.
- [x] Preserved legacy single-service templates/contracts and their existing associations.

## Verification still required

- Run migrations in a disposable database, then run the focused feature suite and frontend type check.
- Exercise template and printing flows with service variants in the browser after migrations are applied.

## 2026-09-29 — one catalogue subject per customer template (service XOR package)

Supersedes plan item 3 (multi-service scopes): the template editor no longer multi-selects
services, and a previous attempt that represented packages by grouping services was removed.

- A customer template binds to **exactly one** catalogue subject: a single service
  (with an optional variant) **or** one package from *Educational data → packages*
  (`GET /packages`). Picking one disables and clears the other; both selectors appear as
  soon as the customer type is selected.
- Backend: `contract_templates.package_id` is validated (`exists:packages,id`, active) and
  the binding is exclusive — a request carrying both is rejected with `service_id`
  *A customer template can be for a service or a package, not both*, and a customer
  template with neither is rejected with *Choose a service or a package…*. Switching
  subjects clears the other side (`service_id` + the `contract_template_services` pivot
  are emptied for a package template; `package_id` is nulled for a service template).
- `ContractTemplateResource` returns `package_id` / `package`; the template table shows the
  bound subject in a **Scope** column.
- The manual contract form (`ContractForm.vue`) hides package-bound templates: packages are
  sold as a bundle through the package flow, and a customer contract always needs a service.
- Fixed stale `package_types` table references left by the packages rename
  (`PackageController`, `EducationalMasterDataController`, `EducationalReportController`,
  `EducationalServiceController` validation rules) which made `POST /api/packages` return 500.

### Verification performed

- `vendor/bin/phpunit --filter 'ContractTemplateUpdateTest'` → 3/3 green (25 assertions),
  including the new `test_customer_template_accepts_a_package_instead_of_a_service`.
- `--filter 'EducationalMasterDataTest|EducationalServiceAttributionTest|ContractTemplateVersionTest'`
  → 7/7 green after the stale-table fix (the `package_types` assertions in
  `EducationalMasterDataTest` now read `packages`).
- `--filter 'ContractServiceLinesTest|ContractDocumentDesignTest|ContractTemplateVersionTest|ContractTemplateUpdateTest|UnifiedPackageServiceTest|ReportedIssueRegressionTest'`
  → 25/29 green; the 4 failures are pre-existing and unrelated (multi-service contract rule,
  template auto-bound service backfill, missing applicant documents on package issue).
- `npx vue-tsc --noEmit` → no errors in the touched files; the 3 remaining `contract/policies.vue`
  errors (version create/activate typing) exist verbatim in `HEAD`.
- `npx eslint` on `policies.vue`, `contractTemplate.ts`, `packages.ts`, `ContractForm.vue` → clean.

## Verification performed

- PHP syntax checks passed for the changed models, resources, controllers, and audit registry.
- Locale JSON parses successfully for English, Dari, and Pashto.
- `git diff --check` passed.
- The localization audit recognizes the newly added variant keys, but still fails on the repository's pre-existing missing-key backlog (129 English/Dari and 551 Pashto keys).
- Focused Laravel feature tests remain blocked by the unavailable local MySQL test database.
