# Progress — Task-020

## Status snapshot
- Status: `Review`
- Owner: `HR backend test owner`
- Last updated: `2026-10-31`
- Current focus: `Implementation and targeted verification complete; ready for review.`
- Blocker: `None for the code. Existing data has not been scanned for pre-existing overlapping off days (no live database access in this session).`
- Next action: `Review the diff; before deployment, run the read-only overlap query recorded under Blockers and decide any data correction.`

## Acceptance criteria progress
- [x] Given a leave range that includes a Friday, when it is submitted, then Friday is skipped, every other date is stored once, and the response reports the stored count. — Evidence: `LeaveRequestEdgeCaseTest::test_a_range_skips_fridays_and_reports_every_stored_day` (2026-11-05..09 stores 4 rows, omits 2026-11-06, response `stored=4`).
- [x] Given a range containing only Friday, when it is submitted, then it is rejected with a specific message and no rows are written. — Evidence: `test_a_range_containing_only_friday_is_rejected_without_rows` (422 `business.leave_range_contains_no_workdays`, 0 `leaves` rows).
- [x] Given a range with a duplicate date in the payload, when it is submitted, then no duplicate attendance claim is written and the transaction leaves no partial rows. — Evidence: `test_a_range_that_reuses_an_existing_date_rolls_back_every_new_row` (range 10..12 with 10 already stored: 422 `business.leave_request_date_exists`, the existing row remains, 11 and 12 are absent). A range payload only carries start/end, so duplicate payload dates are impossible; the equivalent duplicate is a date already in the ledger.
- [x] Given a range starting before the employee's join date or entirely in the past, when it is submitted, then the documented rule is enforced by a test and a specific code is returned. — Evidence: `test_past_and_pre_employment_starts_return_specific_codes` (422 `business.retroactive_leave_request_not_allowed`, then 422 `business.leave_before_employment`, 0 rows).
- [x] Given an already-approved leave request, when approval is submitted again, then allowance is deducted once and the second call is idempotent or returns a specific conflict. — Evidence: `test_approving_the_same_request_twice_deducts_allowance_once` (one attendance row with the same id, `off_day_days=1`, ledger `used=1`, `remaining=9`).
- [x] Given a forced off-day approval, when it lacks a justification, then it is rejected; with a justification, the note is stored and the row is `off_day`. — Evidence: `test_a_forced_off_day_requires_and_stores_a_justification` (422 with `errors.approval_note`, no attendance; then `off_day`, override and note stored).
- [x] Given an approved off day that overlaps an existing approved or pending range, when it is saved, then it is rejected with the conflicting range identified and no attendance rows are materialized. — Evidence: `OffDayOverlapTest::test_an_overlapping_range_is_rejected_and_materializes_nothing` and `test_a_pending_range_also_blocks_a_new_range` (422 `business.off_day_overlaps_existing`, `errors.start_date`, `conflict` carries id/dates/status; attendance unchanged).
- [x] Given an approved off day covering a worked or leave day, when it is applied and reverted, then the worked/leave row is preserved and only synthesized rows are added or removed. — Evidence: `test_apply_and_revert_preserve_worked_and_leave_rows` (worked `present` row and leave `absent` row keep their data and null `off_day_id`; only the untouched employee's synthesized rows are created/deleted).
- [x] Given off days and excess leave in the same month, when payroll runs, then off days are not deducted and the uncovered excess is prorated as absent. — Evidence: `test_payroll_never_deducts_off_days_but_prorates_excess_leave` (26 total, 24 worked, 1 off, 1 absent, 100 deduction on 2600, net 2500).

## Work completed with evidence
- Added `OffDayService::findOverlap()`: one query for pending/approved ranges sharing a date, excluding the row being edited, ordered by start date.
- Added `OffDayController::assertNoOverlap()` and called it from `store`, `update` and `approve`. Rejection is HTTP 422 with `code=business.off_day_overlaps_existing`, `errors.start_date`, and a `conflict` object (`id`, `start_date`, `end_date`, `status`). The check runs before materialization, so a rejected range writes no attendance. Adjacent ranges are allowed; rejected ranges do not block.
- Fixed a real code gap exposed by the duplicate-date test: `ValidationException` with a business code produced the generic top-level `validation_failed`. Duplicate-date rejection now returns an explicit 422 with `business.leave_request_date_exists` and `errors.start_date`, while the transaction still rolls back every new row.
- Added `stored` (the number of rows written) to both range-submission responses while keeping the existing `data` array.
- Added the missing business-code translations to `lang/en|fa|ps/api.php`: `leave_range_contains_no_workdays`, `leave_before_employment`, `retroactive_leave_request_not_allowed`, `leave_date_is_weekly_day_off`, `leave_request_conflicts_with_attendance`, `off_day_overlaps_existing`. These codes previously fell back to the generic business message.
- Added `tests/Feature/LeaveRequestEdgeCaseTest.php` (6 tests) and `tests/Feature/OffDayOverlapTest.php` (5 tests).

## Files changed and why
| File | Change |
|---|---|
| `app/Services/OffDayService.php` | Added the single overlap query used by store/update/approve. |
| `app/Http/Controllers/OffDayController.php` | Rejects overlapping pending/approved ranges with a specific code and conflict context; ignores the row being edited. |
| `app/Http/Controllers/LeaveRequestController.php` | Returns `business.leave_request_date_exists` explicitly for duplicate dates and reports `stored` for ranges. |
| `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php` | Added the six missing leave/off-day business messages in every supported locale. |
| `tests/Feature/LeaveRequestEdgeCaseTest.php` | New range, duplicate, date-boundary, double-approval and forced-note coverage. |
| `tests/Feature/OffDayOverlapTest.php` | New overlap, adjacency, self-edit, apply/revert preservation and payroll coverage. |

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php -l` on every changed PHP file | Local | PASS | Terminal output | No syntax errors. |
| 2026-10-31 | `php artisan test --filter='LeaveRequestEdgeCaseTest|OffDayOverlapTest'` | Local, RefreshDatabase | PASS | Terminal output | 11 tests, 100 assertions. |
| 2026-10-31 | `php artisan test --filter='LeaveRequestGrantTest|HrPayrollOffDaysTest'` | Local, RefreshDatabase | PASS | Terminal output | 11 tests, 53 assertions; existing grant split, off-day materialization, payroll and advance recovery unchanged. |
| 2026-10-31 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | Local | PASS | Terminal output | 20 tasks, 0 errors. |
| 2026-10-31 | `git diff --check` | Local | PASS | Terminal output | No whitespace errors in the task diff. |
| 2026-10-31 | Full `php artisan test --filter=Hr` | Local | NOT RUN | — | Targeted suites and the two HR regressions were run instead; full-suite evidence is not claimed. |

## Decisions and deviations
- Overlap is a **shared date** between pending/approved ranges; adjacent ranges (one ends the day before the next starts) are allowed. Rejected ranges never block. The edited row is excluded through its id.
- The overlap query lives only in `OffDayService`; the controller owns the HTTP contract. `approve` also guards legacy pending ranges created before this change.
- A range payload cannot contain a duplicate date, so the duplicate criterion is covered as "a date in the requested range already exists". The whole request rolls back and the response keeps its specific business code.
- Past-date policy is recorded as it exists: self-service rejects a start before today (`business.retroactive_leave_request_not_allowed`); the staff ledger keeps its administrative backfill behavior. No new staff-side rule was invented.
- Payroll's `worked_days` excludes off days (they are reported as `off_days` and are not deducted); the test records 24 worked + 1 off + 1 absent = 26 total. This matches the existing payroll model.
- Shared-file deviation: `lang/*/api.php` is listed under Task-018's ownership. Task-020 added only its six business keys and did not reformat the files; Task-018 is already in Review.

## Blockers and open questions
- No live database was available, so existing overlapping pending/approved off days were not scanned. Before deploying the guard, run this read-only check and review any hits (do not auto-delete):
  ```sql
  SELECT a.id AS a_id, a.start_date AS a_start, a.end_date AS a_end, a.status AS a_status,
         b.id AS b_id, b.start_date AS b_start, b.end_date AS b_end, b.status AS b_status
  FROM off_days a
  JOIN off_days b ON a.id < b.id
   AND a.start_date <= b.end_date AND a.end_date >= b.start_date
  WHERE a.status IN ('pending', 'approved') AND b.status IN ('pending', 'approved');
  ```
  Existing overlaps would only block future overlapping saves; no rows are rewritten by this change.
- `findOverlap`/`assertNoOverlap` are not concurrency-locked. Two simultaneous announcements of the same range could both pass the check. The current UI submits one range at a time; if concurrent announcements become possible, add a unique/overlap lock at the database or transaction boundary.

## Handoff notes
- Ready for review; not marked Done.
- Review the added translations in all three locales and the shared-file note above.
- After deployment, run the overlap scan and, if desired, the full HR suite (`php artisan test --filter=Hr`) for broader regression evidence.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated HR-009; implementation has not started.
- `2026-10-31 Asia/Kabul` Implemented the overlap guard, explicit duplicate-date code, `stored` count and missing business translations; added both test suites. Targeted and regression checks pass; moved to `Review`.
