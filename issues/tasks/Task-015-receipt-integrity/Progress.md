# Progress — Task-015

## Status snapshot
- Status: `Review`
- Owner: `Implementer (agent)`
- Last updated: `2026-10-31`
- Current focus: `Backend protection, UI confirmation and feedback implemented; manual browser pass outstanding`
- Blocker: `None`
- Next action: `Click through create → revalue (cancel and confirm) → delete in the browser and inspect the audit row.`

## Acceptance criteria progress
- [x] Given an issued receipt, when the user changes its amount or quantity, then a confirmation shows the old total and the new total and requires a non-empty reason before saving. — Evidence: `updateIncomeRecord` detects value-field changes and opens a confirmation dialog showing `old total → new total`; `confirmRevaluation` refuses an empty reason with `erp.required`. Not clicked through in a browser.
- [x] Given the same value change submitted directly to the API without a reason, when it is validated, then it is rejected with a field-level `revaluation_reason` error and the stored receipt is unchanged. — Evidence: `IncomeRevaluationTest::test_a_value_change_without_a_reason_is_rejected_and_the_receipt_is_unchanged` (pass) covers amount, quantity and rate.
- [x] Given a value change with a reason, when it is saved, then the new total is persisted, the reason is returned by the API, and the audit entry shows the old and new values plus the reason. — Evidence: `IncomeRevaluationTest::test_a_reasoned_value_change_is_persisted_and_audited_with_old_and_new_values` (pass): `revaluation_reason` in `changed_columns`, audit old/new amounts 6000 → 5000, receipt number unchanged.
- [x] Given income create, edit or delete, when it succeeds, then a success toast names the receipt number and, for an edit, the new total; a failure shows one specific error message. — Evidence: create toasts the new receipt number, edit toasts `receipt · new total` with the currency, delete toasts the receipt number; failures set the specific inline message from `apiErrorMessage`/`messageFromError` without adding a second global toast.
- [x] Given a receipt detail panel, when it opens, then it shows no internal foreign-key ids, no empty relationship rows and no "{count} useful details" tally, and currency appears as a code or symbol. — Evidence: `FullDetailsButton` now hides every `*_id` key (including `base_currency_id` and `applicant_contract_id`), filters empty relation objects, and the eyebrow renders only the record kind.
- [x] Given the income row actions, when inspected by a screen reader, then print receipt, edit receipt and delete receipt each have an accessible name and a visible tooltip. — Evidence: all three buttons now carry `aria-label` in addition to their existing tooltips.
- [x] Given an income edit that only changes the description or date, when it is saved, then no revaluation confirmation or reason is required and the receipt number is unchanged. — Evidence: `IncomeRevaluationTest::test_a_description_or_date_edit_needs_no_reason_and_keeps_the_receipt_number` (pass); `ApplicantContractPaymentTest`'s null-rate snapshot edit also still passes.

## Work completed with evidence
- Nullable `revaluation_reason` migration (no backfill) plus model fillable and resource field.
- `IncomeController::update` requires a non-empty reason exactly when amount, quantity, currency or rate changes; a null rate is ignored and description/type/date edits stay free. Direct API calls cannot bypass it.
- Reason is stored on the row and therefore appears in the audit `new_values`/`changed_columns`; old and new amounts remain in the audit payload.
- Income UI: old → new confirmation dialog with mandatory reason, create/edit/delete success toasts, named row actions.
- Shared detail viewer no longer exposes internal ids or the details tally.

## Files changed and why
- `database/migrations/2026_11_01_000004_add_revaluation_reason_to_incomes.php`
- `app/Models/Income.php`, `app/Http/Resources/IncomeResource.php`
- `app/Http/Controllers/IncomeController.php`
- `lang/{en,fa,ps}/api.php` — `business.revaluation_reason_required`
- `resources/ts/api/incomes.ts`
- `resources/ts/pages/income.vue`
- `resources/ts/components/FullDetailsButton.vue`
- `tests/Feature/IncomeRevaluationTest.php` (new); fixtures updated in `ApplicantContractPaymentTest`, `BusinessLogicConsistencyTest`, `IncomeReceiptTest` to supply reasons for intended value changes.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php artisan test tests/Feature/IncomeRevaluationTest.php tests/Feature/ApplicantContractPaymentTest.php` | Root checkout, MySQL `testing` | 21 passed / 1 failed (audit amount format assertion) | tool output | Assertion compared decimal strings; changed to numeric comparison. |
| 2026-10-31 | `php artisan test tests/Feature/IncomeRevaluationTest.php` (re-run) | MySQL `testing` | Pass — 3 tests / 28 assertions | tool output | After correcting the audit amount assertion to a numeric comparison. |
| 2026-10-31 | `npm run typecheck` | Node | Pass (exit 0) | — | Two toast/nullable-currency type errors found and fixed. |
| 2026-10-31 | `php -l` on controller, migration, test | PHP 8.2 | Pass | — | |

## Decisions and deviations
- Receipts remain editable with a reason rather than append-only; reversal/credit-note work is a follow-up.
- The reason lives on the row (audited) instead of only in the request, so it survives audit summarisation.
- A present-but-null `rate` is ignored (matching the existing snapshot rule) and does not demand a reason.

## Blockers and open questions
- None blocking. Browser interaction, the receipt detail panel and the A5 print were not visually re-tested.

## Handoff notes
- Backend protection is tested; the confirmation dialog and toasts are wired but only inspected in source.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated finance issues 002, 003, 006 and 009; implementation had not started.
- `2026-10-31` Implemented the reason-gated receipt revaluation, audit trail, toasts, action names and detail-viewer hardening; focused tests and lint pass. Status moved to `Review`.
