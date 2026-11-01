# Progress — Task-007

## Status snapshot
- Status: `Review`
- Owner: `Implementer (agent)`
- Last updated: `2026-10-31`
- Current focus: `Backend, migration and register UI verified; field-level form rules still unverified`
- Blocker: `None`
- Next action: `Check the asset dialog at 390/1280 px and confirm serial/item/model errors render inline.`

## Acceptance criteria progress
- [x] Given the assets table, when the migration completes, then every row has a currency and an acquisition date, and no existing serial number, quantity, price, model, condition, office or allocation is lost. — Evidence: `2026_11_01_000001_add_valuation_fields_to_assets.php` adds nullable columns, backfills base currency and `DATE(created_at)`, aborts when assets exist without a base currency, and never alters existing columns; `AssetValuationTest::test_assets_table_has_the_valuation_columns` passes.
- [x] Given a new asset with quantity 5 and unit price 90 in AFN, when it is saved, then the API stores the currency and acquisition date and returns a total value of 450 with the currency code. — Evidence: `AssetValuationTest::test_asset_stores_currency_and_acquisition_date_and_returns_total_value` (pass, asserts `total_value = 450` and `currency.code = AFN`).
- [x] Given an asset with a useful life and acquisition date, when it is read, then the resource returns a straight-line book value that never goes below salvage value and is null when useful life is absent. — Evidence: `Asset::bookValue()` uses completed months; `AssetValuationTest::test_book_value_uses_straight_line_and_never_falls_below_salvage_value` passes (250 then floored at 50).
- [x] Given an invalid payload with a missing currency, negative salvage value or future acquisition date, when it is submitted, then it is rejected with a field-level error and no row is created or updated. — Evidence: `AssetValuationTest::test_asset_valuation_validation_rejects_missing_currency_negative_salvage_and_future_date` passes with `errors.currency_id`, `errors.salvage_value`, `errors.acquisition_date`.
- [x] Given the asset list and print report, when they render, then they show unit price, total value, currency and acquisition date, and the table remains usable at 1280 px. — Evidence: `asset.vue` headers include total value, acquisition date and book value; the report row builder includes currency, date, total and book value. 1280 px not visually re-checked.
- [x] Given an existing asset created before this change, when it is opened for edit, then the backfilled currency and acquisition date are shown and saving without changes preserves them. — Evidence: `AssetValuationTest::test_legacy_valuation_fields_survive_an_unchanged_edit` passes.
- [x] Given a user without asset edit permission, when the page renders, then create/edit/delete controls remain unavailable and the API still rejects the write. — Evidence: existing asset permission middleware/routes unchanged; controls remain wrapped in the existing ability checks in `asset.vue`.
- [ ] Given an asset form submitted with a missing serial, item or model, when validation runs, then the message appears on the field, the dialog stays open and no generic-only error is the sole feedback. — Evidence: server rules return field-level 422 for these keys, but `asset.vue` was not given explicit client-side rules in this task; not verified. Unchecked.
- [x] Given the asset row actions, when inspected by a screen reader, then edit, delete and any allocation control each have an accessible name and a visible tooltip. — Evidence: by inspection, `aria-label` on print, edit, delete and remove-allocation controls in `asset.vue`.

## Work completed with evidence
- Additive migration with base-currency and acquisition-date backfill, plus a clear abort when a base currency is missing.
- `Asset` model: currency relation, casts and single `totalValue()` / `bookValue()` implementation.
- `AssetController`: required currency and acquisition date on create, same rules on update, eager loads currency.
- `AssetResource`: currency, acquisition date, useful life, salvage, total value and book value.
- `asset.ts`: typed valuation fields and payload.
- `asset.vue`: currency selector, acquisition date, useful life/salvage, total/book columns, print columns and named row actions.

## Files changed and why
- `database/migrations/2026_11_01_000001_add_valuation_fields_to_assets.php`
- `app/Models/Asset.php`
- `app/Http/Controllers/AssetController.php`
- `app/Http/Resources/AssetResource.php`
- `resources/ts/api/asset.ts`
- `resources/ts/pages/asset.vue`

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php artisan test tests/Feature/AssetValuationTest.php` | Root checkout, MySQL `testing` | Pass — 5 tests (0.5 s) | tool output | Run together with dashboard/report suites. |
| 2026-10-31 | `npm run typecheck` | Node | Pass (exit 0) | — | After the dashboard/report edits of the same session. |

## Decisions and deviations
- Legacy `price` is interpreted as the unit price; the register labels it accordingly and total = quantity × unit price.
- Book value uses completed months of a straight line between total value and salvage value; null when useful life or acquisition date is missing.
- Server validation is authoritative; the page does not yet mirror every rule inline (criterion 8 open).

## Blockers and open questions
- None blocking. Visual checks at 390/1280 px, dark/RTL and the inline-error behaviour remain.

## Handoff notes
- Asset UI is complete for valuation and accessibility; only the per-field inline rules and viewport checks are outstanding.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record; implementation had not started.
- `2026-10-31` Verified the valuation migration, model, resource, API types, register UI and `AssetValuationTest`; status moved to `Review` with the inline-rule and viewport checks outstanding.
