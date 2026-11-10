# Commission Review — 2026-11-10

## Environment

- Local test database and `http://localhost:8000`
- Playwright CLI session `commission-review-20261109`
- Super admin: `admin@example.test`
- Counselor: `counselor.commission@example.test` (user 9 / employee 8)
- Admission: `admission.commission@example.test` (user 10 / employee 9)
- Teacher: `teacher.commission@example.test` (user 11 / employee 10)
- All three employee records are commission-based.
- No test records were cleaned up.

## Result summary

### Completed checks

- The super admin assigned Test Admission (employee 9) to all three test cases in the `applicant_responsible_employees` pivot:
  - Applicant 7 — CC Private Applicant / CTR-26-017
  - Applicant 8 — CC Scholarship Applicant / CTR-26-018
  - Applicant 9 — CC Test Offer Applicant / CTR-26-019
- All three cases were moved through every legal stage to their terminal **Completed** stage using the service-case UI:
  - Private, line 10: stage 31 / Completed
  - Scholarship, line 11: stage 14 / Completed
  - Test offer, line 12: stage 40 / Completed
- The applicants are closed and their stages mirror the completed lines.
- Case commissions now accrue to the seller **and every responsible employee** on the case:
  - Test Counselor (employee 8, seller): 3 pending accruals / **6,000 AFN**
    - Private: 50,000 × 5% = 2,500 AFN
    - Scholarship: 40,000 × 5% = 2,000 AFN
    - Test offer: 30,000 × 5% = 1,500 AFN
  - Test Admission (employee 9, responsible): 3 pending accruals / **4,800 AFN**
    - Private: 50,000 × 4% = 2,000 AFN
    - Scholarship: 40,000 × 4% = 1,600 AFN
    - Test offer: 30,000 × 4% = 1,200 AFN
  - Test Teacher (employee 10): 1 separate Academy accrual / **1,000 AFN**
- Full ledger after backfill: **7 pending accruals / 11,800 AFN / 0 settlements**.
- The three already-completed cases were brought in line with the new rule by running the same `CommissionService` path the reconcile action uses. No duplicate seller accruals were created.

### Responsible-employee commission rule

`CommissionService` now builds each service line's owner set from:

- `contracts.employee_id`
- `contracts.sold_by_employee_id`
- `applicant_contract.sold_by_employee_id`
- every employee in `applicant_responsible_employees`

The list is de-duplicated, so an employee who is both seller and responsible receives **one** accrual for a line. Each owner is still skipped unless `employees.is_commission_based` is true and they have a positive rate for the line's service. The rate is applied to the same line base (`fee − discount`) as the seller's commission.

Existing completed cases require a reconcile/backfill, which was done for the three review cases. New case completions include responsible employees automatically.

### Not exercised

- No settlement, approval, or payout was created; the settlement tab remains empty.
- The Academy enrollment still has no contract, so the separate Academy counselor commission remains unreachable (LOGIC-20261110-004).

### Important test-state note

Two roles were added during the investigation to work around missing permissions:

- Test Counselor: `manager` + `staff`
- Test Admission: `manager` + `case_officer` + `staff`

These roles were not removed. The counselor permission block itself is **expected behavior**, not a defect.

## Confirmed issues

| ID | Finding | Severity | Status |
| --- | --- | --- | --- |
| LOGIC-20261110-002 | Assigned teacher cannot complete the class from the UI; the Teacher role is also absent from Users role options | High | Confirmed |
| LOGIC-20261110-003 | Responsible-employee assignment is stored in the plural pivot, but the Applications list and access scope use the singular field, so cases still show Unassigned and the admission user cannot see them without an extra role | High | Confirmed |
| LOGIC-20261110-004 | Academy Add Student creates an enrollment with no contract or contract line, so Academy counselor commission cannot accrue | High / product decision needed | Confirmed behavior |
| LOGIC-20261110-005 | Case commission originally paid the seller only; responsible employees were never inserted into the ledger | High | **Resolved** — responsible employees now earn per line, de-duplicated and rate-gated |

## Non-issue

- `LOGIC-20261110-001` — counselor cannot assign responsible employees without `assign_responsible_employees`. This is intentional permission behavior. The evidence is retained only as a test record.

## Evidence index

- `LOGIC-20261110-002/evidence/` — roles menu, teacher class view, super-admin class action
- `LOGIC-20261110-003/evidence/` — applications list after assignment, admission list before manager access, database assignment evidence
- `LOGIC-20261110-004/evidence/` — Academy enrollment row, database contract/enrollment evidence, commissions page
- `LOGIC-20261110-005/evidence/` — pre-fix zero-commission screenshots, completed case pages, reconciliation, post-fix ledger and test evidence
- `_verification/` — additional completion and ledger screenshots
