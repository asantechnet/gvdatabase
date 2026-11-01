# ISSUE-10 — Super-admin account is missing from Users; "Email" column is actually verification state

- **Severity:** P2 (account administration)
- **Category:** Security / UI
- **Page:** `/setup/users`
- **Persona:** Super administrator
- **Confidence:** High — UI and MySQL compared

## Expectation

User management lists all accounts, including the current one, with self-protection (no self-delete) and a clearly labelled verification state. A super admin must be able to find and reset their own account.

## Observed

- List footer: **"1–7 of 7"**. Database: **8 users**. The absent user is exactly the super administrator:
  - id 1, Demo Admin, `admin@example.test`, `super_admin`, active.
- No search/filter reveals the missing account.
- The header **"Email"** covers cells that contain **"Verified"** (email confirmation state). The actual email is already displayed under the user name, so the column is mislabelled and redundant.
- No "last login", "last active" or invitation state, although the page promises "current health of user accounts".
- Delete user actions are offered on every listed row with no visible self/role protection in the table.

## Impact

The only super admin cannot manage their own account from the UI and cannot see who holds the highest role. The mislabelled column makes verification state look like an email value. An administrator may not realise the super-admin account exists at all.

## Evidence

- `review/evidence/users.png`
- Query:
```
SELECT u.id,u.name,u.email,GROUP_CONCAT(r.name) roles
FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id
LEFT JOIN roles r ON r.id=ur.role_id GROUP BY u.id;
```
- Result: 8 users, id 1 role `super_admin`; UI shows 7 rows and no admin.

## Verification

1. Open `/setup/users` and count rows against `users`.
2. Try to locate the super admin by search.
3. Confirm the "Email" column content is a verification state, not an address.
