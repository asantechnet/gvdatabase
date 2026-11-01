# ISSUE-17 — Primary and extension contracts look identical; future-dated extensions count as active

- **Severity:** P2 (commercial clarity)
- **Category:** Logic sanity / UI
- **Pages:** Contracts list, Applicant pipeline, Dashboard
- **Persona:** Super administrator
- **Confidence:** High

## Expectation

A contract register distinguishes a primary contract from an extension/renewal (role, parent, effective dates), and a future-dated extension is not presented as an active, currently collectible sale.

## Observed

- Contracts list shows CTR-26-001 and CTR-26-002 for Ahmad Wali with the same service, version, total **50000.00 AFN**, both status **Active**, differing only in contract number and start date.
- Only the detail drawer reveals **Contract role: Extension** and parent context; the list shows neither.
- DB: CTR-26-002/004/006/008 are `contract_role = extension`, `start_date = 2026-11-28` (future), status active.
- Pipeline shows both lines as separate cards (CTR-26-001 and CTR-26-002) under New.
- Dashboard includes **165,000 AFN** of these future extension fees in "Still to collect".
- A list-level "Print contract" button is offered with no row selection, so its target is unclear (not executed; printing was out of scope).

## Impact

Staff may treat an extension as a duplicate, deliver the same service twice, or chase money that is not yet due. Financial totals are inflated for the current period in a way the UI does not explain.

## Evidence

- `review/evidence/contracts.png`
- `review/evidence/contract-detail.png`
- `review/evidence/pipeline.png`
- `review/evidence/dashboard-full.png`
- Query: contracts table (`contract_role`, `parent_contract_id`, `start_date`) in §7 of the report.

## Standard comparison

Contract registers show role/type, parent contract, effective and expiry dates, billing schedule and payment status, with renewal lines excluded from current receivables until effective.
