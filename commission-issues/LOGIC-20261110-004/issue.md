# LOGIC-20261110-004 — Academy enrollment creates no contract, so Academy commission cannot accrue

## Severity: High

## Status: Confirmed behavior; product decision needed

## Reproduction

1. The `/contracts/academy` route redirects to `/academy/students`; there is no Academy contract wizard at the CRM route.
2. Sign in as Test Counselor and use **Academy → Students → Add student**.
3. Enroll CC Academy Student in **Commission Review Class** (class fee 10,000 AFN).
4. Inspect the created enrollment, contracts, and Finance → Commissions.

## Observed

- `academy_enrollments` row 1 was created for student 1, class 3.
- `contract_id` is `NULL`.
- `applicant_contract_id` is `NULL`.
- The student list shows the class and the 10,000.00 fee.
- No Academy contract or contract line was created.
- Finance → Commissions has only the teacher class accrual of 1,000 AFN; Test Counselor has no Academy accrual.

## Expected

If the Academy service is commissionable for the selling counselor, the enrollment/live Academy record must create or link a contract line with the 10,000 AFN fee and the counselor as `sold_by_employee_id`, so the configured 5% Academy rate can accrue (500 AFN in this test).

The project notes describe Academy as case-free and earning from its live contract, while the current route comment says the Academy contract surface is gone.

## Actual

The only UI path creates an enrollment without any contract. `CommissionService` cannot create an Academy accrual without a contract line.

## Impact

Academy counselor commission is unreachable through the normal UI, even though the rate table and commission engine support Academy. The teacher class commission still works once the class is completed.

## Evidence

- `evidence/academy-student-enrollment-no-contract.png`
- `evidence/academy-db.txt`
- `evidence/commissions-one-teacher-accrual.png`

## Relevant source

- `resources/ts/plugins/1.router/routes.ts` — `/contracts/academy` redirect
- `app/Services/Contracts/ContractGenerationService.php` — Academy contract/line creation
- `app/Services/CommissionService.php` — Academy contract accrual requires contract data
