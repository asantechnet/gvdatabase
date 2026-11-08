# Progress — Task-009

## Status snapshot
- Status: `Review`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-10-31`
- Current focus: `Backend 2FA/recovery/session contract and the security/login UI are implemented; focused tests and typecheck pass.`
- Blocker: `None`
- Next action: `Reviewer confirms the new recovery-code table and the session UI, then runs a disposable-account authenticator walkthrough.`

## Acceptance criteria progress
- [x] Given a user starts 2FA setup, when the security page receives the response, then it shows a scannable QR code for the otpauth URI, the issuer is the company/product name from settings (or the app-name fallback), and the manual secret remains available. — Evidence: `beginTwoFactor` now resolves `Setting.short_company_name`, then `website_title`, then `config('app.name')` and returns `issuer` with the `otpauth_url`; `test_begin_uses_the_company_issuer_and_never_exposes_the_secret_in_account_data` asserts `Acme Agency` and the encoded issuer. `AccountSettingsSecurity.vue` renders `QrcodeVue` from `otpauth_url` and keeps the secret. The QR render itself was not captured in a browser.
- [x] Given the user confirms a valid TOTP code, when confirmation succeeds, then ten recovery codes are shown once, only hashes are stored, and the raw codes cannot be retrieved again. — Evidence: `test_confirm_returns_ten_recovery_codes_and_stores_only_hashes` asserts 10 unique codes, 10 rows, no stored value equals a raw code, and a bcrypt hash present; the confirm response returns codes only in that payload and they are not serialized by `/auth/me`.
- [x] Given a stored recovery code, when it is used at login, then it authenticates once and is rejected on a second use, and the normal six-digit TOTP path still works. — Evidence: `test_a_recovery_code_authenticates_once_and_the_totp_path_still_works`: first login with `recovery_code` returns 200, the same code returns 422 `two_factor_required`, a valid TOTP still returns 200, and an unknown code is rejected. Consumption uses a `whereNull('used_at')` update and only runs when TOTP failed.
- [x] Given the security page, when it loads, then it lists active sessions with device/user-agent, created time, last used time and a current-session marker, and can revoke one session or all other sessions. — Evidence: `GET /auth/account/sessions` returns id/type/device/created_at/last_used_at/expires_at/current scoped to the caller; `test_sessions_are_scoped_to_the_owner_and_revoking_another_users_token_is_refused` asserts device and current marker and a successful revoke. The security page renders the list with a "This device" chip and per-row Revoke, keeping the existing sign-out-others action. UI was not exercised in a browser.
- [x] Given a session revoke request for another user's token, when it is submitted, then it is refused and the token remains active. — Evidence: same test: foreign token id returns 404 and the row still exists in `personal_access_tokens`.
- [x] Given the password form, when it renders, then the minimum length and confirmation requirement are stated, server validation appears inline, and a successful change still signs out other sessions. — Evidence: the security page now states "Use at least 8 characters. Enter the same password in both fields." and binds `current_password`, `password` and `password_confirmation` errors from `apiFieldErrors`; the existing server `min:8|confirmed` rule and `tokens()->whereKeyNot(current)` sign-out are unchanged.
- [x] Given the demo super-admin seed, when the database is seeded, then the password meets the 8-character minimum and is documented as demo-only; production validation is not relaxed. — Evidence: `SuperAdminSeeder::DEMO_PASSWORD` changed from `1234` to `Demo1234` with a demo-only docblock. No production rule changed. The local DB was not re-seeded (no mutation of the existing demo account).
- [x] Given 2FA is confirmed, when account data is fetched, then no TOTP secret or recovery-code hash is returned, and no secret appears in logs. — Evidence: `accountData` only exposes `two_factor_enabled`; test asserts `/auth/me` has no `two_factor_secret`/recovery keys. No logging was added anywhere in the flow.
- [x] Given the login form, when inspected, then the email and password fields each have one accessible name and the password visibility control is named, with no duplicate Email Email or Password Password label. — Evidence: Playwright snapshot of `/login` after the change reports `textbox "Email"`, `textbox "Password"`, `button "Show password"`. Explicit `aria-label`s plus a real slotted toggle button removed the duplicate names.

## Work completed with evidence
- Additive `two_factor_recovery_codes` migration (user FK cascade, hashed code, `used_at`, index).
- `AuthController`: settings-based issuer, ten one-time recovery codes on confirmation, `recovery_code` login path, hashed single-use consumption, session list/revoke, device capture in the Sanctum token name, and refresh-name prefix matching.
- `routes/api.php`: `GET /auth/account/sessions` and `DELETE /auth/account/sessions/{token}` under Sanctum auth.
- `resources/ts/api/auth.ts`, `AccountSettingsSecurity.vue`, `LoginForm.vue`, `pages/login.vue`: typed contract, QR + recovery-code dialog, session list/revoke, password policy and inline errors, recovery fallback, accessible labels.
- `database/seeders/SuperAdminSeeder.php`: demo password raised to the production minimum.
- `package.json` / lockfile: `qrcode.vue` (maintained client-side QR renderer; no secret leaves the browser).
- `tests/Feature/TwoFactorAuthenticationTest.php`: issuer, one-time codes, TOTP regression, session ownership.

## Files changed and why
- `app/Http/Controllers/AuthController.php` — issuer, recovery codes, login verification, sessions.
- `routes/api.php` — session endpoints.
- `database/migrations/2026_11_01_000002_add_two_factor_recovery_codes.php` — new table.
- `database/seeders/SuperAdminSeeder.php` — demo credential policy.
- `resources/ts/api/auth.ts` — types and endpoints.
- `resources/ts/views/pages/account-settings/AccountSettingsSecurity.vue` — QR, recovery dialog, sessions, policy.
- `resources/ts/components/forms/LoginForm.vue` — recovery fallback and accessible names.
- `resources/ts/pages/login.vue` — **ownership exception**: directly required to send `recovery_code`; the login page was not in the task's file list but the acceptance criterion cannot be met without it.
- `tests/Feature/TwoFactorAuthenticationTest.php` — **not in the ownership list**; added because the verification plan requires feature tests and no test path was assigned.
- `package.json`, `pnpm-lock.yaml` — QR dependency.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php artisan test --filter=TwoFactorAuthenticationTest` | Local MySQL test DB | PASS — 4 tests, 44 assertions, exit 0 | `/tmp/task009-test.log` | First run exposed test-only JSON-path and settings-fixture mistakes; fixed, then green |
| 2026-10-31 | `npm run typecheck` | Local | PASS — exit 0 | `/tmp/task009-typecheck.log` | Run after the LoginForm slot change as well |
| 2026-10-31 | Playwright `/login` accessibility snapshot | Local app + Vite | PASS | Snapshot output | `Email`, `Password`, `Show password`; no duplicate names |
| 2026-10-31 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | Local | PASS | Task set validator | Status/index synchronized |

## Decisions and deviations
- QR rendering uses `qrcode.vue`; the otpauth URI never leaves the browser. No third-party image service is used.
- Recovery codes live in a dedicated table, written through `DB` inside `AuthController` (no extra model file, respecting exclusive ownership).
- Sanctum has no device column, so the user agent is stored as a suffix after `|` in the token name; refresh detection now matches the name prefix. Existing tokens without a suffix still work and simply have no device.
- The issuer prefers `short_company_name`, then `website_title`, then `config('app.name')`.
- Two ownership exceptions were necessary and are recorded above (`login.vue`, the test file).

## Blockers and open questions
- QR rendering, the recovery-code dialog and the sessions card were not exercised in a browser; they are typechecked and their API contract is feature-tested.
- The demo password change will take effect on the next `db:seed`; the existing local demo account still has the old password.
- Existing sessions created before this change show no device label (the old token names have no suffix).

## Handoff notes
- Run `php artisan migrate` in the target environment before deploying the UI; the migration is additive.
- A real authenticator walkthrough should use a disposable account, as the task requires.
- `GET /auth/account/sessions` returns both access and refresh rows; the UI labels the type.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issue 09; implementation has not started.
- `2026-10-31` Claimed by `Buffy (erp-task-implementer)`.
- `2026-10-31` Implemented migration, issuer, recovery codes, login fallback, session endpoints, security/login UI, demo password and feature tests. Test run: 4 passed (44 assertions); `npm run typecheck` exit 0; Playwright login snapshot shows single accessible names. Status → `Review`.
- `2026-11-07` Narrowed exclusive ownership from the whole seeders directory to `database/seeders/SuperAdminSeeder.php` so the Academy tasks can own their new seeders without an overlap; no code change.
