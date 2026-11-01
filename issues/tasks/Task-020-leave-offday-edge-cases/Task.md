# Task Task-020 — Close leave and off-day edge cases with tests

## Metadata
- Task ID: `Task-020`
- Title: `Close leave and off-day edge cases with tests`
- Initial status: `Ready`
- Type: `Test`
- Priority: `P1`
- Owner role: `HR backend test owner`
- Estimated size: `M`
- Source plan/version: `hr-issues/issues/HR-009-leave-and-offday-validation-coverage.md`; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `HR-009`

## Objective
The documented leave and off-day rules are either covered by a passing test or have a deliberate, recorded gap, and overlapping company off days can no longer double-materialize attendance.

## Context and problem
`tests/Feature/LeaveRequestGrantTest.php` covers a single-day grant and its allowance split; `tests/Feature/HrPayrollOffDaysTest.php` covers off-day materialization, payroll proration and advance recovery. There is no test for a range containing a Friday, a range consisting only of Friday, a range with one duplicate day, a start before joining, a past-dated request, double approval, a forced off-day without justification, or overlapping company off days.

The overlap gap is a code gap, not only a test gap: `OffDayController::validated()` checks date order and the 366-day limit but not overlap with an existing approved or pending range, and `OffDayService` has no overlap guard. Two overlapping approved holidays can materialize conflicting attendance rows.

## Scope
### Included
- Add an overlap guard for company off days: reject a range that intersects an existing approved or pending off day, with a specific business code and the conflicting range in the error context.
- Test leave ranges that include Friday (Friday skipped, other days stored), only Friday (rejected with a specific message), duplicate dates (no duplicate rows, safe rollback), start before joining (rejected), and past dates (policy recorded and tested).
- Test double approval of the same leave request: the second call is idempotent or returns a specific conflict, never double-deducts allowance.
- Test forced `off_day` without a justification is rejected, and with a justification is stored with the note.
- Test that apply/revert never overwrites a worked or leave row, and that an off day is not deducted in payroll while an excess leave day is prorated as absent.
- Fix only the defects these tests expose.

### Excluded
- Changing the allowance split, Friday rule, missing-attendance policy or payroll formula.
- Redesigning the leave or off-day pages.
- New HR features such as half-day requests or carry-over.
- The staff form, timesheet and payroll UI (Task-006).

## Preconditions and assumptions
- Friday remains the weekly day off and is never materialized; do not "fix" that behavior.
- An off-day overlap is any date intersection between the requested range and an existing `pending` or `approved` range. Rejected/cancelled ranges do not block.
- "Past dates" behavior is not documented precisely; the task records the current behavior in a test and flags it if it looks wrong, rather than inventing a new rule.
- Tests use the existing factories and an isolated month; no reliance on the demo database.

## Dependencies
- `None` — backend-only and test work; the overlap guard does not depend on UI tasks.

## Contracts and boundaries
- Input/output: `POST /off-days` and `PUT /off-days/{id}` keep their shapes; overlap returns HTTP 422 with `errors.start_date` or `errors.end_date` and a business code identifying the conflicting range.
- Compatibility guarantee: editing the same off day must not conflict with itself; approved holidays created before this change are not rewritten.
- Error behavior: all newly tested rejections return specific business codes, not a generic validation message; the frontend already surfaces server messages.
- Permission/data scope: `manage_off_days`, `approve_off_days` and `grant_leave_requests` behavior is unchanged.
- Integration seam: the overlap check lives in one place used by both store and update; do not duplicate it in the controller and service with different boundaries.
- Files this task must not modify: `app/Http/Controllers/SalaryController.php`, `app/Services/SalaryAdvanceService.php`, `resources/ts/pages/leave_requests.vue`, `resources/ts/pages/payrolls.vue`.

## Exclusive file ownership
- `app/Http/Controllers/OffDayController.php` — overlap validation and conflict payload.
- `app/Services/OffDayService.php` — overlap guard and idempotent materialization/revert.
- `app/Http/Controllers/LeaveRequestController.php` — only fixes exposed by the edge-case tests.
- `tests/Feature/LeaveRequestEdgeCaseTest.php` — new range/approval coverage.
- `tests/Feature/OffDayOverlapTest.php` — new overlap/apply/revert coverage.

## Acceptance criteria
- [ ] Given a leave range that includes a Friday, when it is submitted, then Friday is skipped, every other date is stored once, and the response reports the stored count.
- [ ] Given a range containing only Friday, when it is submitted, then it is rejected with a specific message and no rows are written.
- [ ] Given a range with a duplicate date in the payload, when it is submitted, then no duplicate attendance claim is written and the transaction leaves no partial rows.
- [ ] Given a range starting before the employee's join date or entirely in the past, when it is submitted, then the documented rule is enforced by a test and a specific code is returned.
- [ ] Given an already-approved leave request, when approval is submitted again, then allowance is deducted once and the second call is idempotent or returns a specific conflict.
- [ ] Given a forced off-day approval, when it lacks a justification, then it is rejected; with a justification, the note is stored and the row is `off_day`.
- [ ] Given an approved off day that overlaps an existing approved or pending range, when it is saved, then it is rejected with the conflicting range identified and no attendance rows are materialized.
- [ ] Given an approved off day covering a worked or leave day, when it is applied and reverted, then the worked/leave row is preserved and only synthesized rows are added or removed.
- [ ] Given off days and excess leave in the same month, when payroll runs, then off days are not deducted and the uncovered excess is prorated as absent.

## Verification plan
- Automated: `php artisan test --filter=LeaveRequestEdgeCaseTest` and `--filter=OffDayOverlapTest`; run the existing `LeaveRequestGrantTest` and `HrPayrollOffDaysTest` as regression; run `php artisan test --filter=Hr` if time permits.
- Manual: submit a Friday-spanning range in the UI only if a page change is needed; otherwise exercise the API with a disposable employee and inspect attendance rows and the payroll figures.
- Fixtures: one employee with an allowance, one month with a Friday, one existing approved off day and one pending range; disposable only.
- Regression: verify the existing grant split, missing-attendance policy, advance recovery and payslip snapshots still pass.
- Evidence: test output for each case, attendance rows before/after overlap attempts, payroll worked/absent/off-day counts.

## Implementation notes
Place the overlap query in `OffDayService` and call it from both store and update, excluding the record being edited and ignoring rejected/cancelled ranges. Use a transaction so a rejected range leaves no attendance rows behind. Leave the Friday skip and allowance split as they are; the tests should prove them, not redefine them. Build each edge case on factories with an isolated month, and assert both the HTTP response and the attendance/payroll rows.

## Risks and rollback
- Risk: the overlap rule blocks legitimate consecutive holidays that share a boundary date. Mitigation: define intersection as a shared date, allow adjacent ranges, and test both.
- Risk: tightening validation breaks an existing workflow that announces ranges twice. Mitigation: search existing data for overlaps first and report them; do not auto-delete.
- Data migration: none; if existing overlaps are found, handle them as a reviewed data correction.
- Stop condition: if product wants overlapping holidays to merge rather than be rejected, stop and redesign the service rule instead of adding a guard.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
