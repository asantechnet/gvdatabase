# HR-009 — Leave and company off-day edge cases need verification

- Severity: Needs verification
- Area: Off Day Requests (`/leave-requests`), Company Off Days (`/off-days`), self-service leave
- Status: Review incomplete. This entry records the risks identified from source and the checks that must still be run; it is **not** a confirmed defect.

## Why this is open

The HR ledger pages for leave/off days are approval-oriented; submitting a request requires an employee account and the self-service surface, and proving the allowance split end to end requires manipulating dates and approved usage. That was not completed in this session. The UI does expose only `Approve Selected (0)` on the leave ledger and `Announce Off Day` on the company ledger, so the full employee path was not reachable from the admin session alone.

## Checks to run

1. **Range submission** — as an employee, request a range containing a Friday: Fridays must be skipped, not rejected; a range containing only Fridays must return `business.leave_range_contains_no_workdays`.
2. **Duplicate/overlap** — submitting a range that overlaps an existing request must return `business.leave_request_date_exists` (specific toast, no partial rows written). Confirm the transaction rolls back every date.
3. **Past dates** — a start date before today must return `business.retroactive_leave_request_not_allowed`; before join date must return `business.leave_before_employment`.
4. **Allowance split** — approve N days for an employee whose allowance has only M days left: M days become `off_day`, N−M become `absent`; the attendance rows and the salary's `absent_days` must reflect that.
5. **Forced off-day** — `force_off_day` without `approval_note` must be rejected; with a note, the excess stays `off_day` and the justification is shown in the ledger.
6. **Approval concurrency** — approving the same request twice must not double-write the attendance claim.
7. **Company off day** — announcing a range creates `off_day` attendance rows for non-worked days only, never overwrites worked or leave rows, and deleting the holiday restores/deletes the synthesized rows. Overlapping company off days and the same range twice need explicit behavior (currently no overlap guard was found in `OffDayController::validated`).
8. **Payroll interaction** — an approved off day must not be deducted; an excess absence must be prorated by `amount_paid × absent_days ÷ total_days`.

## Source notes

- `LeaveRequestController::requestRange` rejects cross-year ranges (`business.request_rejected`), and stores one row per date with `is_granted = false`.
- `LeaveRequestController::grant` locks employees and rows, and splits by the employee's allowance; `force_off_day` requires `approval_note` via `Rule::requiredIf`.
- `OffDayController::validated` limits a range to 366 days but does not appear to reject overlapping off-day ranges, which can produce two competing holidays.
- `OffDayService` is the single writer for synthesized attendance; verify revert behavior on edit/reject/delete.

## Evidence

- Source only; no UI execution recorded. Keep this issue open until the scenarios above are run against a disposable employee account.
