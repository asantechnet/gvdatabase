## Task: Operations, contracts, applications, email, leave and comment fixes

- **Task ID:** operations-contracts-applications-email-and-leave-fixes
- **Created:** 2026-11-10
- **Last updated:** 2026-11-11
- **Status:** COMPLETE
- **Outcome:** Make responsible-employee roles configurable, allow leave rejection, stop inactive currencies leaking into ordinary pickers, fix the Applications overlay/dark theme, add the Applicants navigation entry and attached-services view, unify disabled email-delivery errors, restrict comment editing to its creator within 30 minutes, and remove university notes while keeping quick contact in visa cases.

### 1. Requirements and acceptance criteria

- [x] Options → Contracts exposes a configurable responsible-roles list; only employees matching the configured roles appear in responsible-employee selects.
- [x] Pending leave requests can be rejected from the leave-request workspace, with the rejected state visible and excluded from approval.
- [x] Ordinary currency GET requests exclude inactive currencies; only the main Finance → Currencies page asks for all currencies.
- [x] Applications dialogs use the theme-aware overlay system already used by Academy, with no light veil in dark mode.
- [x] The Applications navigation includes a working Applicants button for the existing CRM Applicants page.
- [x] The Applicants page exposes each applicant's attached services without leaking finance-only fields.
- [x] Disabled email delivery never falls back to the environment mailer; every attempted send returns one unified "email delivery is not set up" error, and the setting label/help text describe that behavior.
- [x] Only the creator of a task comment can edit it, and only within 30 minutes; otherwise no edit action is offered or accepted.
- [x] Visa-case university applications no longer expose university notes and retain/offer university and country quick-contact actions.

### 2. Scope and non-goals

- In scope: backend validation/query changes, migrations or settings storage needed by the above, API resources/contracts, static routes, navigation, responsive/theme-aware Vue UI, translations, and focused tests.
- Out of scope: restoring removed invoice/counterparty features, changing unrelated permissions, redesigning the applications pipeline, changing existing approved leave balances beyond rejection bookkeeping, adding a mail queue, and committing/pushing.

### 3. Project findings

- `AgencyStaffAssignment` currently hardcodes counselor/admission by employee position `system_key`/title and account role names. `ApplicantController::store` validates responsible IDs through the `admission` specialty, while `ContractController::serviceOptions` and `EmployeeController::assignmentOptions` centralize the query.
- `leaves`/`LeaveRequest` currently model approval only through `is_granted`; `LeaveRequestController` exposes grant but not rejection, and the pending list is `is_granted = false`.
- `CurrencyController@index` returns every currency. `resources/ts/api/currency.ts` is shared by many pickers, while `resources/ts/pages/options/currencies.vue` needs the complete set.
- `resources/ts/pages/applicants.vue` overrides `.v-overlay__scrim` with `rgba(var(--v-theme-on-surface), 0.24)`, which becomes a light scrim in dark mode. Academy pages use Vuetify's theme-aware default; the Applications override should be removed rather than replaced with another hard-coded scrim.
- `resources/ts/components/erp/ErpPageHeader.vue` lists Applications and Application Reports for the applications section, but not the existing `/all-customers` CRM Applicants page.
- `resources/ts/pages/all_customers.vue` currently lists identity/contact fields and actions only. `ApplicantResource` only serializes `services` when applicant contracts are loaded and the viewer has `view_applicant_contracts`; list loading needs a safe, non-financial summary for an attached-services view.
- Email delivery falls back to the environment mailer when `email_delivery_enabled` is false; the shared `CompanyProfile` helper and mail-sending call sites must instead stop at one clear configuration error. The setup settings label/help text currently implies a different behavior.
- `resources/ts/components/task-manager/components/TaskCommentsDrawer.vue` lets `edit_all_comments` expose editing; `EditWindow` is shared and currently 60 minutes. Backend policy/controller and frontend must agree on creator-only plus 30 minutes.
- `resources/ts/pages/applicant-case.vue` already renders primary and tracked university/country quick-contact controls using `ContactActionMenu`/`CrmRelationshipDetails`. It also has a university-notes draft/dialog, a notes button, notes text in the tracked row subtitle, and a notes field on the add-application form; these are the notes to remove.
- The working tree already contains earlier Task-002/003/004 and unrelated changes. They must be preserved.

### 4. Graph and impact review

- Graph tools: repository search and manual caller tracing; no graphify artifact/skill was available for this task.
- Responsible employees: applicant create/update, applicant edit form, applicant filters, contract service options, and employee assignment-option endpoints all converge on `AgencyStaffAssignment`; changing the central query updates every select and validation path.
- Currencies: the largest fan-out is the shared frontend currency API used by applicants, contracts, expenses, incomes, assets, payroll and display currency. Defaulting the API to active rows fixes them together.
- Applicants: `/all-customers` uses `ApplicantApi.list`; `applicant-case` already uses `ApplicantApi.get` and service lines. Adding a safe summary to the list avoids making every row fetch the finance-heavy detail.
- Email: `CompanyProfile` is used by support and account/security flows; a single guard in the mail profile setup prevents environment fallback for all callers. Callers must translate the unified exception into the standard API error shape.
- Comments: `CommentPolicy`, `CommentController`, `CommentResource`, `EditWindow`, the task comment drawer, and task store/API contracts form one chain.
- Leave: migration/model/resource/controller/route/frontend API/page form one chain; approval must keep existing attendance materialization and allowance behavior.

### 5. Clarifications, decisions, and assumptions

- Open questions: none after clarification.
- User decisions: responsible employees are filtered by selected **employee positions only**; leave rejection keeps an **optional reason**, rejected requests stay visible, and the employee may submit a fresh request for the same dates.
- Resolved by repository review: the Academy overlay system is Vuetify's default theme-aware overlay; the Applications scrim override is the defect.
- Assumption: the main Finance → Currencies page is `/options/currencies`, the only surface that should request inactive currencies.
- Assumption: "Applicants page" is the existing `/all-customers` CRM page, and the Applications navigation entry should point there. Its header section moves to `applications` so the new destination is visible and current there.
- Assumption: the responsible-role list governs the plural responsible-employee picker (the existing `admission` specialty). The separate counselor picker keeps its existing counselor-position behavior.
- Constraint discovered: `leaves` has a unique `(employee_id, date)` key. Re-requesting a rejected date therefore resets that rejected row to pending rather than inserting a duplicate; the rejection reason remains until the fresh request replaces it.

### 6. Implementation plan

- [x] Step 1 — Record the clarifications, then finalize the ordered plan before editing feature files.
- [x] Step 2 — Add the responsible-role configuration storage/API/UI and switch `AgencyStaffAssignment` to the configured roles while preserving active-employee validation.
- [x] Step 3 — Add leave rejection state, validation, endpoint, frontend API and rejection UI; keep approval/attendance behavior intact.
- [x] Step 4 — Default currency GETs to active rows and make only the currencies-management page opt into inactive rows.
- [x] Step 5 — Remove the Applications scrim override and align its dialog overlays with the Academy/Vuetify default.
- [x] Step 6 — Add the Applicants navigation entry and a safe attached-services summary/view on the Applicants page.
- [x] Step 7 — Add a single disabled-email guard and unified error, update settings wording, and cover all outbound mail paths.
- [x] Step 8 — Enforce creator-only 30-minute comment editing in policy, controller, API resource and task-comment UI.
- [x] Step 9 — Remove university notes from the visa-case university application flow while retaining university/country quick contact.
- [x] Step 10 — Run focused backend tests, `npm run typecheck`, translation/API checks and targeted source/build verification; update this record.

### 7. Validation plan and results

- [x] Focused PHP feature tests for responsible-role eligibility, leave rejection, currency filtering, email guard and comment edit window.
- [x] `npm run typecheck`
- [x] `php artisan test --filter=...` for each affected backend feature.
- [x] Translation-key parity check for new labels in English, Persian and Pashto.
- [x] Source checks for removed notes, navigation target, overlay selector, and attached-services columns.
- Results:
  - `npm run typecheck` — passed.
  - `php -l` on all modified PHP files and JSON parsing for `en`, `fa-AF`, `ps-AF` — passed.
  - `SupportTicketTest` — 5 passed (including disabled delivery returning `business.email_delivery_not_configured`).
  - `LeaveRequestEdgeCaseTest` — 7 passed after freezing the test clock; includes rejection, approval blocking, and re-request reset.
  - `CurrencyVisibilityTest` — 2 passed; inactive rows require `include_inactive` and `view_currencies`.
  - `ResponsibleEmployeeAssignmentTest` — 7 passed; includes position-driven eligibility.
  - `TaskCommentMentionTest` — 2 passed; includes creator-only and 30-minute expiry.
  - `SettingsEmailAndFileRemovalTest --filter=test_disabling_email_delivery_blocks_the_company_mailer` — passed.
  - Locale key parity and source checks — passed; no Applications scrim override, university-notes remnants, or comment edit-all UI remains.
- Known unrelated failures observed outside this task's touched assertions: the full settings file's `test_partial_updates_report_required_fields...` and `AgencyWorkflowCoherenceTest`'s primary-mirror assertion. Neither path was changed by this task; the full suite was not run.
- Checks not run: browser visual review and full test suite. The change was verified by typecheck, focused backend tests and source/locale checks.

### 8. Risks and mitigations

- Changing responsible-role configuration could silently empty every select — preserve a safe default and validate configured roles before saving.
- Defaulting currencies to active could hide the base/inactive currency needed by historical records — explicitly opt the management page and retain IDs already attached to records.
- Rejection bookkeeping could disturb attendance or allowance usage — rejection must never write attendance, and approval remains the only granting path.
- A mail guard could break unrelated password/verification flows — use one exception and return a stable, translated configuration error without masking other mail failures.
- Comment role rules could break edit-all workflows — remove the edit override only; leave deletion and other comment permissions unchanged.
- Removing notes could hide existing data — stop the UI/API writes but do not destructively drop existing rows without an explicit request.

### 9. Progress log

- 2026-11-10 — DISCOVERY: Traced responsible assignments, leave approval, currency fan-out, Applications overlay/header, applicant services, email fallback, comment edit path, and visa-case university notes. Confirmed the existing dirty worktree must be preserved.
- 2026-11-10 — CLARIFICATION: Waiting for the responsible-role source and leave-rejection lifecycle decisions.
- 2026-11-11 — IMPLEMENTATION: Added responsible-position storage/API/editor, leave rejection/re-request handling, active-currency defaults, theme-aware Applications overlays, Applicants navigation and attached-service summaries, disabled-email guard/error, creator-only 30-minute comment editing, and removal of university notes with retained country/university quick contact.
- 2026-11-11 — VALIDATION: Fixed the support-mail closure capture, added focused regression tests, passed the listed suites, typecheck, PHP/JSON checks and locale/source checks. Recorded two unrelated failures in broader suites.

### 10. Completion summary

- Delivered: all nine requirements across backend, API contracts, routing, Vue UI, migrations and translations.
- Acceptance criteria satisfied: all listed criteria are implemented and covered by the focused checks above.
- Tests/checks passed: typecheck; PHP syntax; locale parsing/parity; support email tests; leave edge cases; currency visibility; responsible-role eligibility; comment edit window; targeted settings email guard.
- Remaining limitations or follow-up work: run `php artisan migrate` when deploying the two new migrations; run the full suite in a clean baseline to resolve the two unrelated failures noted above; perform a browser visual pass for the responsive/RTL/dark-theme surfaces if desired.
