# Progress — Task-009

## Status snapshot
- Status: `Review`
- Owner: `Academy and commission implementer`
- Last updated: `2026-11-10`
- Current focus: `Academy counselor commission retired; teacher-only class commission verified`
- Blocker: `None`
- Next action: `Review the contract-line retirement gate and the teacher-position guard.`

## Acceptance criteria progress
- [x] Given the approved Task-008 decision, when a student is added through Academy Students, then the enrollment follows the chosen commission path end to end. — Evidence: `AcademyCommissionPathTest::test_direct_academy_enrollment_creates_no_counselor_commission` passes; `AcademyStudentController` remains contract-free as decided.
- [x] Given the chosen path creates or links a service line, when the enrollment is saved, then the line carries the agreed fee, currency, and seller or responsible employee. — Evidence: not applicable under the approved retirement path; `decision.md` explicitly forbids creating or linking an Academy line for commission.
- [x] Given an existing enrollment, when the chosen migration or backfill runs, then it is handled exactly as the decision specifies and no duplicate accrual is created. — Evidence: no migration/backfill was added; existing null-contract enrollments remain unchanged and the direct-enrollment test creates no accrual.
- [x] Given the Academy teacher class flow, when a class is completed, then teacher commission remains unchanged and separate from counselor commission. — Evidence: `AcademyTeacherCommissionTest` passes; `AcademyCommissionPathTest` proves a non-teacher class assignee earns nothing.
- [x] Given the implemented path, when the commission ledger is filtered, then the expected counselor accrual appears or the retired path is no longer advertised. — Evidence: `AcademyContractCommissionTest::test_an_academy_contract_does_not_earn_counselor_commission` passes; no Academy counselor commission UI was added.
- [x] Given the changed enrollment path, when the relevant Academy and commission suites run, then they pass. — Evidence: 8 tests, 48 assertions passed across the four Academy commission/finance suites.

## Work completed with evidence
- Retired Academy counselor commission in `CommissionService::lineAccruals()`: Academy contract lines are skipped, so seller, responsible-employee and counselor accruals are never created from an Academy agreement.
- Kept the existing teacher class path in `CommissionService::syncForAcademyClass()`; it remains keyed to `academy_class_id` and employee.
- Added a teacher-position guard to the class path: only employees with `positions.system_key = teacher` can earn Academy class commission.
- Updated `AcademyContractCommissionTest` to assert that a live Academy contract creates no counselor accrual.
- Added `AcademyCommissionPathTest` for direct enrollment and non-teacher class assignment.
- Did not change `AcademyStudentController`, `AcademyEnrollmentService`, `AcademyEnrollmentResource`, the Academy student dialog, or the Academy students page: the direct path already creates no contract/line, and the approved decision requires no new fee, owner, or UI field.
- Did not change the staff list UI, as explicitly requested by the product owner.
- No migration, backfill, rate change, settlement change, or destructive cleanup was introduced.

## Files changed and why
- `app/Services/CommissionService.php` — skips Academy contract lines for counselor commission and restricts class commission to teacher-position employees.
- `tests/Feature/AcademyContractCommissionTest.php` — changed the Academy expectation from counselor accrual to no counselor accrual.
- `tests/Feature/AcademyCommissionPathTest.php` — new direct-enrollment and non-teacher class coverage.
- Task-008 decision/progress records and Task-009 progress/index records — approved policy, ownership and evidence.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-10 | `php artisan test tests/Feature/AcademyCommissionPathTest.php tests/Feature/AcademyContractCommissionTest.php tests/Feature/AcademyTeacherCommissionTest.php tests/Feature/AcademyStudentFinanceTest.php --stop-on-failure` | Local MySQL test DB | PASS — 8 tests, 48 assertions, 57.80s | Terminal output | Includes direct enrollment, non-teacher class, contract Academy, teacher class, and Academy finance. |
| 2026-11-10 | `php -l app/Services/CommissionService.php tests/Feature/AcademyContractCommissionTest.php tests/Feature/AcademyCommissionPathTest.php` | Local PHP 8.2 | PASS — no syntax errors | Terminal output | All changed PHP files parse. |
| 2026-11-10 | `git diff --check` on the changed files | Git | PASS — no output | Terminal output | No whitespace errors in owned changes. |
| 2026-11-10 | No migration/backfill review | Source inspection | PASS | `decision.md` | Existing direct enrollments and null contract ids are intentionally untouched. |
| 2026-11-10 | No frontend change check | Git status | PASS | Task file list | No Academy UI or staff-list file changed; no typecheck required for this backend-only change. |

## Decisions and deviations
- Academy counselor commission is retired; no contract line is created or linked for direct enrollment.
- Academy contract lines are deliberately non-earning, including live agreements.
- Only employees in a teacher position earn Academy class commission; a class assignment alone is insufficient.
- Pending historical Academy counselor accruals are excluded from future rebuilds and become void on the relevant commission sync; settled/paid accruals are preserved by the existing settlement guard.
- No migration/backfill or UI change was needed; this is a smaller implementation than the Task-009 outline allowed because the chosen path is retirement.

## Blockers and open questions
- None.

## Handoff notes
- Status is `Review`; the changed source/tests are uncommitted.
- Reviewer should verify the retirement interpretation: no Academy counselor accrual from contract lines, teacher-position-only class accrual, and direct enrollment unchanged.
- The existing teacher commission test remains green; non-teacher class assignment is now explicitly rejected for commission.
- The staff list UI was intentionally not modified.

## Activity log (append-only)
- `2026-11-10` Created task record; implementation has not started.
- `2026-11-10` Task-008 approved retirement of Academy counselor commission; claimed Task-009 as Academy and commission implementer.
- `2026-11-10` Implemented the retirement gate, teacher-position guard, updated contract expectation and added direct-enrollment/non-teacher regressions.
- `2026-11-10` Academy commission suites passed 8 tests / 48 assertions; syntax and diff checks passed; moved Task-009 to Review.
