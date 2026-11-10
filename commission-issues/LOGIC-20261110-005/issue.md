# LOGIC-20261110-005 — Completed cases did not pay the assigned responsible employee

## Severity: High

## Status: Resolved in code — 2026-11-10

## Reproduction

1. Use the three case-bearing contracts whose seller is Test Counselor (employee 8):
   - CTR-26-017 — private, 50,000 AFN
   - CTR-26-018 — scholarship, 40,000 AFN
   - CTR-26-019 — test offer, 30,000 AFN
2. Assign Test Admission (employee 9) to each applicant through the super-admin **Edit case** form.
3. Confirm Test Admission has 4% rates for private, scholarship, test offer, and academy.
4. Move each service line through every legal stage to **Completed** in `/service-cases/{line}`.
5. Open Finance → Commissions and filter by Test Counselor, then Test Admission.

## Observed before the fix

All three cases completed successfully:

- Private line 10 → stage 31 / Completed
- Scholarship line 11 → stage 14 / Completed
- Test offer line 12 → stage 40 / Completed

The ledger contained only:

- Test Counselor (employee 8): 3 pending accruals / 6,000 AFN
- Test Admission (employee 9): **0 accruals / 0 AFN**
- Test Teacher (employee 10): 1 separate Academy accrual / 1,000 AFN

The admission filter showed **“No records match the current filters”** and `0-0 of 0`.

## Ownership data

- `contracts.employee_id` is `NULL` for contracts 17, 18, and 19.
- `contracts.sold_by_employee_id` is 8 for all three.
- `applicant_contract.sold_by_employee_id` is 8 for lines 10, 11, and 12.
- `applicant_responsible_employees` contains employee 9 for applicants 7, 8, and 9.
- `applicants.admission_employee_id` is `NULL`.

## Expected

Test Admission should receive 4% of 120,000 AFN = **4,800 AFN**:

- Private: 2,000 AFN
- Scholarship: 1,600 AFN
- Test offer: 1,200 AFN

Each responsible employee should be paid once per service line, at their own rate, and only when they are commission-based with a positive rate.

## Actual cause

`CommissionService::lineAccruals` built the employee set only from:

- `contracts.employee_id`
- `contracts.sold_by_employee_id`
- `applicant_contract.sold_by_employee_id`

It never read `applicant_responsible_employees`.

## Resolution

`CommissionService::syncForApplicant` now loads the case's responsible employee ids from `applicant_responsible_employees` and passes them to `lineAccruals`. `lineAccruals` merges those ids with the seller ids and de-duplicates the list before applying the existing `is_commission_based` and positive-rate checks. A seller who is also responsible therefore receives one accrual, not two.

The existing three completed cases were backfilled through the same service path used by **Reconcile completed cases**:

- Employee 8: 3 accruals / 6,000 AFN
- Employee 9: 3 accruals / 4,800 AFN
- Employee 10: 1 accrual / 1,000 AFN
- Ledger total: 7 pending accruals / 11,800 AFN / 0 settlements

Regression coverage:

- Responsible employee earns at their own rate.
- A responsible employee who is also the seller earns exactly one accrual.
- A non-commission-based responsible employee earns nothing.
- Multiple responsible employees each earn on the same line.
- Existing seller, settlement, cancellation, reconciliation and Academy tests still pass.

## Impact

Before the fix, case work assigned to an admission employee produced no commission for them while the seller was paid. The configured admission rates were unused for case work.

## Evidence

- `evidence/commissions-all-four-accruals.png` — pre-fix seller-only ledger
- `evidence/commissions-counselor-6000.png` — seller filter
- `evidence/commissions-admission-empty.png` — pre-fix admission filter
- `evidence/private-case-completed.png`
- `evidence/scholarship-case-completed.png`
- `evidence/test-offer-case-completed.png`
- `evidence/commissions-after-reconcile.png` — no duplicates before backfill
- `evidence/completion-db.txt` — original ownership data
- `evidence/responsible-commission-fix.txt` — code change, tests, post-fix ledger

## Relevant source

- `app/Services/CommissionService.php` — responsible-employee ids in `lineAccruals`
- `app/Models/Applicant.php` — `responsibleEmployees`
- `tests/Feature/CommissionResponsibilityRegressionTest.php` — rate, duplicate and non-commission cases
- `tests/Feature/ResponsibleEmployeeAssignmentTest.php` — multiple responsible employees
