# Progress — Task-036

## Status snapshot
- Status: `Blocked`
- Owner: `Unassigned (Laravel data-model implementer)`
- Last updated: `2026-11-07`
- Current focus: `Reconcile the students-table decision with this task and the dependent Academy contracts.`
- Blocker: `Task-contract reconciliation for the individual students table.`
- Next action: `Update this task's scope, ownership and criteria, then reconcile Task-037, Task-038, Task-039 and Task-041 to the students-table model before implementation.`

## Acceptance criteria progress
- [ ] Given a database with the existing `academy_classes`, `durations`, `academy_applicant_details` and academy contracts, when the migrations run, then the new columns and `academy_enrollments` exist, every existing row is retained, and no existing academy detail, contract, line, income or snapshot is changed. — Evidence: `not yet available`
- [ ] Given the same database, when the backfill runs, then each existing academy detail that has a class produces at most one enrollment, linked to the matching academy contract line when that link is unambiguous, with student, program, time, duration and class copied and no invented values. — Evidence: `not yet available`
- [ ] Given a detail whose academy contract or line cannot be resolved, when the backfill runs, then no enrollment is attached to an unrelated contract, the detail remains readable, and the unresolved case is reported with its applicant id. — Evidence: `not yet available`
- [ ] Given an applicant with two academy contract lines, when both enrollments are created, then both rows persist for the same applicant, the line-level unique constraint rejects a duplicate enrollment on one line, and neither course overwrites the other. — Evidence: `not yet available`
- [ ] Given durations named `3 months`, `6 Months` and `Beginner`, when the duration-month backfill runs, then the first two store 3 and 6, the unknown value stays null, and a report lists it; running the backfill again does not duplicate or change a known value. — Evidence: `not yet available`
- [ ] Given a class with weekdays, start/end times, capacity, dates and default duration, when the model is saved and reloaded, then the JSON array, date casts, integer capacity and nullable end date round-trip correctly and its `enrollments` relation returns only its own rows. — Evidence: `not yet available`
- [ ] Given a duration with `months = null`, when it is saved, then the value remains null and no default month count is invented; given a negative, zero or greater-than-60 value, then validation or the database constraint rejects it. — Evidence: `not yet available`
- [ ] Given the new feature tests, when `php artisan test --filter=AcademyEnrollmentFoundationTest` and `php artisan test --filter=AcademyEnrollmentBackfillTest` run on an isolated database, then all assertions pass, and the migrations can be rolled back and re-run without duplicate enrollment rows. — Evidence: `not yet available`

## Work completed with evidence
- No implementation work has started. The task contract was created from the 2026-11-07 Academy revamp planning session after repository inspection named the real paths and constraints.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Not run yet | — | — | — | Task not started. |

## Decisions and deviations
- Enrollment is additive; the existing per-case academy detail is not repurposed or deleted. Class dates/capacity/duration months are nullable so existing rows stay valid.
- `2026-11-07`: An individual `students` table is now explicitly required. A student is a durable Academy record distinct from an applicant and an enrollment; one student may have several enrollments. The current applicant-only criteria and Task-037/038/039/041 contracts must be reconciled before implementation.

## Blockers and open questions
- Task-contract reconciliation: the new individual-students-table decision is not yet reflected in this task's acceptance criteria, file ownership or dependent task contracts. Do not implement the applicant-only model unchanged.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the Academy revamp plan and repository inspection.
- `2026-11-07` Added the explicit individual-students-table requirement and flagged the task contract for reconciliation before implementation.
