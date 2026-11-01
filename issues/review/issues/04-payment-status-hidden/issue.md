# ISSUE-04 — Payment status exists in the database but is absent from case and contract screens

- **Severity:** P2 (operational data visibility)
- **Category:** Logic sanity / UI
- **Pages:** Applications list, Contracts list, Contract detail
- **Persona:** Super administrator
- **Confidence:** High

## Expectation

Where money is owed, the list and detail views show paid / partially paid / unpaid and the outstanding amount, so staff can decide whether to deliver service or chase collection.

## Observed

- `applicant_contract` stores `payment_status` (paid / partially_paid / unpaid) and `amount_paid`.
- Applications list has a **Payment status** column, but all five rows show **"—"**.
- Contracts list shows only **Active**; there is no paid/unpaid/outstanding column or badge.
- Contract drawer shows status Active but no payment state.

Actual database values include:
- CTR-26-001: paid 50,000 / fee 50,000
- CTR-26-003: partially paid 30,000 / fee 80,000
- CTR-26-005, CTR-26-002/004/006/008: unpaid

## Impact

Staff cannot see collection status from the screens they use to progress work; the dashboard total is the only payment signal. Risk of delivering service to unpaid clients or misreading "Active" as financially settled.

## Evidence

- `review/evidence/applications.png` (all Payment status cells "—")
- `review/evidence/contracts.png`
- Query:
```
SELECT ac.id, ac.service, ac.fee, ac.amount_paid, ac.payment_status
FROM applicant_contract ac;
```

## Standard comparison

Contract and case registers in standard ERP/CRM show a payment/awarded/outstanding badge column, with currency and amount due, and link to the receipt ledger.
