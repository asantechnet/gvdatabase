# Task Task-007 — Add currency and valuation data to the asset register

## Metadata
- Task ID: `Task-007`
- Title: `Add currency and valuation data to the asset register`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P2`
- Owner role: `Full-stack Laravel/Vue implementer`
- Estimated size: `M`
- Source plan/version: `issues/review/issues/14-asset-currency/issue.md`; `issues/review/REPORT.md` §4 P2-9; `fin-issues/issues/FIN-006-unlabeled-icon-actions.md` asset slice; `fin-issues/issues/FIN-008-asset-bare-required-errors.md`; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-14`, `FIN-006-ASSET`, `FIN-008`

## Objective
An asset row states its acquisition cost in a currency, its total value, its acquisition date and its current book value, so the register can support procurement and write-off decisions.

## Context and problem
`SHOW COLUMNS FROM assets` returns only id, serial_number, item, quantity, price, model, condition, office_id, timestamps and deleted_at. Prices render as bare values (550, 400, 90, 250, 1200) that cannot be reconciled to the ledger. `AssetController` validates price and quantity but has no currency, acquisition date or valuation concept. Quantity 5 times price 90 is never shown as 450. The asset form defines no field-level rules, so a missing serial, item or model submits and returns a generic server validation message instead of pointing at the field; the exact literal "Required." from the report is not reproducible. Asset row edit/delete controls also lack accessible names.

## Scope
### Included
- Add `currency_id` (FK, restrict on delete), `acquisition_date` (date, nullable for legacy), `useful_life_years` (nullable integer) and `salvage_value` (decimal, default 0) to assets.
- Backfill existing rows with the company base currency and `acquisition_date = DATE(created_at)` so no row is left without a currency.
- Define `price` explicitly as the unit price; expose `total_value = quantity * price`.
- Compute a straight-line book value from acquisition date, useful life and salvage value when useful life is set; otherwise expose total value with a null book value.
- Require currency and acquisition date on create; allow them on update with the same validation.
- Show currency, total value, acquisition date and book value on the asset list, create/edit dialogs and print report.
- Add explicit field-level validation for serial number, item, model, quantity, price and condition, with inline messages and a neutral allocation helper; keep the server rules authoritative.
- Give every asset row icon action an accessible name and visible tooltip.
- Keep the existing condition, office and allocation rules unchanged.

### Excluded
- Depreciation journal entries, disposal workflows, revaluation and asset transfers.
- Changing office, allocation or condition semantics.
- Shared money formatting implementation (Task-012) and table primitives (Task-013).

## Preconditions and assumptions
- A base currency exists in `currencies` with `is_base = true`; the migration must fail clearly if none exists rather than silently using null.
- Assumption to validate: the business wants straight-line depreciation. If a different method is required, stop and record the decision; the schema fields chosen here are method-neutral enough to keep.
- Legacy prices are interpreted as unit prices because the UI labels price next to quantity and allocations; record this interpretation in the migration and UI copy.
- Asset permissions and allocations remain unchanged.

## Dependencies
- `Task-012` — money and date display use the shared formatter.
- `Task-013` — responsive table and accessible row actions are needed for the list acceptance.

## Contracts and boundaries
- Input/output: `POST/PUT /assets` accept the new fields; `GET /assets` and the resource return them; existing fields keep their names and meanings.
- Compatibility guarantee: `price` remains unit price; new `total_value` and `book_value` are additive. No existing consumer breaks.
- Error behavior: missing currency, non-positive useful life, negative salvage value or acquisition date in the future return field-level 422 errors.
- Permission/data scope: existing asset permissions unchanged; currency options come from the existing currency endpoint.
- Integration seam: the depreciation calculation lives in one model method used by both resource and print, not duplicated in Vue.
- Files this task must not modify: `resources/ts/utils/localization.ts`, `resources/ts/components/responsive-data-table/**`, `app/Http/Controllers/ContractController.php`.

## Exclusive file ownership
- `database/migrations/2026_11_01_000001_add_valuation_fields_to_assets.php` — new columns and base-currency backfill.
- `app/Models/Asset.php` — casts, relations and book-value calculation.
- `app/Http/Controllers/AssetController.php` — validation, eager loading and filters.
- `app/Http/Resources/AssetResource.php` — create or extend the asset resource with currency and valuation fields.
- `resources/ts/pages/asset.vue` — form fields, columns and print rows.
- `resources/ts/api/asset.ts` — typed currency and valuation fields.

## Acceptance criteria
- [ ] Given the assets table, when the migration completes, then every row has a currency and an acquisition date, and no existing serial number, quantity, price, model, condition, office or allocation is lost.
- [ ] Given a new asset with quantity 5 and unit price 90 in AFN, when it is saved, then the API stores the currency and acquisition date and returns a total value of 450 with the currency code.
- [ ] Given an asset with a useful life and acquisition date, when it is read, then the resource returns a straight-line book value that never goes below salvage value and is null when useful life is absent.
- [ ] Given an invalid payload with a missing currency, negative salvage value or future acquisition date, when it is submitted, then it is rejected with a field-level error and no row is created or updated.
- [ ] Given the asset list and print report, when they render, then they show unit price, total value, currency and acquisition date, and the table remains usable at 1280 px.
- [ ] Given an existing asset created before this change, when it is opened for edit, then the backfilled currency and acquisition date are shown and saving without changes preserves them.
- [ ] Given a user without asset edit permission, when the page renders, then create/edit/delete controls remain unavailable and the API still rejects the write.
- [ ] Given an asset form submitted with a missing serial, item or model, when validation runs, then the message appears on the field, the dialog stays open and no generic-only error is the sole feedback.
- [ ] Given the asset row actions, when inspected by a screen reader, then edit, delete and any allocation control each have an accessible name and a visible tooltip.

## Verification plan
- Automated: feature tests for create/update validation, backfill and book-value calculation; run `php artisan test --filter=Asset` and `npm run typecheck`.
- Manual: create an asset with quantity 5 and price 90; confirm 450 total and the currency; set a 4-year useful life and confirm book value declines; open a legacy row and save it unchanged.
- Fixtures: one base currency, one non-base currency, one legacy asset and one new asset; non-production only.
- Regression: confirm allocation totals, condition filter, office filter, asset print and soft delete still work.
- Evidence: migration output, SQL before/after columns, resource JSON, list and dialog screenshots.

## Implementation notes
Use `decimal` for money and a date cast for acquisition date. Compute total value from quantity times unit price in the model/resource, not only in the UI. For book value, use completed months or days consistently and document the choice. The migration should be reversible; dropping the columns is acceptable only after a backup of the derived values is not required.

## Risks and rollback
- Risk: interpreting legacy price as unit price inflates totals if it was actually a total. Mitigation: confirm with the business and label the column "Unit price" explicitly; document the assumption.
- Risk: a missing base currency blocks the migration. Mitigation: abort with a clear message and require the currency seed before running.
- Data migration: additive columns plus a base-currency/date backfill. Rollback drops the new columns; no existing column is modified.
- Stop condition: if depreciation method is undecided, ship the currency/acquisition/total fields and leave book value null rather than inventing a method.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
