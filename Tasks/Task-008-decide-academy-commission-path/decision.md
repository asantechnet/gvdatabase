# Academy Counselor Commission Decision

- **Status:** Approved
- **Decision ID:** `LOGIC-20261110-004`
- **Approver:** Product owner (in-session approval, 2026-11-10)
- **Source evidence:** `commission-issues/LOGIC-20261110-004/issue.md`

## Decision

Retire Academy **counselor** commission.

The only Academy commission is the **teacher class commission** already produced by `CommissionService::syncForAcademyClass()` when an Academy class is completed. An employee must hold a teacher position (`positions.system_key = teacher`) and an active Academy service rate to earn it.

## Money and ownership rules

- Direct Academy enrollment does **not** create a contract or service line and does **not** create a counselor commission.
- Academy contract lines do **not** create seller, responsible-employee, or counselor commission.
- The teacher class commission base is the class fee, expressed in the base currency, using the teacher's configured Academy percentage.
- The class teacher is the only Academy commission owner.
- Teacher commission remains keyed to the completed class and employee, so repeated completion/reconciliation updates the existing accrual rather than duplicating it.

## Lifecycle and existing records

- Teacher commission is earned when the class is completed; it is independent of enrollment refunds, withdrawals, and contract cancellation.
- Existing direct enrollments with null contract/line ids are left unchanged and receive no retroactive counselor commission.
- Existing Academy contract lines are not backfilled.
- Unsettled counselor accruals from the retired path are excluded from future rebuilds and become void when the relevant commission sync runs. Settled or paid accruals are not clawed back or deleted.
- No migration, backfill, or destructive cleanup is required.
- Teacher class commission remains separate and uses its existing lifecycle.

## UI and scope

- No Academy counselor commission field, balance, or action is added.
- The staff list UI is explicitly not changed; the product owner does not want additional commission presentation there.
- Commission rates, settlements, and payouts are not changed.
- This decision does not alter contract creation, enrollment fees, or the direct Academy enrollment workflow.

## Consequences for Task-009

Task-009 must gate the Academy contract-line counselor path off, preserve the existing teacher class accrual, keep direct enrollment unchanged, and add regression coverage proving that no counselor accrual is created from Academy contract lines or direct enrollment. Re-sync must remain idempotent for teacher class accruals.
