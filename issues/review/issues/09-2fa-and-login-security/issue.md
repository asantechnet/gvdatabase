# ISSUE-09 — 2FA setup is manual and has no recovery path; seeded password is weak

- **Severity:** P2 (security hardening)
- **Category:** Security
- **Page:** `/account-settings/security`, `/login`
- **Persona:** Super administrator
- **Confidence:** High for the UI behavior; 2FA not enabled

## Expectation

An ERP super-admin account should be able to enrol an authenticator with a QR code and a product issuer name, receive recovery/backup codes, and recover access if the device is lost. The credential policy should be enforced at login.

## Observed

- Clicking "Enable Two Factor Authentication" immediately generated and displayed:
  - a raw 32-character secret in a code block,
  - a link with `issuer=Laravel` (not the product/company),
  - no QR code,
  - an "Authenticator Code" field and confirm button,
  - **no recovery codes** and no "what if I lose my device" guidance.
  I did not confirm/enable 2FA, so the account was not changed. (Screenshot withheld because it contains the secret.)
- The seed uses `admin@example.test` / **`1234`**, below the app's own `min:8` password rule. This is documented as demo-only, but it is the live super-admin credential in this environment.
- Password change backend enforces `min:8|confirmed`, but the form states no rules and has no strength feedback.
- The Security page lists no active sessions/devices, only a "sign out other devices" field.
- Logout itself works correctly: `/login` after logout, Back does not show the previous page, and a deep link to `/setup/users` redirects to `/login`.

## Impact

QR-less manual entry is error-prone; no recovery codes means a lost phone can lock the only super admin out; `Laravel` issuer makes the entry hard to identify among other accounts.

## Evidence

- Snapshot text captured live (secret withheld).
- `review/evidence/my-account.png` (account context).
- Source: `app/Http/Controllers/AuthController.php:306` (password change rules).

## Recommendation

Show a QR code; set issuer to the product name; provide one-time recovery codes; surface the password policy on the form; add a session/device list; require a strong password for the super admin in non-demo environments.
