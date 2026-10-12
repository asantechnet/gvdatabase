# Support Contacts, Settings Tab, Manual Accounts & Payment-Request Types — Plan & Progress

Six-comment batch from the requester. Everything below is implemented and verified
(typecheck, i18n audit + runtime, icon bundle, Pint, full PHPUnit, browser regressions).

---

## 0. Requested changes (verbatim intent)

1. **Support page** — add a developer-contact links section: phone `+935453435234`,
   address `Kabul Afghanistan`, email `blue@gm.com`, with `tel:`, WhatsApp, Telegram and
   `mailto:` links plus phone/WhatsApp/telegram/email labels. These are the **developer's**
   (platform team's) details, *not* the company/office profile, so they are not editable here.
2. **Support** — remove it from the profile menu and move it into a dedicated sub-page
   (tab) inside Settings.
3. **Users** — allow manually entering a user's password for an account and **completely
   omit** sending the email to the user's address.
4. Rename the global **Applicants** navigation group to **Applications**.
5. **Applications page** — remove the call details from the applicant-name column.
6. **Pending requests** — remove **Salary** from the type options (salary payments are not
   needed there).

## 1. Decisions

| Topic | Decision |
|---|---|
| Where the developer contacts live | Hardcoded in `resources/ts/utils/constants.ts` as `SUPPORT_CONTACT` (`as const`), with no import and no API call, so it can never be confused with the office profile. The card carries an info alert saying these belong to the platform team and are not editable here. The values are rendered `dir="ltr"` so they stay readable in RTL locales; only the WhatsApp/Telegram tiles open a new tab (`target="_blank"` + `rel="noopener noreferrer"`). |
| Support placement | The `/support` route is **kept** so existing deep links keep working, but the profile-menu entry is gone. Settings owns a Support tab, and the page receives `embedded` from the shell so the standalone `data-page-title` block is not duplicated inside the Settings layout. |
| Manual password accounts | A supplied password is hashed, `must_set_password` is cleared and `email_verified_at` is stamped — otherwise the login flow would block the account forever (`AuthController` rejects unverified addresses) — and **no** verification notification is sent. The users table therefore reads “Verified” for these accounts, which is intentional. Without a password nothing changes: invitation email + user-chosen password. |
| Password on edit | Edits never accept a password. The field has **no validation rule at all** on update rather than an empty rule list, because Laravel still returns a value in `validated()` for a key that has an (empty) rule — which would have mass-assigned a raw password. A feature test pins this. |
| Salary in payment requests | Removed from creation and editing (UI list + API `in:` validation), but the legacy `'salary'` post branch and its employee guard are kept so rows created before this change still post and keep their payroll link. `PendingTransactionType` keeps `'salary'` for reading old rows only. |
| Expense payee | Because salary is gone, the employee select no longer drives a required field. It stays for **expense** (optional, `educational.employeeOptional`) since the API already supported a payee employee; picking any other type clears it. |
| Applicants → Applications nav group | Only the global group label changes (`common.navigation.applicants` → `common.navigation.applications`); its first child becomes `educational.allApplications` so the group and the row read “Applications” / “All applications”. CRM's own “Applicants” group and the CRM breadcrumb/search copy are intentionally untouched. |

## 2. Workstreams

### S1 — Support centre: developer contact section (comments 1, 2)

| Surface | Change |
|---|---|
| `resources/ts/utils/constants.ts` | New exported `SUPPORT_CONTACT` (`phone`/`phoneHref`, `whatsapp`/`whatsappHref`, `telegram`/`telegramHref`, `email`/`emailHref`, `address`) documented as platform-team data that must stay separate from the office profile. |
| `resources/ts/pages/support.vue` | New `embedded?: boolean` prop (default `false`); `contactChannels` computed (phone `tabler-phone`/primary, WhatsApp `tabler-brand-whatsapp`/success, Telegram `tabler-brand-telegram`/info, email `tabler-mail`/warning); a full-width `support-contact-card` with a `tabler-headset` avatar, the developer-ownership alert, a responsive `support-contact-grid` of tiles plus an address row (`tabler-map-pin`); the standalone title block is hidden when embedded. Scoped CSS uses logical properties (`margin-inline-start`, `min-inline-size`) and theme tokens only, so it holds in RTL/LTR and light/dark. |
| `resources/ts/pages/account-settings.vue` | New `support` tab (`tabler-lifebuoy`) after Work history, rendering `SupportPage` with `embedded: true`. |
| `resources/ts/layouts/components/UserProfile.vue` | Support nav item removed. |
| i18n `en` / `fa-AF` / `ps-AF` | New `pages.support.contact` block (`title`, `subtitle`, `developerNotice`, `phone`, `whatsapp`, `telegram`, `email`, `address`). |

### S2 — Manual-password user provisioning (comment 3)

| Surface | Change |
|---|---|
| `app/Http/Controllers/UserController.php` | `store()` accepts an optional `password`: present → `Hash::make`, `must_set_password = false`, `email_verified_at = now()`, no `sendEmailVerificationNotification()`; absent → the unchanged invitation flow. `validated()` only declares the `password` rules (`sometimes`,`nullable`,`string`,`min:8`,`confirmed`) for **creation**, so an edit can never touch a stored credential. |
| `resources/ts/api/user.ts` | New `UserCreatePayload extends UserPayload` with optional `password`/`password_confirmation`; `createUser()` takes it. |
| `resources/ts/components/forms/UserForm.vue` | New `passwordMode` prop; password + confirmation fields with length/match rules; submit label switches between `saveUser` / `createUser` / `sendInvitation`. |
| `resources/ts/pages/setup/users.vue` | Add-user dialog gains a “set the password manually and send no email” switch; the alert swaps from info (invitation) to warning (no email) and the credentials section appears; client-side checks run before the request; success toast distinguishes “created without sending an email”. Create labels became `addUser`/`addUserDialog`. |
| i18n `en` / `fa-AF` / `ps-AF` | New `pages.settings.users.*` keys (manual password, no-email notice, password section, password/confirm, too-short, mismatch, required, create user, created-without-email); `inviteUser`/`inviteUserDialog` replaced by `addUser`/`addUserDialog`. |
| `tests/Feature/UserProvisioningTest.php` | **New.** Manual password creates a verified, ready account with nothing sent; no password keeps the invitation + `VerifyEmail`; missing/short/mismatched confirmation fails; editing never rewrites the stored password. |

### S3 — Applications naming (comment 4)

| Surface | Change |
|---|---|
| `resources/ts/navigation/horizontal/applicants.ts` | Group title → `common.navigation.applications`; first child → `educational.allApplications`. |

### S4 — Applications name column (comment 5)

| Surface | Change |
|---|---|
| `resources/ts/pages/applicants.vue` | `ContactActionMenu` and its `?contact=` deep-link handler removed; the name is a plain CRM `RouterLink` again, so the column carries no call details. |

### S5 — Payment requests: no salary (comment 6)

| Surface | Change |
|---|---|
| `resources/ts/pages/pending-transactions.vue` | Type list is `income`/`expense`; `needsEmployee`/watcher replaced by `showsEmployeeField` (expense only, optional, `educational.employeeOptional`); save button no longer requires an employee. |
| `resources/ts/api/pendingTransactions.ts` | `PendingTransactionType` documented as keeping `'salary'` for reading legacy rows only. |
| `app/Http/Controllers/PendingTransactionController.php` | `transaction_type` validates `in:income,expense`; the legacy salary post branch and employee guard are kept and commented; the “income and salary are exempt from the contract rule” comment narrowed to income. |
| `app/Services/LoanReceiptService.php` | Comments updated: voucher layout is for “any other direction (an expense, or a legacy salary request)”; the payee block is described by employee/applicant rather than by salary. |
| i18n | `educational.pendingUi.employeeRequired` removed from all three locales (the rule it described no longer exists). |
| `tests/Feature/PendingTransactionTest.php` | New test: `salary` is rejected as a payment-request type. |

## 3. Progress tracker

| # | Item | Status |
|---|---|---|
| 1 | Support developer-contact section (links + labels + address) | ✅ |
| 2 | Support out of the profile menu, into a Settings tab | ✅ |
| 3 | Manual password user creation with no email | ✅ |
| 4 | Applicants nav group renamed to Applications | ✅ |
| 5 | Applications name column without call details | ✅ |
| 6 | Salary removed from payment-request types | ✅ |

## 4. Progress log

| Step | Result |
|---|---|
| `npm run typecheck` | Pass (`vue-tsc --noEmit`). |
| `npm run i18n:check` | 273 source files, 3047 used keys, 4410 English keys; no missing keys, no untranslated strings, no direct date formatting, no physical-direction CSS; 1368 unused keys reported (informational). |
| `npm run i18n:test` | Runtime switching + schema pass for `en`, `fa-AF`, `ps-AF` (4410 keys). |
| `npm run build:icons` | 825 files scanned, 294 icon references, tabler 292 / mdi 2 bundled, **no unresolved-token warnings** (the new `tabler-brand-whatsapp`, `tabler-brand-telegram`, `tabler-headset` tokens are bundled). |
| `vendor/bin/pint` on the touched PHP files | Clean (also normalised two pre-existing brace/style issues in the touched controllers). |
| `php artisan test` (`DB_CONNECTION=sqlite DB_DATABASE=":memory:"`) | **368 passed** (3474 assertions). |
| `node scripts/support-contact-regression.mjs` | Pass — real `support.vue` mounted in Chrome for `en`, `fa-AF` and embedded mode: the four channel links resolve to `tel:`, `wa.me`, `t.me`, `mailto:` with i18n labels and `dir="ltr"` values, only the chat links are `_blank`+safe, the address row and ownership notice render, the standalone title only appears outside Settings, the grid stacks without overflow on mobile, and the tile border stays visible in dark mode. Also asserts the Support/profile/settings, salary and manual-password wiring. |
| `node scripts/applications-tabs-regression.mjs` | Pass (stage tabs unchanged, `?contact=` removal broke nothing). |
| `node scripts/case-stage-move-regression.mjs` | Pass. |
| `node scripts/loan-receipt-print-regression.mjs` | Pass (income receipt vs. expense voucher layout unchanged). |

## 5. Known follow-ups (not part of this batch)

- Accounts created with a manual password show the **Verified** chip in the users table even
  though no address was ever confirmed. That is deliberate (an unverified address cannot sign
  in), but a dedicated “Set manually” verification label may be a better read.
- The developer contacts are hardcoded by design. If they ever need to change without a
  deploy, they should move into a platform-level settings row rather than the office profile.
- `SUPPORT_CONTACT.telegramHref` uses the `https://t.me/+<number>` form; if the platform team
  later has a public username, that href is the one value to swap.
