# HR-006 — Commission is earned on case completion, not contract completion

- Severity: High (needs product confirmation)
- Area: Commissions / contract lifecycle
- Status: Logic misalignment between the task's stated rule and the implementation; observed in source, not yet reproduced end to end in the UI.

## Stated requirement

> when a contract reaches complete stage and any employee attached to that contract is a commission-based employee, system needs to get that employee's commission-list and account for that employee's commission for that contract (based on contract type …)

## What the system does

`app/Services/CommissionService.php` is explicit:

- The trigger is the applicant workflow: commission is earned when the **case** reaches the terminal `completed` applicant stage.
- `syncForApplicant()` first checks `$applicant->isCaseCompleted()`; if the case is not completed it **voids** all unsettled accruals, regardless of contract status.
- `syncForContract()` loads the contract's applicant and delegates to `syncForApplicant()`. Therefore completing a contract while its case is not completed does **not** accrue anything.
- `earnsCommission()` requires the contract itself to be `active` or `completed` and not cancelled — so both conditions must hold, but the case is the controlling one.
- Source amounts use `ApplicantContract::payableTotal()` (fee − discount) converted to base currency, and each employee attached through `contracts.employee_id`, `contracts.sold_by_employee_id` or `applicant_contracts.sold_by_employee_id` is eligible only if `is_commission_based` and has a per-service rate.

## Why this matters

- A completed contract on a case that is still in the pipeline earns nothing. Users watching the contract list will see "completed" with no commission, and the commissions page offers no explanation.
- Conversely, completing the case accrues for every non-cancelled contract line, including contracts that are still `active`, not "complete".
- The UI names the ledger action "Sync cases", reinforcing that the unit is the case, but the stated business rule is contract-based.

## Reproduction plan (not completed)

1. Create a commission-based employee with a per-service percentage.
2. Create a service contract with that employee attached as seller/counselor.
3. Set the contract status to `completed` while leaving the applicant case in progress → verify `commission_accruals` stays empty (current expectation from source).
4. Complete the applicant case → verify accruals appear for all service lines.
5. Reverse: complete the case while the contract is still draft/active and observe accrual behavior.

## Suggested direction

Confirm which event is the commercial trigger. If it is contract completion, gate `syncForApplicant` on the relevant contract state and/or call a contract-specific accrual path from the contract status transition. If the case is the intended trigger, rename the requirement/UI so contract completion is not implied.

## Evidence

- `app/Services/CommissionService.php` lines 45–60 (`isCaseCompleted` gate), 99–115 (`syncForContract` delegation), 137–140 (`earnsCommission`).
- `app/Http/Controllers/CommissionController.php` routes `/commissions/sync` (permission `manage_commission_settlements`).
