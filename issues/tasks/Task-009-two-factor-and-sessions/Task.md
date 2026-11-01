# Task Task-009 — Harden two-factor enrolment and expose active sessions

## Metadata
- Task ID: `Task-009`
- Title: `Harden two-factor enrolment and expose active sessions`
- Initial status: `Ready`
- Type: `Security`
- Priority: `P2`
- Owner role: `Laravel security implementer with Vue UI support`
- Estimated size: `L`
- Source plan/version: `issues/review/issues/09-2fa-and-login-security/issue.md`; `issues/review/REPORT.md` §4 P2-8 Security; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-09`

## Objective
A super administrator can enrol an authenticator with a QR code and a recognizable issuer, recover access with one-time codes, see active sessions and revoke them, and read the password policy before submitting.

## Context and problem
`AuthController::beginTwoFactor()` generates a 32-character secret and returns a raw `otpauth://` URL with `issuer = config('app.name')` (default "Laravel"). The security page renders the secret in a code block and a bare link; there is no QR image, no recovery codes and no lost-device guidance. The sessions card only signs out all other devices and cannot list them. The password form enforces `min:8|confirmed` server-side but states no rules.

The review also reported a seeded 4-character super-admin password. The demo seeder path was not found in `database/seeders`, so the fix is a seed/credential policy decision recorded and implemented in the seed path that actually creates the demo account.

## Scope
### Included
- Render a QR code from the returned `otpauth` URI in the security page, in addition to the manual secret.
- Use the company/product name from settings as the TOTP issuer, with the app name only as a fallback; keep the account label as the user's email.
- Generate ten one-time recovery codes when 2FA is confirmed, show them once, store only hashes, and consume a code exactly once at login.
- Add a recovery-code fallback on the login 2FA prompt.
- Add `GET /auth/account/sessions` and `DELETE /auth/account/sessions/{token}` (or an equivalent token id) to list active access/refresh tokens and revoke one, keeping the existing sign-out-others action.
- Show device/user-agent, created time, last used time and current-session marker in the security page.
- State the password policy (minimum 8, confirmation) and show server validation inline.
- Ensure the demo super-admin password meets the minimum and is documented as demo-only; never weaken production rules to match a seed.
- Do not return the TOTP secret after confirmation and do not log it.

### Excluded
- Changing the TOTP algorithm, period or digit count.
- SSO, WebAuthn, email OTP or SMS.
- Permission model changes.
- A full device-management policy (naming devices, remote wipe) beyond listing and revoking tokens.
- Any change to the session/refresh-token lifetime unless required for the listing.

## Preconditions and assumptions
- `personal_access_tokens` already stores access and refresh tokens with names and timestamps; the sessions list reads that table through the authenticated user's tokens.
- Assumption to validate: Sanctum's current access token is available as `currentAccessToken()` and can be identified safely. If not, mark the session by token id in the response and compare on the client.
- Recovery codes are a new additive table or JSON column; choose one and document it. The task assumes a dedicated table for hashed codes and one-time consumption.
- A QR encoder dependency is acceptable only if no existing QR utility is present; prefer a small, maintained client-side package and record the choice.

## Dependencies
- `None` — this task is independent of the reporting and table tasks.

## Contracts and boundaries
- Input/output: `POST /auth/account/two-factor` returns `secret`, `otpauth_url` and a QR payload/URL; confirm returns `recovery_codes` once. Login accepts `two_factor_code` or `recovery_code`.
- Compatibility guarantee: existing `two_factor_code` payloads keep working; accounts with 2FA already enabled are not locked out.
- Error behavior: an invalid or already-used recovery code returns a generic 422 without revealing whether the code existed; a revoked session request returns 404/403 without leaking another user's token.
- Permission/data scope: a user may list and revoke only their own tokens; super admins do not get a cross-user session list in this task.
- Integration seam: recovery-code verification and TOTP verification share one authentication path in `AuthController`; the UI never sees the stored hashes.
- Files this task must not modify: `app/Http/Controllers/UserController.php`, `resources/ts/pages/setup/users.vue`, `resources/ts/utils/localization.ts`.

## Exclusive file ownership
- `app/Http/Controllers/AuthController.php` — issuer, QR payload, recovery codes, session list/revoke and login verification.
- `resources/ts/views/pages/account-settings/AccountSettingsSecurity.vue` — QR, recovery codes, sessions and password guidance.
- `resources/ts/components/forms/LoginForm.vue` — recovery-code fallback and accessible password toggle.
- `resources/ts/api/auth.ts` — two-factor, recovery-code and session types.
- `routes/api.php` — the new session routes and their order.
- `database/migrations/2026_11_01_000002_add_two_factor_recovery_codes.php` — hashed recovery codes.
- `database/seeders` — demo credential policy and seeded super-admin password.
- `package.json` — QR encoder dependency, only if no existing utility is found.

## Acceptance criteria
- [ ] Given a user starts 2FA setup, when the security page receives the response, then it shows a scannable QR code for the otpauth URI, the issuer is the company/product name from settings (or the app-name fallback), and the manual secret remains available.
- [ ] Given the user confirms a valid TOTP code, when confirmation succeeds, then ten recovery codes are shown once, only hashes are stored, and the raw codes cannot be retrieved again.
- [ ] Given a stored recovery code, when it is used at login, then it authenticates once and is rejected on a second use, and the normal six-digit TOTP path still works.
- [ ] Given the security page, when it loads, then it lists active sessions with device/user-agent, created time, last used time and a current-session marker, and can revoke one session or all other sessions.
- [ ] Given a session revoke request for another user's token, when it is submitted, then it is refused and the token remains active.
- [ ] Given the password form, when it renders, then the minimum length and confirmation requirement are stated, server validation appears inline, and a successful change still signs out other sessions.
- [ ] Given the demo super-admin seed, when the database is seeded, then the password meets the 8-character minimum and is documented as demo-only; production validation is not relaxed.
- [ ] Given 2FA is confirmed, when account data is fetched, then no TOTP secret or recovery-code hash is returned, and no secret appears in logs.
- [ ] Given the login form, when inspected, then the email and password fields each have one accessible name and the password visibility control is named, with no duplicate Email Email or Password Password label.

## Verification plan
- Automated: feature tests for begin/confirm, recovery-code one-time use, invalid code, session ownership and revoke; run the focused auth tests and `npm run typecheck`.
- Manual: use a disposable non-production account with a real authenticator app; scan the QR, confirm, save recovery codes, sign out, sign in with a recovery code, reuse it and confirm rejection. Revoke one session and verify it fails on refresh.
- Fixtures: two users, two tokens each, one confirmed 2FA account. Never enable 2FA on the real admin during the test.
- Regression: confirm normal login, logout, refresh-token rotation, password change and sign-out-other-devices still work; confirm no secret is serialized by `GET /auth/account`.
- Evidence: QR screenshot with the secret redacted, recovery-code list redacted after capture, test output, session list screenshot, revoked-token response.

## Implementation notes
Prefer a small local QR component or a maintained client-side encoder; do not send the secret to a third-party image service. Keep recovery codes out of the `users` table if a dedicated table gives cleaner one-time updates. Use `Hash::make`/`Hash::check` and a transaction that marks the code used before issuing tokens. For the issuer, read the company short name/website title from settings with a cached lookup, falling back to `config('app.name')`.

## Risks and rollback
- Risk: a lost recovery-code table or migration failure locks users out. Mitigation: make the migration additive, deploy before enabling the UI, and keep TOTP as the primary path.
- Risk: session listing exposes token metadata. Mitigation: return only id, name, timestamps and current marker; never the token hash.
- Risk: demo password change breaks the reviewer's login. Mitigation: document the new demo credential in the seed notes and keep it environment-only.
- Data migration: new recovery-code table; no change to existing users. Rollback drops the table and the UI path returns to TOTP-only, with a recorded limitation.
- Stop condition: if a QR dependency is not acceptable, stop and get a decision rather than embedding a third-party QR image URL.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
