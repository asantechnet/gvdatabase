# Task Task-015 — Protect issued receipts and acknowledge income writes

## Metadata
- Task ID: `Task-015`
- Title: `Protect issued receipts and acknowledge income writes`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Full-stack Laravel/Vue implementer`
- Estimated size: `M`
- Source plan/version: `fin-issues/README.md`; `fin-issues/issues/FIN-002-receipt-silent-revaluation.md`; `fin-issues/issues/FIN-003-income-mutations-no-toast.md`; `fin-issues/issues/FIN-006-unlabeled-icon-actions.md` income slice; `fin-issues/issues/FIN-009-receipt-detail-raw-ids.md`; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `FIN-002`, `FIN-003`, `FIN-006-INCOME`, `FIN-009`

## Objective
Changing the value of an issued receipt can no longer happen silently: the user sees the old and new total, must give a reason, the change is audited, and every income write is acknowledged with a specific toast.

## Context and problem
`IncomeController::update()` accepts `amount` and `quantity` without a reason or confirmation, and `resources/ts/pages/income.vue` saves them with no warning and no success toast. The demo database still contains the reproduced `RCPT-2026-000001` at 6000 × 3 after the review, which is the silent revaluation the report describes. Create and delete are equally silent.

The receipt detail uses `FullDetailsButton`; `IncomeResource` exposes `base_currency_id` and `applicant_contract_id`, and `FullDetailsButton` does not hide the `base_currency` stem or the `applicantcontract` stem. The detail panel therefore shows internal ids and an "18 useful details" tally instead of finance-readable fields. Income row actions for print, edit and delete have no `aria-label`, while the attachment and view-details controls do.

## Scope
### Included
- Treat an issued receipt's value as protected: when amount, quantity, currency or rate changes, require an explicit confirmation that shows old total → new total and a mandatory reason.
- Add a nullable `revaluation_reason` to incomes, return it in the resource, and include it in the audit record of the change.
- Reject a server-side value change that has no reason, so a direct API request cannot bypass the UI.
- Add success toasts for create, edit and delete that include the receipt number and, for value changes, the new total; add an error toast for failures.
- Harden `FullDetailsButton` for receipt display: hide foreign keys and empty relations, show currency code instead of id, and replace the "{count} useful details" eyebrow with a neutral record-kind label.
- Give the print, edit and delete income row actions accessible names and tooltips.
- Keep receipt numbering, line-payment limits, currency snapshots and permission rules unchanged.

### Excluded
- Reversal entries, credit notes or a full append-only ledger (record the chosen approach as a follow-up if required).
- Changing income receipt printing or the A5 receipt template.
- Shared API error-toast ownership (Task-018; this task depends on it and must not add a second toast).
- Expense ledger changes.

## Preconditions and assumptions
- `incomes` is audited through the existing audit pipeline; the reason must appear in the audit payload or an adjacent audit entry.
- Assumption to validate: an issued receipt is any income row with a `receipt_number`; there is no separate draft state. If a draft state exists, scope the confirmation to issued rows only.
- `full details` is a shared component used by contracts, visitors, assets and leaves; a neutral eyebrow is acceptable for all of them, but do not remove fields those pages depend on.
- The demo row `RCPT-2026-000001` is evidence, not a fixture; tests create their own receipts.

## Dependencies
- `Task-012` — money display uses the shared formatter.
- `Task-013` — accessible icon-action primitives.
- `Task-018` — one owner for API error toasts; this task adds success toasts and specific inline errors without duplicating the global failure toast.

## Contracts and boundaries
- Input/output: `PUT /incomes/{income}` accepts `revaluation_reason` when value fields change; `GET /incomes` and the detail return it. Existing clients that do not change value fields keep working.
- Compatibility guarantee: receipt numbers, line payment calculations and currency snapshots are unchanged; no historical amount is rewritten by the migration.
- Error behavior: a value change without a reason returns HTTP 422 with `errors.revaluation_reason`; an over-limit payment still returns the existing business error.
- Permission/data scope: existing income create/edit/delete permissions and applicant scoping are unchanged.
- Integration seam: the old→new total is computed from the same fields the server validates; the audit entry uses the stored reason, not client-only text.
- Files this task must not modify: `app/Http/Controllers/PendingTransactionController.php`, `resources/ts/api/axios.ts`, `resources/ts/utils/apiErrors.ts`, `resources/ts/pages/asset.vue`.

## Exclusive file ownership
- `resources/ts/pages/income.vue` — revaluation confirmation, toasts and action labels.
- `resources/ts/api/incomes.ts` — revaluation reason and typed mutation responses.
- `app/Http/Controllers/IncomeController.php` — reason requirement and audit payload.
- `resources/ts/components/FullDetailsButton.vue` — foreign-key hiding and neutral eyebrow.
- `database/migrations/2026_11_01_000004_add_revaluation_reason_to_incomes.php` — new nullable reason column.

## Acceptance criteria
- [ ] Given an issued receipt, when the user changes its amount or quantity, then a confirmation shows the old total and the new total and requires a non-empty reason before saving.
- [ ] Given the same value change submitted directly to the API without a reason, when it is validated, then it is rejected with a field-level `revaluation_reason` error and the stored receipt is unchanged.
- [ ] Given a value change with a reason, when it is saved, then the new total is persisted, the reason is returned by the API, and the audit entry shows the old and new values plus the reason.
- [ ] Given income create, edit or delete, when it succeeds, then a success toast names the receipt number and, for an edit, the new total; a failure shows one specific error message.
- [ ] Given a receipt detail panel, when it opens, then it shows no internal foreign-key ids, no empty relationship rows and no "{count} useful details" tally, and currency appears as a code or symbol.
- [ ] Given the income row actions, when inspected by a screen reader, then print receipt, edit receipt and delete receipt each have an accessible name and a visible tooltip.
- [ ] Given an income edit that only changes the description or date, when it is saved, then no revaluation confirmation or reason is required and the receipt number is unchanged.

## Verification plan
- Automated: feature tests for missing reason, successful revaluation, audit payload, unchanged description-only edit and receipt detail serialization; run the focused income tests and `npm run typecheck`.
- Manual: create a receipt, edit amount and quantity, cancel the confirmation and verify no change; repeat with a reason and verify the toast and audit row; delete and verify the toast; inspect the detail dialog and action names.
- Fixtures: a base currency, one standalone receipt and one contracted-service receipt with remaining balance. Non-production only.
- Regression: verify receipt printing, line balance limits, currency snapshot and permissions still work; verify the shared detail button still renders contracts, visitors, assets and leave requests.
- Evidence: before/after receipt values, audit row, confirmation screenshot, toast text, accessibility-tree action names.

## Implementation notes
Compute the old total from the persisted row and the new total from the same normalized amount/quantity fields the controller validates; do not trust a client-supplied difference. Require the reason only when a value-bearing field changes, and store it beside the audit entry so the reason is not lost when the audit payload is summarized. Keep the receipt number and currency snapshot untouched. In `FullDetailsButton`, hide keys ending in `_id` and stems for empty relationships, and replace the count eyebrow with a neutral label rather than removing the component's shared sections.

## Risks and rollback
- Risk: requiring a reason breaks automation that edits incomes. Mitigation: only value fields trigger the requirement; description/date edits remain free.
- Risk: hiding foreign keys in the shared detail button removes useful admin fields. Mitigation: keep them available only when a related object is loaded and is not a raw id.
- Data migration: one nullable column; no backfill. Rollback drops the column; existing amounts remain.
- Stop condition: if the business wants append-only receipts, stop this task and plan reversal/credit-note work instead of layering confirmation on an editable ledger.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
