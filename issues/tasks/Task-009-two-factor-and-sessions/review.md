# Review — Task-009 two-factor and sessions

- **Reviewed:** 2026-11-04
- **Verdict:** Needs a test-contract correction — runtime paths reviewed, auth suite is red
- **Method:** `php artisan test tests/Feature/AuthFlowTest.php` and `tests/Feature/TwoFactorAuthenticationTest.php`; source and progress inspection.
- **Scope:** review only. No code was changed.

## Finding — `AuthFlowTest` was not updated for device-labelled refresh tokens

**Severity:** Medium (CI/test contract, not a runtime failure observed in the browser)

**Expected:** The auth test suite remains green after the task’s session work.

**Actual:** `AuthFlowTest::test_remembered_login_issues_separate_access_and_refresh_tokens` fails:

```text
Failed asserting that two strings are identical.
-'refresh-token-remembered'
+'refresh-token-remembered|Browser...'
```

Task-009’s progress records that the user agent is deliberately stored as a suffix after `|` in the Sanctum token name, and `AuthController` creates the refresh token with `refresh-token-remembered|{device}`. The old assertion still expects the exact un-suffixed name. Token abilities and the `/auth/me` rejection assertion pass; only the name assertion is stale.

**Evidence:** `tests/Feature/AuthFlowTest.php:75`; `app/Http/Controllers/AuthController.php:484`; Task-009 `Progress.md` (“device capture in the Sanctum token name”).

**Suggested fix:** Update the assertion to the new contract (for example `assertStringStartsWith('refresh-token-remembered|', $refresh->name)`), and add/keep coverage for the refresh-name prefix check. Re-run the full auth suite, not only `TwoFactorAuthenticationTest`.

## Notes

- `TwoFactorAuthenticationTest` passed (4 tests / 44 assertions) in this review.
- This failure is separate from the 2FA screens and was not caused by the dashboard/case work.
