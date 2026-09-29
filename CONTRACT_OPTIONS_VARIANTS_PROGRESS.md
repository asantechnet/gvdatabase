# Contract options and service variants

## Plan

1. Move the Templates & Policies workspace into Options → Contracts while retaining old links.
2. Keep the contract vocabulary fixed to Customer, Employment, and Other; enforce the same vocabulary for reusable clauses.
3. Make customer templates support multiple service scopes and optional service-variant scopes; retain the old `service_id` association for existing records.
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

## Verification performed

- PHP syntax checks passed for the changed models, resources, controllers, and audit registry.
- Locale JSON parses successfully for English, Dari, and Pashto.
- `git diff --check` passed.
- The localization audit recognizes the newly added variant keys, but still fails on the repository's pre-existing missing-key backlog (129 English/Dari and 551 Pashto keys).
- Focused Laravel feature tests remain blocked by the unavailable local MySQL test database.
