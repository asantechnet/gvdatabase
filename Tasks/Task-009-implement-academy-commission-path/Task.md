# Task Task-009 — Implement the approved Academy commission path

## Metadata
- Task ID: `Task-009`
- Title: `Implement the approved Academy commission path`
- Initial status: `Blocked`
- Type: `Full-stack`
- Priority: `P0`
- Owner role: `Academy and commission implementer`
- Estimated size: `L`
- Source plan/version: `commission-issues/LOGIC-20261110-004/issue.md`, 2026-11-10 review; Task-008 decision
- Requirement / behavior IDs: `LOGIC-20261110-004`

## Objective
Implement the path chosen in Task-008 so Academy enrollment either produces the intended counselor commission through a durable contract line or explicitly stops advertising a commission that cannot accrue. Existing enrollments, teacher commission, and fee/balance displays must remain consistent.

## Context and problem
Academy Students → Add student currently creates a student and an enrollment but no contract or contract line. The commission engine requires a contract line to compute an Academy counselor accrual, so the configured rate is unreachable. The remedy depends on a product decision: link the enrollment to money, use another source, or retire the expectation. This task implements that decision end to end.

## Scope
### Included
- Implement the Task-008 decision across the enrollment creation path and the commission trigger.
- If a contract/line is chosen, create or link it with the approved fee, currency, seller/owner, and enrollment relationship.
- If the commission path is retired, remove or gate the misleading Academy counselor expectation and update the affected UI/API surfaces.
- Handle existing enrollments exactly as the decision specifies; do not create duplicate accruals.
- Keep teacher class commission separate and unchanged.
- Add regression coverage for the chosen path, including a repeated sync/reconcile case.

### Excluded
- Re-deciding the policy or inventing a fee/owner for historical enrollments.
- Teacher class completion and teacher commission; Task-007 and the existing class path own those.
- Contract extension integrity; Task-004 owns it.
- Responsible-employee access; Task-006 owns it.
- Changing commission rates, settlement, or payout behavior unless the decision explicitly requires it.

## Preconditions and assumptions
- Task-008 has approved a decision record with fee, currency, owner, lifecycle, and existing-enrollment rules.
- `academy_enrollments` already has nullable contract/line columns; the decision may use them or require an additive migration.
- `CommissionService` remains the single accrual writer; no parallel ledger should be introduced.
- The direct enrollment path and the contract-based Academy path must not double-pay the same sale.

## Dependencies
- `Task-008` — requires the approved Academy commission decision.

## Contracts and boundaries
- Enrollment contract: the chosen link between `academy_enrollments`, the student/applicant, and the contract line is stable and exposed where the UI needs it.
- Commission contract: counselor accrual is created once per approved line and repeated syncs update rather than duplicate; teacher class accrual remains keyed to the class.
- Fee/currency contract: the line uses the approved fee basis and currency/rate snapshot; client-supplied overrides are ignored.
- Lifecycle contract: cancellation, withdrawal, refund, and completion behavior matches the decision.
- Compatibility: existing enrollments without a line remain readable; the chosen backfill/no-op rule is explicit.
- Files/modules this task must not modify: `app/Services/Contracts/ContractGenerationService.php`, contract extension files, teacher class completion UI, and the edit-window middleware.

## Exclusive file ownership
- `app/Http/Controllers/AcademyStudentController.php` — direct enrollment creation path.
- `app/Services/AcademyEnrollmentService.php` — enrollment-to-line/link behavior.
- `app/Services/CommissionService.php` — approved counselor accrual trigger.
- `app/Http/Resources/AcademyEnrollmentResource.php` — enrollment/line payload.
- `resources/ts/components/academy/AcademyStudentDialog.vue` — fee/owner fields if the decision requires them.
- `resources/ts/pages/academy/students.vue` — fee/balance/commission presentation.
- `tests/Feature/AcademyStudentFinanceTest.php` — direct enrollment finance regressions.
- `tests/Feature/AcademyContractCommissionTest.php` — contract-based Academy commission regressions.
- `tests/Feature/AcademyCommissionPathTest.php` — chosen-path and duplicate-sync coverage.

## Acceptance criteria
- [ ] Given the approved Task-008 decision, when a student is added through Academy Students, then the enrollment follows the chosen commission path end to end.
- [ ] Given the chosen path creates or links a service line, when the enrollment is saved, then the line carries the agreed fee, currency, and seller or responsible employee.
- [ ] Given an existing enrollment, when the chosen migration or backfill runs, then it is handled exactly as the decision specifies and no duplicate accrual is created.
- [ ] Given the Academy teacher class flow, when a class is completed, then teacher commission remains unchanged and separate from counselor commission.
- [ ] Given the implemented path, when the commission ledger is filtered, then the expected counselor accrual appears or the retired path is no longer advertised.
- [ ] Given the changed enrollment path, when the relevant Academy and commission suites run, then they pass.

## Verification plan
- Automated checks: `php artisan test tests/Feature/AcademyCommissionPathTest.php tests/Feature/AcademyStudentFinanceTest.php tests/Feature/AcademyContractCommissionTest.php tests/Feature/AcademyTeacherCommissionTest.php`.
- Data check: create a direct enrollment and a contract-based Academy line in the test database; confirm one counselor accrual per approved sale and no duplicate after a second sync.
- Manual/UI scenario: add a student, inspect the fee/balance and commission ledger, then complete a class and confirm the teacher accrual is separate.
- Existing-data check: run the chosen backfill/no-op against a fixture with a null contract and verify the decision's expected outcome.
- Expected result: the chosen path is visible end to end; the alternative path is neither advertised nor double-paid.
- Required evidence: test output, enrollment/line database rows, commission ledger rows, and UI observations.

## Implementation notes
- Keep `CommissionService` as the only accrual writer; do not create a second Academy ledger.
- If the direct path needs a contract line, reuse the existing contract/line model and fee snapshot rather than adding a parallel money table.
- Do not edit `ContractGenerationService`; if the chosen path requires a change there, stop and coordinate with Task-006 ownership before proceeding.
- Make repeated enrollment saves idempotent; a re-sync must update the existing accrual, not add another.
- Keep teacher class commission keyed to `academy_class_id` and do not route it through the counselor line.

## Risks and rollback
- Risk: creating a contract solely to satisfy commission could duplicate the existing contract-based Academy flow; use the decision to choose one path.
- Risk: backfilling historical enrollments could create unexpected liabilities; honor the explicit decision and make the backfill reversible.
- Risk: a second sync could duplicate accruals; add the idempotency test before shipping.
- Rollback: revert the enrollment/commission changes and restore the prior direct-enrollment behavior; preserve issued lines and settled accruals rather than deleting them.

## Handoff checklist
- [ ] Task-008 decision is followed exactly.
- [ ] Direct and contract-based Academy paths cannot double-pay.
- [ ] Teacher commission remains separate and green.
- [ ] Existing enrollments are handled as approved.
- [ ] No overlap with Task-006 contract-generation ownership.
