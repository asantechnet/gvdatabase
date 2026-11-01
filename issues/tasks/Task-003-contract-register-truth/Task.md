# Task Task-003 — Make the contract register show role, expiry and payment truth

## Metadata
- Task ID: `Task-003`
- Title: `Expose contract role, expiry and payment state in the register`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P2`
- Owner role: `Full-stack Laravel/Vue implementer`
- Estimated size: `M`
- Source plan/version: `issues/review/issues/04-payment-status-hidden/issue.md` contract slice; `issues/review/issues/06-contract-attention/issue.md`; `issues/review/issues/17-contract-identity/issue.md`; `issues/review/REPORT.md` §4 P2-2, P2-4; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-04-CONTRACT`, `ISSUE-06`, `ISSUE-17`

## Objective
A contract register row tells the truth about what the contract is (primary or extension), when it expires, and whether it is paid — so a dashboard attention alert can be understood and acted on from the same page.

## Context and problem
`contract_list.vue` shows contract number, contract name, version, counterparty, start date, status and actions. It never shows `contract_role`, `parent_contract_id`, `end_date` or payment state. The dashboard counts active contracts with `end_date <= now()+30 days`; the demo database has CTR-26-015 ending 2026-11-18, but the register shows it as Active with no end date. Extension rows such as CTR-26-002 have a future `start_date` of 2026-11-28 yet render identically to the primary contract and are labelled Active. Applicant service lines already store `payment_status` and `amount_paid`; the contract register does not surface them.

## Scope
### Included
- Add a role indicator for primary vs extension, including parent contract number where applicable.
- Add an end-date column, days-remaining or expiry badge, and a row highlight for active contracts expiring within 30 days or already expired.
- Add an `expiring` register filter (query parameter supported by the page) that the dashboard attention tile can deep-link to; show a visible active-filter chip and a clear-filter action.
- Distinguish a future-dated extension as scheduled/not yet effective instead of a plain Active label.
- For applicant service contracts, show payment status and outstanding amount from the applicant contract line, with currency.
- Show a clear empty/not-applicable state for employee and other contracts where service-line payment does not apply.

### Excluded
- Recomputing payment status or changing the payment service (Task-003 is presentation and query only).
- Dashboard salary and receivable totals (Task-004).
- The dashboard attention tile itself (Task-004 owns `educational-dashboard.vue`; this task exposes the filter it links to).
- Contract creation validation (Task-002).

## Preconditions and assumptions
- `contracts.end_date` is nullable and already cast to date; `ContractResource` already returns `end_date` and `contract_role`.
- Applicant service lines are already exposed through the contract resource or can be loaded without changing the payment calculation.
- Assumption to validate: the dashboard attention query and this register's expiring filter use the same definition — active, non-cancelled, `end_date` within 30 days including today. Record the exact definition in `Progress.md`.
- The register is permission-scoped by the existing contract permissions; no new permission is required.

## Dependencies
- `Task-012` — money and date display must use the shared formatter.
- `Task-013` — sticky status/action columns and accessible row actions must exist before the 1280 px acceptance is tested.

## Contracts and boundaries
- Input/output: `GET /contracts` gains an optional `expiring` boolean/filter; existing filters and pagination remain compatible.
- Compatibility guarantee: `contract_role`, `parent_contract_id`, `end_date` and payment fields are additive in the resource; no existing key is renamed.
- Error behavior: an unknown `expiring` value is ignored or rejected consistently with the other boolean filters; the page shows an inline error rather than an empty table.
- Permission/data scope: `view_contracts` remains the gate; payment fields must not leak to a viewer who lacks the relevant financial permission. If such a permission exists, hide the payment column for that viewer.
- Integration seam: the dashboard deep link is `/employee-contracts?expiring=1`; this task must keep that URL stable.
- Files this task must not modify: `resources/ts/pages/educational-dashboard.vue`, `resources/ts/utils/localization.ts`, `resources/ts/components/responsive-data-table/**`.

## Exclusive file ownership
- `resources/ts/pages/contract_list.vue` — columns, expiry badges, role labels and filter UI.
- `resources/ts/composables/useContracts.ts` — list parameters and pagination state.
- `resources/ts/api/contracts.ts` — `expiring` parameter, role/end/payment types.
- `app/Http/Controllers/ContractController.php` — expiring filter and relation loading.
- `app/Http/Resources/ContractResource.php` — additive role/expiry/payment fields.

## Acceptance criteria
- [ ] Given an active employee contract ending within 30 days, when the register opens, then the row shows its end date, days remaining and an expiring badge, and the row is visually distinguishable from a non-expiring active contract.
- [ ] Given the register URL contains the expiring filter, when the page loads, then only active contracts with an end date inside the 30-day window are listed, and an active-filter chip with a clear action is visible.
- [ ] Given an extension contract with a future start date, when it appears in the list, then it shows its extension role and parent contract number and is labelled scheduled or not yet effective rather than simply Active.
- [ ] Given an applicant service contract with a partially paid line, when the register renders for a viewer allowed to see financials, then the payment column shows partially paid and the outstanding amount with its currency.
- [ ] Given an employee or other contract with no service line, when the register renders, then the payment column shows a defined not-applicable state rather than a misleading dash or zero.
- [ ] Given a 1280 px viewport, when the register first renders, then status and row actions are reachable without horizontal hunting, using the shared table affordances from Task-013.
- [ ] Given an expiring contract row, when the user opens its actions, then a renewal action is available or explicitly explained as unavailable, and it preserves the primary/extension relationship.

## Verification plan
- Automated: feature tests for the expiring filter, future extension labelling data and payment fields; run `php artisan test --filter=Contract` plus the new focused test. Run `npm run typecheck`.
- Manual: open `/employee-contracts`, confirm CTR-26-015 shows 2026-11-18 and its days remaining; follow the dashboard attention link once Task-004 lands; open an extension row and confirm role/parent/effective date.
- Fixtures: create one primary contract ending in 20 days, one extension starting in 30 days, and one partially paid applicant line. Use a non-production database; delete after verification.
- Regression: confirm existing contract list pagination, search, print report and contract detail drawer still work; confirm a user without financial permission sees no payment data.
- Evidence: screenshots at 390 and 1280 px, test output, sample filter response.

## Implementation notes
Prefer computing days remaining and expiry state in the resource so the Vue page does not re-implement date arithmetic. Keep the future-extension rule explicit: `contract_role === extension && start_date > today`. Do not relabel the database status; add a derived presentation state. For payment, read the existing `applicant_contract` values and do not call the payment service.

## Risks and rollback
- Risk: loading applicant contracts on every list row creates N+1 queries. Mitigation: eager-load the relation in the controller and assert query count in a test.
- Risk: a new filter changes dashboard counts if it shares the query. Mitigation: keep the dashboard query untouched in this task and verify both counts after Task-004.
- Data migration: none. Rollback is code-only; the additive fields can remain unread.
- Stop condition: if payment status is not exposed to the register's permission scope, stop and record the permission decision rather than leaking it.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
