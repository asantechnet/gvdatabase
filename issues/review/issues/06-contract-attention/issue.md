# ISSUE-06 — "Contract attention" cannot be diagnosed from the contract register

- **Severity:** P2 (missed renewal)
- **Category:** Logic sanity / UI
- **Pages:** Agency overview, Employee contracts
- **Persona:** Super administrator
- **Confidence:** High

## Expectation

A dashboard item that says a contract needs attention should deep-link to a register that shows why: end date, days remaining, status (expiring/expired), and a renewal action.

## Observed

- Dashboard: **Contract attention 1 — CTR-26-015**, linking to `/employee-contracts`.
- The register lists CTR-26-015 as **Active** with columns Contract Number, Contract, Counterparty, Start Date, Status, Actions. There is **no end date**, no "expiring" badge and no highlighted row.
- Database: `contracts.end_date = 2026-11-18` (20 days after the review date).
- The table header says "Start Date" only; the rest of the 7 employee contracts are active with missing or 2027 end dates.

## Impact

The user knows something needs attention but cannot tell what. Renewal can be missed while the UI says Active.

## Evidence

- `review/evidence/dashboard-full.png`
- `review/evidence/employee-contracts.png`
- Query: `SELECT contract_number,start_date,end_date,status FROM contracts WHERE applicant_id IS NULL;`

## Standard comparison

Standard HR/contract registers show commencement, expiry, notice period, status and a renewal/termination action; dashboard alerts link to the filtered expiring set.
