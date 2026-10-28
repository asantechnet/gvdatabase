## Task: Applicant portal user provisioning (manual + automatic on contract creation)

- **Task ID:** applicant-portal-user-provisioning
- **Created:** 2026-10-28
- **Last updated:** 2026-10-28
- **Status:** COMPLETED
- **Outcome:** Staff can create a ready-to-use applicant portal account from the visa case page by setting its email and password, and can delete that account. Creating a built-in service contract (scholarship, test offer, academy, private) offers an option that pre-generates a unique login email and a strong password, shows them with a copy action, and creates the applicant portal user with exactly those credentials once the contract is created. The created account is email-verified by default.

### 1. Requirements and acceptance criteria

- [x] The visa case page lets an authorized staff member create an applicant user with a chosen email and password.
- [x] The created applicant account is email-verified, active, and carries only the `applicant` role, so it can sign in with that password immediately.
- [x] The visa case page lets an authorized staff member delete an applicant's portal account; the case stays intact and can receive a new account later.
- [x] The service contract create form offers an opt-in "create applicant user" option for all four built-in services.
- [x] Turning the option on generates a unique login email plus a strong random password and displays both with a one-click copy of `Email: …` / `Password: …`; it does **not** create anything yet.
- [x] The generated email is the applicant's own email when one exists, otherwise a readable name + education combination with a random suffix that does not collide.
- [x] Saving the contract with the option on creates the portal user with exactly the displayed credentials; saving it off creates nothing.
- [x] The credentials remain visible after the contract is created so the operator can copy them.
- [x] New copy exists in EN/FA/PS and the new UI uses logical flow/theme tokens for RTL/LTR and light/dark support.

### 2. Scope and non-goals

- In scope: `ApplicantPortalController` manual creation + deletion, a credential-suggestion endpoint, a shared account service, contract-creation provisioning, request validation, API contracts, the visa case page, the service contract form, EN/FA/PS translations, focused feature tests, this record.
- Out of scope: changing the existing invitation email flow's default behavior, editing an existing account's password (passwords are hashed and are only known at creation time), portal user management outside the case/contract flows, permissions changes, migrations, notification changes.

### 3. Project findings

- Current behavior: `POST /applicants/{applicant}/portal-invitation` creates an unverified `users` row with `must_set_password = true` and a random unusable password, sends a verification mail, and links `applicants.user_id`. `PUT /applicants/{applicant}/portal-access` only toggles `acc_status`. There is no way to set a password, no delete, and no credential generation.
- `UserController::store` already establishes the "supplied password ⇒ verified, ready-to-use account" rule; the applicant flow should mirror it.
- Popular roles that may create contracts (`staff`, `counselor`, `case_officer`, `manager`) all hold `invite_applicant_portal`, so gating provisioning on it is safe for the UI and the API.
- `applicants.user_id` is a nullable unique FK with `nullOnDelete`; `users` is soft-deleted, so unlinking must be explicit.
- `Setting::company_email` is the available address to borrow a domain from (`CompanyProfile::current()`), with `example.com` as the fallback.
- `StoreServiceContractRequest` is shared by create and update, and already uses `isMethod('post')` to scope create-only rules (`paid_fee`).
- `ContractGenerationService::create()` creates the applicant, then the contract/line; provisioning belongs immediately after the applicant exists and inside the same transaction.
- Relevant paths: `app/Http/Controllers/ApplicantPortalController.php`, `app/Services/Contracts/ContractGenerationService.php`, `app/Http/Requests/StoreServiceContractRequest.php`, `routes/api.php`, `resources/ts/api/applicantPortal.ts`, `resources/ts/api/contracts.ts`, `resources/ts/pages/applicant-case.vue`, `resources/ts/pages/service-contracts.vue`, locales, `tests/Feature/ApplicantPortalTest.php`.

### 4. Graph and impact review

- Method (fallback, no graph skill loaded): repository search plus manual call/data-flow tracing from the static routes (`routes/api.php`) through controllers, services, models, API contracts and pages. `graphify-out` was not used.
- Traced: service contract POST → `StoreServiceContractRequest` → `ContractGenerationService::create` → applicant/service detail → contract line; applicant case page → `ApplicantPortalApi` → portal controller → `users`/`applicants.user_id`; role seeder grants → UI ability checks.
- Downstream effects: contract creation now has one extra transactional side effect; the applicant case header gains a destructive action; the invitation response gains an optional field; tests asserting invitation behavior must keep passing unchanged.
- Limitations: no browser run; verification is by feature tests, typecheck and i18n check.

### 5. Clarifications, decisions, and assumptions

- Open questions: none blocking.
- Decisions:
  - Reuse the existing invitation endpoint for manual creation with an optional `password` (supplied ⇒ verified, no invitation mail), instead of adding a second creation endpoint.
  - Automatic provisioning is limited to newly created applicants (no extension/edit form), matching "applicants are created automatically by system upon contract creation".
  - Credential generation is a server endpoint so uniqueness is checked against `users` (including soft-deleted rows).
- Assumptions:
  - "Delete them" means delete the portal user account, revoking its tokens and unlinking the case, not the case itself. Soft delete, consistent with `UserController::destroy`.
  - "Combination of its name and education" uses the service's academic selection (education level, academy program, or test) with a 4-digit suffix.
  - The domain is borrowed from `settings.company_email`, falling back to `example.com`.
  - Programming identifiers, API names and code stay English; Persian/Afghan Persian and Pashto copy is added for the UI.

### 6. Implementation plan

- [x] Step 1 — Add `App\Services\ApplicantPortalAccountService` (suggest credentials, create account, delete account).
- [x] Step 2 — Extend `ApplicantPortalController` with credential preview, password-aware invite and user deletion; register routes in `routes/api.php`.
- [x] Step 3 — provision the account in `ContractGenerationService::create` and accept/validate the three new fields in `StoreServiceContractRequest`.
- [x] Step 4 — Extend `resources/ts/api/applicantPortal.ts` and `resources/ts/api/contracts.ts`.
- [x] Step 5 — Add the password field and delete action to `applicant-case.vue`.
- [x] Step 6 — Add the opt-in switch, credential display/copy and post-create credentials dialog to `service-contracts.vue`.
- [x] Step 7 — Add EN/FA/PS translations.
- [x] Step 8 — Add focused feature tests; run them, `npm run typecheck`, `npm run i18n:check`, rebuild `public/build`.

### 7. Validation plan and results

- [x] `php artisan test tests/Feature/ApplicantPortalAccountTest.php tests/Feature/ApplicantPortalTest.php` — 11 passed, 78 assertions.
- [x] `php artisan test tests/Feature/BuiltInContractGenerationTest.php tests/Feature/ContractExtensionTest.php tests/Feature/VersionedContractGenerationTest.php` — 28 passed, 300 assertions.
- [x] `php artisan test tests/Feature/VisitorProfileAndServiceContractFieldSetsTest.php tests/Feature/ServiceContractNavigationTest.php tests/Feature/ApplicantCaseViewTest.php tests/Feature/RoleBasedAuthorizationTest.php tests/Feature/ApplicantWorkflowTest.php` — 16 passed, 133 assertions.
- [x] `npm run typecheck` — clean.
- [x] `npm run i18n:check` — no missing keys from this change. The audit still reports the pre-existing `pages.contracts.allContracts.allContracts.failedToFetchContractsPleaseTryAgain`, which is absent from `HEAD` too and untouched here.
- [x] `npm run build:icons` — `tabler-user-minus` now bundled (generated CSS is untracked).
- [x] `npm run build` — production bundle, `public/sw.js` and `public/version.json` regenerated and committed.
- Results: all of the above passed after the final edits; the built bundle includes the changed pages.
- Checks not run and why: no browser run. The dialog layouts were verified by typecheck and feature tests, not by visual inspection.

### 8. Risks and mitigations

- Generated email taken between preview and save — mitigation: unique validation at create time returns a clear 422 instead of a silent mismatch; the operator can regenerate.
- Losing a generated password — mitigation: credentials stay in a confirmation dialog after the contract is created, with the copy action.
- Deleting an account with live sessions — mitigation: delete tokens inside the same transaction and unlink before deleting.
- Extra side effect inside contract creation could abort a sale — mitigation: provisioning runs last, uses the already-validated credentials, and only when explicitly requested.

### 9. Progress log

- 2026-10-28 — DISCOVERY: reviewed portal controller/routes/model, contract generation request/service, service contract form, visa case page, role grants, locale structure and existing portal tests.
- 2026-10-28 — PLAN: plan finalized and saved before implementation.
- 2026-10-28 — PROGRESS: added the shared account service, credential/deletion endpoints, contract-creation provisioning and request rules; extended both API contracts and both pages; added EN/FA/PS copy and six focused feature tests.
- 2026-10-28 — VALIDATION: portal, contract generation, extension, versioning, field-set, navigation, case-view, authorization and workflow suites passed; typecheck and i18n check clean; icons and production bundle rebuilt.
- 2026-10-28 — DEVIATION: the invite endpoint answers 422 with a new `business.applicant_portal_account_*` code instead of the generic `business.request_rejected`, and the case page no longer shows a generic failure toast (the shared notifier carries the server message).

### 10. Completion summary

- Delivered: `ApplicantPortalAccountService`; `POST /applicants/portal-credentials`, an optional password on `POST /applicants/{applicant}/portal-invitation` and `DELETE /applicants/{applicant}/portal-user`; contract-creation provisioning for all four built-in services through `create_portal_user` / `portal_user_email` / `portal_user_password`; the visa-case password field and delete action; the contract form switch with credential display, copy and a post-create reminder dialog; EN/FA/PS copy.
- Acceptance criteria satisfied: all nine listed above.
- Tests/checks passed: 55 feature tests across 10 files (511 assertions), `npm run typecheck`, `npm run i18n:check`, `npm run build:icons`, `npm run build`.
- Remaining limitations or follow-up work: deleted portal accounts are soft-deleted, so their email stays taken (the credential suggestion skips such addresses, but re-creating with the exact same address is rejected) — consistent with `UserController::destroy`. Automatic provisioning is limited to newly created applicants; extensions and edits never touch the account. No browser/visual pass was performed.
