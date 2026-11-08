# Progress — Task-037

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (Laravel contract and domain implementer)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Implement the contract-generation boundary, pivot, term/capacity service and the five focused feature suites.`

## Acceptance criteria progress
- [ ] Given an academy class with an end date and a duration with months, when an academy contract is created, then `contracts.start_date` is today, `contracts.end_date` is the earlier of the class end and start plus months, and one enrollment row with the same boundaries is linked to the new line. — Evidence: `not yet available`
- [ ] Given an open-ended class and a three-month duration, when a contract is created, then the end date is exactly three calendar months after the start and no class end date is invented. — Evidence: `not yet available`
- [ ] Given a duration with null months and a class with no end date, when creation is attempted, then it fails with 422 `business.academy_term_undefined`, and no contract, line, enrollment or income row is created. — Evidence: `not yet available`
- [ ] Given a class whose `ends_on` is in the past, when creation is attempted, then it fails with 422 `business.academy_class_ended` and writes nothing. — Evidence: `not yet available`
- [ ] Given a class at capacity with active enrollments, when another contract is attempted, then it fails with 422 `business.academy_class_full`; completed, withdrawn and cancelled enrollments do not consume a seat. — Evidence: `not yet available`
- [ ] Given a full class and an update that only edits an existing enrollment's duration or notes, when the update is saved, then the current enrollment is excluded from the seat count and the update succeeds; changing to another full class still fails. — Evidence: `not yet available`
- [ ] Given one visitor with an existing academy course, when a second academy contract is created for the same student, then a second contract, line and enrollment are created, the first enrollment and its contract snapshot are unchanged, and no academy applicant detail row is overwritten. — Evidence: `not yet available`
- [ ] Given an academy contract created with `paid_fee > 0`, when the end date and enrollment are written, then the existing income receipt is still created once, its amount and line link are unchanged, and the contract snapshot contains the class, programme, time, duration, student, teachers and start/end dates. — Evidence: `not yet available`
- [ ] Given an academy extension, when it is created, then the new line gets its own enrollment and end date, the parent contract and enrollment remain, and the extension snapshot records the new term. — Evidence: `not yet available`
- [ ] Given a test-offer or academy contract, when it is created, then the party, contract, service line and money are written, `applicant.current_stage_id` and `line.current_stage_id` are null, no `applicant_stage_histories` row exists, no stage is looked up, and no stage-bearing case is exposed. — Evidence: `not yet available`
- [ ] Given a scholarship or private contract created or updated with two responsible employees and the permission, when it succeeds, then two distinct pivot rows are stored, a rerun does not duplicate them, and the legacy `admission_employee_id` is not written; without the permission the request is 403 before any contract, line, pivot or notification write. — Evidence: `not yet available`
- [ ] Given a test-offer/academy payload containing `responsible_employee_ids`, or an id outside the active admission-assignment scope, when validated, then it is refused with a field error and no contract, line, pivot or income is written. — Evidence: `not yet available`
- [ ] Given a completed scholarship/private case with multiple responsible employees, when the contract is generated and commission sync runs, then accruals still come only from the seller/employee fields and their count and amount are identical to the single-responsibility fixture; responsible employees never become recipients. — Evidence: `not yet available`
- [ ] Given the focused suites, when `php artisan test --filter=AcademyContractTermTest`, `php artisan test --filter=AcademyEnrollmentCapacityTest`, `php artisan test --filter=AcademyMultiCourseTest`, `php artisan test --filter=AcademyCaseFreeContractTest` and `php artisan test --filter=ResponsibleEmployeeAssignmentTest` run on an isolated database, then they pass. — Evidence: `not yet available`

## Work completed with evidence
- No implementation work has started. The task contract was created from the 2026-11-07 Academy revamp planning session after repository inspection named the real paths and constraints.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Not run yet | — | — | — | Task not started. |

## Decisions and deviations
- Contract start is the sale date; end is the class end or start plus duration months. No client-supplied dates; capacity counts active enrollments only.
- Test-offer and academy contract generation writes no stage, stage history or pipeline case; the party remains the contract counterparty. Scholarship/private keep their existing stage write.
- The singular admission assignment becomes the plural `responsible_employee_ids` pivot. The legacy column is backfilled once and retained read-only; the renamed permission gates the field.
- Commissions stay on the seller/employee fields; multiple responsible employees never enter accrual math.

## Blockers and open questions
- None.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the Academy revamp plan and repository inspection.
- `2026-11-07` Forward ownership of the paths needed for Task-046 moved to Task-046.
- `2026-11-07` Corrected the boundary: this task retains contract generation, the pivot and the contract-level permission, because Task-037 owns term/capacity and those files; Task-046 consumes them and owns stage/UI/notification surfaces.
