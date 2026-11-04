# Review — Task-020 leave and off-day edge cases

- **Reviewed:** 2026-11-04
- **Verdict:** Needs changes — the new edge-case suite is red
- **Method:** `php artisan test tests/Feature/LeaveRequestEdgeCaseTest.php`; source inspection; related `OffDayOverlapTest`, grant and payroll suites passed.
- **Scope:** review only. No code was changed.

## Finding — The pre-employment test is date-dependent and now fails

**Severity:** Medium (CI/test reliability; also exposes ambiguous error precedence)

**Expected:** The pre-employment and past-date cases return their specific business codes, and the suite remains green.

**Actual:** `test_past_and_pre_employment_starts_return_specific_codes` fails:

```text
Failed asserting that two strings are identical.
-'business.leave_before_employment'
+'business.retroactive_leave_request_not_allowed'
```

The test uses a fixed start date of `2026-11-02` for an employee joining `2026-12-01`. When the test was written on 2026-10-31 that date was in the future, so the join-date check fired. On 2026-11-04 the same date is in the past; `LeaveRequestController` checks the past-date rule first (line 142) and never reaches the join-date rule (line 143). The test therefore fails solely because the calendar advanced.

**Evidence:** `tests/Feature/LeaveRequestEdgeCaseTest.php:119–133`; `app/Http/Controllers/LeaveRequestController.php:142–143`.

**Suggested fix:** Make the test date-independent (use dates relative to `now()` for the past case and a future join date for the pre-employment case), and decide the intended precedence when a date is both past and before employment. If the join-date reason is the desired one, move that check before the past-date check. Re-run the suite on a real current date.

## Notes

- The other Task-020 coverage (Friday skipping, empty range, duplicate dates, double approval, forced off-day note) passed in this run.
- `OffDayOverlapTest` passed (5 tests) in the same review run.
- No source was modified during this review.
