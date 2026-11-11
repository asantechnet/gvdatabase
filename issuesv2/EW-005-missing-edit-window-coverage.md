# EW-005 — No regression coverage for the employee or contract edit-window paths

- **Severity:** Medium
- **Area:** Test coverage
- **Status:** Confirmed
- **Confidence:** High

## Summary

The existing edit-window tests cover comments, subtasks, and the administrative override only. There is no test that:

- a non-override role with `edit_employees` can edit a fresh employee and is rejected after 60 minutes;
- a contract update/cancel respects the window;
- a scalar-ID contract route does **not** silently bypass the middleware.

The live audit found the contract bypass precisely because no such test exists.

## Expected

- Tests for both sides of the 60-minute boundary on an employee record.
- A test that an old contract update is rejected (or explicitly exempt).
- A test that an override produces the expected audit row for an employee/contract record, not only for a comment.

## Actual

- `tests/Feature/PhaseFiveIntegrityTest.php` covers:
  - comment and subtask metadata expiry, with completion remaining available;
  - `override_edit_window` audit for a comment.
- The selected run passed: **2 tests / 17 assertions**.
- `tests/Feature/ContractExtensionTest.php` covers extension creation rules but not the edit window; the three extension-specific tests passed (**3 tests / 22 assertions**).
- No employee edit-window test and no contract edit-window test exist.

## Impact

- The confirmed contract bypass (EW-003) can regress silently.
- The dormant-permission problem (EW-001) is invisible to the test suite because it seeds only super-admin/super-user actors.
- Future changes to the global middleware or route signatures will not be caught.

## Suggested direction (report only; no code changed)

- Add a feature test using a role with `edit_employees` and **no** override: fresh edit succeeds; record older than 60 minutes returns `business.edit_window_expired`.
- Add contract update/cancel tests with an old draft and assert the intended result.
- Add an override test for an employee or contract record and assert the audit payload.
- Keep the existing comment/subtask carve-out tests.

## Evidence

- Test files and selected results above; no source changes made.
