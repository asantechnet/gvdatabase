## Task: Compact service-contract forms and portal controls

- **Task ID:** compact-service-contract-forms-and-portal-controls
- **Created:** 2026-10-14
- **Last updated:** 2026-10-14
- **Status:** PARTIALLY COMPLETED
- **Outcome:** Produce a project-backed, implementation-ready plan for compact service-contract creation, restricted staff editing, admission-only contract notifications, and staff-controlled applicant portal access. This task is planning-only; no application code will be changed.

### 1. Requirements and acceptance criteria

- [x] Scholarship, test-offer, academy, and private contract create dialogs use one compact responsive grid with safe overflow fallback, RTL-safe logical CSS, and theme-token colors. Browser viewport validation remains environment-blocked.
- [x] The visitor selector is narrower on wider viewports without harming responsive layouts.
- [x] University and university-fee inputs are removed only from service-contract creation; the visa-case multi-university application workflow remains intact.
- [x] Both optional-details accordions are removed from service-contract creation and their retained fields are shown directly in the form.
- [x] The fixed built-in contract template is no longer a dedicated body field; its name appears in the header. Active contract versions, including historical active versions, are directly selectable in the compact header area.
- [x] Service-contract start date is not displayed or accepted from the client; the server assigns the current application date.
- [x] Ordinary counselors cannot change the admission employee; `assign_admission_employee` controls this field in the UI and API and defaults only to manager/admin plus implicit super-admin.
- [x] Assigning or reassigning an admission employee during contract creation or applicant editing sends one notification to the newly assigned admission employee, including when that employee is the actor; `notifyOnce()`/`deliverTo()` are preserved.
- [x] Authorized staff can disable and re-enable an applicant's portal access from the visa case page, immediately revoking all tokens when disabled while retaining the account and data.
- [x] Focused backend regression coverage covers API contracts, version selection, RBAC defaults, notifications, and portal access. Frontend compile/browser validation remains environment-blocked.

### 2. Scope and non-goals

- In scope: the shared service-contract create dialog; service-contract-only field payload changes; explicit version selection and validation; server-owned creation date; admission-assignment permission and notifications; applicant case portal-access controls and API; translations and targeted tests.
- Out of scope: counselor restrictions over scholarship/portal data (explicitly deferred); changing/removing visa-case multi-university applications; restoring removed invoice/company/ledger features; changing generic non-service contract creation; replacing heartbeat notification delivery; redesigning the whole applicant portal or applicant case page; deleting university data from the applicant domain.

### 3. Project findings

- Current behavior:
  - All four built-in services use `resources/ts/pages/service-contracts.vue` and static routes with `meta.builtInService`.
  - The create dialog has `max-width="1100"`, is scrollable, and renders a full-width visitor autocomplete, applicant/service fields, separate read-only template and version fields, finance/date fields, notes, and two optional-details accordions.
  - `resources/ts/components/ApplicantServiceFields.vue` renders university for scholarship/private, university fee for private in the main grid, and university fee for scholarship inside its own optional-details accordion.
  - University is not presentation-only: `ContractGenerationService` validates its country, uses it to choose the initial applicant stage, and uses it when resolving a contract policy/template version. Removing the submitted university without a replacement rule can change generated content or cause policy lookup failure.
  - The client currently supplies `start_date`; `StoreServiceContractRequest` requires it. The generator stores it in the issued contract and template context.
  - The admission employee selector is always editable for new non-academy service contracts. Existing `assign_applicant_employee` logic governs responsible/counselor assignment, not admission assignment. The request accepts admission assignment for every user with `create_contracts`, so hiding the field alone would be insecure.
  - Default role grants give `assign_applicant_employee` to managers, while counselors have `edit_applicants` but not `assign_applicant_employee`. A new dedicated permission can fit the existing permission catalog/role seeder model.
  - Counselors receive `edit_applicants`; the applicant update endpoint currently accepts scholarship/service detail fields under that broad permission. The applicant-facing portal profile endpoint is separate and available only to the linked applicant user.
  - Applicant portal access is currently represented by `applicants.user_id`; invitations create an active `users` record. The case header only shows “portal active” after invitation. No case-level disable/re-enable endpoint exists. Existing `users.acc_status` plus the global `active` middleware already blocks inactive users and deletes the current access token, making it the likely reusable enforcement mechanism.
  - `contractGenerated()` currently de-duplicates the responsible employee, admission employee, and selling employee, then excludes the actor. Therefore it can deliver zero to three recipient pivots, not necessarily exactly one.
- Relevant architecture and conventions:
  - REST payload normalization belongs in `resources/ts/api`; responses are double-wrapped at `response.data.data`.
  - Routes are explicit in `routes/api.php` and `resources/ts/plugins/1.router/routes.ts`.
  - Permissions are cataloged/seeding-backed and must be enforced server-side, with CASL/UI checks used only as the matching presentation layer.
  - Notification producers must continue through `EducationalWorkflowNotificationService::notifyOnce()` and `Notification::deliverTo()`; delivery remains HTTP-heartbeat based.
  - Responsive, RTL/LTR, and light/dark support are mandatory.
- Relevant paths/files:
  - Frontend: `resources/ts/pages/service-contracts.vue`, `resources/ts/components/ApplicantServiceFields.vue`, `resources/ts/pages/applicant-case.vue`, `resources/ts/api/contracts.ts`, `resources/ts/api/applicants.ts`, `resources/ts/api/applicantPortal.ts`, locale files.
  - Backend: `app/Http/Requests/StoreServiceContractRequest.php`, `app/Http/Controllers/ContractController.php`, `app/Services/Contracts/ContractGenerationService.php`, `app/Http/Controllers/ApplicantController.php`, `app/Http/Controllers/ApplicantPortalController.php`, `app/Services/EducationalWorkflowNotificationService.php`, `app/Models/User.php`, `routes/api.php`.
  - RBAC/data: `database/seeders/PermissionSeeder.php`, `database/seeders/RolePermissionSeeder.php`, likely a data migration for installed databases, and applicant/user schema.
  - Tests: `tests/Feature/BuiltInContractGenerationTest.php`, `tests/Feature/ApplicantPortalTest.php`, `tests/Feature/RoleBasedAuthorizationTest.php`, `tests/Feature/VisaFirmComplianceTest.php`, `scripts/service-contract-regression.mjs`.
- Constraints:
  - The worktree already contains many unrelated modifications and deletions. Any later implementation must preserve them and carefully inspect overlaps, especially currently modified notification, applicant, API, case-page, and `AGENTS.md` files.
  - Git required a per-command safe-directory override in the sandbox; no repository configuration was changed.

### 4. Graph and impact review

- Graph skills/tools or fallback methods actually used: no graph skill was available. Repository search (`rg`), focused source reads, route tracing, request/controller/service tracing, and test call-site searches were used as the fallback. Existing `graphify-out` artifacts were not relied on because their cache is modified and no applicable graph skill was exposed.
- Dependencies, callers, data flows, schemas, and integrations examined:
  - Static route -> `service-contracts.vue` -> `ContractApi.serviceOptions/createService` -> contract options/store routes -> `StoreServiceContractRequest` -> `ContractGenerationService` -> applicant/service detail, stage/policy resolution, task generation, and notification generation.
  - Applicant case page -> `ApplicantPortalApi.invite` -> invitation endpoint -> applicant/user link -> applicant portal routes guarded by Sanctum, ability, active-account, and permission middleware.
  - Permission catalog/default role grants -> frontend ability checks and backend middleware/service checks.
  - Contract-generated producer -> `notifyOnce` -> `Notification::deliverTo` -> heartbeat/inbox consumption.
- Potential downstream effects and affected tests:
  - Removing university input can alter stage/policy resolution and active template matching.
  - Server-owned dates affect request validation, API types, template snapshots, filtering/list output, and timezone-boundary tests.
  - Deactivating a portal user affects login, refresh, existing access tokens, active middleware, case display, and re-invitation behavior.
  - Splitting edit permissions affects counselors, managers/admins, seeded installations, custom roles, service creation, and generic applicant editing.
- Tool/review limitations: no browser layout measurement or test suite was run because this is discovery/planning and the intended behavior still has material open decisions. The exact working-tree status was visible but truncated due to the unusually large set of existing asset deletions; relevant overlapping modified files were identified.

### 5. Clarifications, decisions, and assumptions

- Open questions: none.
- User decisions:
  - Remove university and university-fee fields only from service-contract creation. Preserve the existing visa-case multi-university application feature and its columns.
  - Defer all counselor restrictions concerning scholarship/portal data.
  - Notify the newly assigned admission employee on both initial contract assignment and later edit/reassignment, even when that employee performed the action.
  - Portal access must be reversibly disabled/re-enabled, with data/uploads preserved and active access revoked.
  - Grant the dedicated admission-edit permission to manager/admin defaults and super-admin implicitly, not counselors.
  - Preserve explicit contract-version selection because versions are dynamic; place it in the compact header rather than removing the capability.
- Assumptions and rationale:
  - “Visitor width shorter” is provisionally interpreted as making the visitor autocomplete occupy only part of the desktop grid while remaining full width on small screens.
  - The header will show the fixed template name and expose the version through an always-visible compact selector, avoiding a separate body section and extra clicks.
  - “Immutable current date” should be server-owned and based on the Laravel application timezone at creation time; removing only the frontend control would not provide immutability.
- Alternatives considered (where useful):
  - Reusing `assign_applicant_employee` for admission assignment would be smaller, but it is not dedicated and would couple counselor ownership reassignment to admission assignment, contradicting the stated RBAC requirement.
  - Unlinking or deleting the applicant portal user would make re-enablement and audit continuity harder; deactivation is reversible and supported by current auth middleware.

### 6. Implementation plan

- [x] Step 1 — Refactored service-contract state/API payloads, compatible version selection, and removed university/date submission.
- [x] Step 2 — Rebuilt the dialog as a compact responsive grid with a narrow visitor selector, upright optional fields, header version selector, and sticky total/actions.
- [x] Step 3 — Made the server authoritative for current date and active compatible selected-version validation; single-university fields are prohibited.
- [x] Step 4 — Added `assign_admission_employee` catalog/seeder/migration grants, backend enforcement, options filtering, and frontend gating.
- [x] Step 5 — Restricted contract-generated notifications to the assigned admission employee and added reassignment notification behavior without actor exclusion.
- [x] Step 6 — Added reversible portal access controls, transactional status changes, and token revocation.
- [x] Step 7 — Updated EN/FA/PS translations and focused automated tests.
- [ ] Step 8 — Backend tests, migration cycle, JSON parsing, PHP syntax, and diff checks passed. `npm run typecheck`, `npm run build`, and browser checks remain blocked by the local dependency/toolchain failure described below.

### 7. Validation plan and results

- Planned: focused Laravel feature tests, `npm run typecheck`, relevant frontend lint/build checks, `scripts/service-contract-regression.mjs`, and manual responsive/RTL/LTR/light/dark dialog checks at representative desktop/tablet/mobile viewports.
- Results:
  - PHP syntax checks passed for all changed backend/migration files.
  - `php artisan test tests/Feature/BuiltInContractGenerationTest.php tests/Feature/ApplicantPortalTest.php tests/Feature/VisaFirmComplianceTest.php tests/Feature/RealtimeNotificationTest.php` passed before the final two contract assertions were added: 29 tests, 631 assertions.
  - `php artisan test tests/Feature/AgencyWorkflowCoherenceTest.php tests/Feature/RoleBasedAuthorizationTest.php tests/Feature/ServiceContractNavigationTest.php` passed: 13 tests, 92 assertions.
  - Final `php artisan test tests/Feature/BuiltInContractGenerationTest.php` passed: 14 tests, 163 assertions, including server-owned date, university-field rejection, and explicit historical active-version selection.
  - Temporary SQLite `migrate:fresh`, last-step rollback, and forward migrate passed for `2026_10_14_000005_add_admission_assignment_permission.php`; schedule discovery also completed.
  - All three locale JSON files parsed successfully; `git diff --check` passed.
  - `npm run typecheck` and `npm run build` could not load the toolchain. Dependencies were initially absent; offline install lacked `@casl/ability`, the approved network install helper failed to start, and the available partial install fails under Node 24 in `entities@7.0.1` with `ReferenceError: exports is not defined in ES module scope` before project source compilation.
- Checks not run and why: browser automation/manual responsive, RTL/LTR, and light/dark validation could not start because the frontend build/toolchain cannot load in the current environment.

### 8. Risks and mitigations

- Removing university without defining replacement selection semantics could break template/policy/stage resolution — mitigation: resolve the question before finalizing the plan.
- UI-only RBAC would allow crafted API writes — mitigation: plan request/service/controller authorization first, then mirror it in the UI.
- Deactivating a user without revoking all tokens may allow continued sessions — mitigation: explicitly plan token revocation inside the same transaction and test existing-token access.
- “No scroll” cannot be guaranteed on every viewport or with validation/localized text — mitigation: define a desktop-height target and retain safe scrolling for small screens and overflow cases.
- Existing unrelated edits overlap several likely files — mitigation: implementation must review diffs before each edit and avoid overwriting user changes.

### 9. Progress log

- 2026-10-14 — DISCOVERY: Read the Plan-Implement workflow, project instructions, repository structure, static routes, shared service-contract UI, API contracts, request validation, contract generation, permissions, notification delivery, applicant portal controller, case-page portal invitation UI, auth active-account enforcement, and relevant test references.
- 2026-10-14 — DISCOVERY: Confirmed the shared dialog and both optional accordions; traced university into policy/stage resolution; confirmed client-owned start date; confirmed missing admission-specific permission and missing portal disable endpoint; confirmed contract notification currently targets up to three roles.
- 2026-10-14 — SAFETY: Identified a heavily dirty worktree and overlapping modified files; made no changes outside this planning record.
- 2026-10-14 — CLARIFICATION: User limited university removal to service-contract fields, deferred counselor scholarship/portal restrictions, required admission notifications on create and reassignment without actor exclusion, selected reversible portal suspension, approved manager/admin admission-assignment permission defaults, and required editable dynamic contract-version selection.
- 2026-10-14 — PLAN: Finalized the implementation and validation plan before code changes; status moved to IN PROGRESS.
- 2026-10-14 — PROGRESS: Rebuilt the shared service-contract dialog into a compact grid; moved fixed template identity and selectable active version into the header; shortened the visitor field; removed service-contract university/university-fee/date inputs and both contract-form accordions; retained the visa-case multi-university workflow.
- 2026-10-14 — PROGRESS: Added server-owned contract dates, selected-version scope validation, admission-assignment RBAC/migration/options enforcement, admission-only creation notifications, edit/reassignment notifications, and reversible portal access with token revocation.
- 2026-10-14 — PROGRESS: Added EN/FA/PS UI copy and focused regression coverage; preserved the explicitly deferred counselor scholarship/portal-data restriction.
- 2026-10-14 — VALIDATION: PHP syntax, 44 focused feature tests across the final relevant suites, migration fresh/rollback/forward, locale JSON parsing, and diff whitespace checks passed. Frontend typecheck/build/browser checks are environment-blocked by the partial dependency install/Node 24 `entities` module failure before source compilation.

### 10. Completion summary

- Delivered: compact service-contract form, service-only removal of single-university fields, active version selection, server-owned date, admission RBAC/notifications, reversible portal suspension, migrations, translations, and regression tests.
- Acceptance criteria satisfied/not satisfied: implemented criteria are satisfied by source and backend tests; visual/compile criteria are not fully validated because the frontend toolchain cannot load.
- Tests/checks passed: backend focused suites, syntax, migration lifecycle, locale JSON, and diff checks listed above.
- Remaining limitations or follow-up work: repair/install frontend dependencies under the project's supported Node version, then run `npm run typecheck`, `npm run build`, the service-contract regression script, and responsive RTL/LTR light/dark browser checks. No application defect was reported by those commands because they fail before reading project source.
