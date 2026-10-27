## Task: Visitor profile fields and per-service contract field sets

- **Task ID:** visitor-profiles-and-service-contract-field-sets
- **Created:** 2026-10-27
- **Last updated:** 2026-10-27
- **Status:** PARTIALLY COMPLETED
- **Outcome:** Visitors carry a complete enquiry profile (education, age, percentage, province, English level, acquisition route, consultant, consultant review). Each built-in service contract (scholarship, private, test offer, academy) shows only its own field set, the frontend can select a visitor and auto-fill the overlapping fields for every service type, private contracts price the application fee as a saved range whose paid state can be completed from the contract list, and academy contracts can record a paid fee as a real receipt. Main changes are mirrored into the multi-tenancy worktree per `docs/facts/branch-mirroring.md`.

### 1. Requirements and acceptance criteria

**Visitors**

- [ ] Visitor form and API have: full name (required), phone (required), email, education (education-level catalogue), age, percentage, province catalogue, English-level catalogue, acquisition-route catalogue, consultant, and a consultant-review flag.
- [ ] A user holding the `counselor`/`senior_counselor` role is auto-selected as consultant on create; the field is disabled and cannot be changed through the API (create or update).
- [ ] `reviewed_by_consultant` is writable only by holders of the new `review_visitors` permission; the field is hidden from users without it and a request that tries to set it is rejected.
- [ ] `provinces`, `english-levels` and `acquisition-routes` are educational master-data catalogues managed in Settings; provinces (all Afghan provinces) and English levels are seeded, acquisition routes are left for admins.
- [ ] Visitor list, printable report and edit dialog show the new fields; search/filters and responsive/RTL/dark behaviour keep working.
- [ ] Visitor API types expose the new fields and the consultant rename.

**Service contracts**

- [ ] Scholarship: full name (required), contact (required), parent name (required), parent contact (required), percentage (required), education level (required), country and language (required for version scoping), application fee, currency (required), rate (required), requested subjects (required), admission note.
- [ ] Private: full name (required), phone (required), parent name (required), parent phone (required), email, address, percentage (required), country (required), education level (required), semester (required), application fee as a min–max range (required), visa fee (required), currency (required), rate (required), contract type (required), requested subjects (required), university fee (required), admission note.
- [ ] Test offer: full name (required), phone (required), backup phone (required), test name (required), test skills (required), booking fee (required), services fee (required), remote fee (required); currency/rate remain for every service.
- [ ] Academy: student id (required), student full name (required), phone (required), time/teacher/duration/program/class (required), service fee (required), currency (required), rate (required), paid fee.
- [ ] Fields outside a service’s list are not displayed anywhere in that service’s contract form and are accepted as optional in the backend (existing cross-service rejection stays).
- [ ] The consultant field is shown for every service; the admission employee only for scholarship and private.
- [ ] The shared contract envelope needed to issue and print a contract (template, version, language, dates, discount, signatory) remains.
- [ ] Private application fee is stored as a saved range (`min`/`max`) on the contract line, the contract price/payable is the range maximum, lists/details show the range, and a “mark paid” action appears only when the effective paid amount lies inside the range; it uses the existing payment-override endpoint and permission.
- [ ] Academy “paid fee” creates a real linked income receipt at contract creation (requires `create_incomes`); line paid state follows the receipt.
- [ ] Visitor selection in every service contract form auto-fills the overlapping fields (name, phone, email, education level, percentage) and still pre-fills from `?visitor=`.
- [ ] New labels exist in `en`, `fa-AF` and `ps-AF`; layouts stay responsive, RTL/LTR and light/dark.

**Validation and mirroring**

- [ ] Focused PHP feature tests and the full PHP suite (where feasible), `npm run typecheck`, `npm run i18n:check` pass on main.
- [ ] The change is committed on `main` as ordinary commits and ported to `feature/multi-tenancy-per-office` in `../educational-multi-tenancy` following `docs/facts/branch-mirroring.md`, with the tenant-specific adaptations and validation recorded there.

### 2. Scope and non-goals

- **In scope:** migrations; visitor model/controller/resource/validation/page/API contract; educational catalogue models/controller/routes/seeders/settings page; permissions and role grants; per-service contract request validation, applicant detail sync, contract generation, income receipt for academy, private fee-range storage/display/mark-paid; shared applicant-service form component and service contract page; applicant case/applicants form alignment; `ApplicantSummary`; academy slip/print data where it already lists the affected fields; i18n; feature tests; main commit and multi-tenancy mirror.
- **Out of scope:** HR/payroll/attendance (parallel agents own those files); multi-tenancy architecture/Drive; redesigning the applicant portal, CRM interactions, WhatsApp templates, reports engine or contract template content; retroactive data migration for existing visitor/catalog values beyond safe nullable columns; visitor-to-applicant conversion of fields that have no applicant counterpart.
- **Compatibility:** existing contracts keep their rows; new columns are nullable/defaulted. Private fee range is additive (`application_fee` continues to hold the range maximum for older consumers).

### 3. Project findings

- **Visitors.** `visitors` currently stores name, contact, email, service, notes, date, follow-up, converted_at and `counselor_employee_id`. `VisitorController::store` already forces the signed-in counselor’s employee id when the actor has the `counselor`/`senior_counselor` role, but `update` does not lock it. `VisitorResource` returns only the old fields. `resources/ts/pages/visitor.vue` renders the form/table/print report; `resources/ts/api/visitor.ts` holds the types and `visitorApplicantFields`, which currently maps only name/contact/email.
- **Educational data.** `EducationalMasterDataController` is a thin CRUD adapter over a fixed map of small catalogues (countries, education-levels, languages, universities, semesters, subjects, tests, skills, programs, times, durations, teachers, scholarships, classes). `resources/ts/pages/educational-master-data.vue` renders the tabs and generic add/edit dialog; `resources/ts/api/educationalMasterData.ts` types the catalogue union. Catalogue permissions are `<action>_<singular>`.
- **Service contracts.** Creation runs through `POST /api/contracts/{service}` → `StoreServiceContractRequest` → `ContractGenerationService`. Per-service data lives in four detail tables (`scholarship_applicant_details`, `private_applicant_details`, `test_applicant_details`, `academy_applicant_details`) plus pivots (`scholarship_applicant_subject`, `test_applicant_skill`, `academy_applicant_teacher`); money lives on `applicant_contract`. `ApplicantServiceDetails` is the single writer.
- **Contract UI.** `resources/ts/pages/service-contracts.vue` is the dialog; `resources/ts/components/ApplicantServiceFields.vue` is shared with `applicants.vue` and `applicant-case.vue`. The current component already branches by service but still shows fields outside the requested sets (passport, university, scholarship, contract type/semester gaps, no student id, no paid fee). Visitor autofill uses `visitorApplicantFields` in the `visitorId` watcher.
- **Money.** `ApplicantContract` owns `fee`, itemized fees, `discount`, `currency`, `currency_rate`, `amount_paid`, `payment_status`. `ApplicantContractPaymentService` calculates paid/payable; `IncomeController::paymentOverride` sets the existing overrides under `override_contract_payments`. Incomes are the cash ledger and must be linked to the line for balances to update. `StoreServiceContractRequest` currently asserts total fee equals the itemized charges.
- **Template versions.** Scholarship/private versions are scoped by destination country + language; test-offer versions by test + language; academy by neither. Hiding country for scholarship would make version selection impossible, so country stays visible for scholarship.
- **Existing tests.** `VisitorFollowUpTest`, `AgencyWorkflowCoherenceTest` (counselor field), `MarketerRetirementMigrationTest` (schema assertion), `BuiltInContractGenerationTest`, `ApplicantServiceDetailsTest`, `EducationalMasterDataTest`, `ContractExtensionTest`, `EducationalContractIntegrityTest` cover the affected paths.
- **Parallel work.** The `main` working tree contains a large uncommitted HR rewrite (including `AGENTS.md`, HR controllers/models/services, a new migration and docs) owned by parallel agents. Those files, `public/build`, `public/sw.js`, `public/version.json`, `auto-imports.d.ts` and `components.d.ts` are not mine to edit or commit. `AGENTS.md` in particular is modified by that work, so this task documents its behavior here instead of editing the project notes.

### 4. Graph and impact review

- **Graph artifact:** `graphify-out/GRAPH_REPORT.md` (built from commit `c4c97435`, 2026-10-09) was used for orientation. It contains the `visitor.vue` community (`Visitor`, `VisitorApi`, `visitorApplicantFields`, `Counselor`, create/update payloads) and the `VisitorController.php` community (`VisitorController`, `StoreServiceContractRequest`, `AgencyStaffAssignment`, resources), confirming the visitor → contract form coupling.
- **Fallback actually used:** repository search and direct reads for every affected path: visitor migration/model/controller/resource/page/API types, `EducationalMasterDataController` and settings page, `StoreServiceContractRequest`, `ContractGenerationService`, `ApplicantServiceDetails`, four detail models, `ApplicantContract`, `ApplicantContractPaymentService`, `IncomeController`/`Income` model, `service-contracts.vue`, `ApplicantServiceFields.vue`, `ApplicantSummary.vue`, applicant pages, permissions/seeders, routes, template version seeders, tests and both locale sets.
- **Impact:** visitor CRUD and follow-up lists, catalogue CRUD + settings UI, contract creation validation/generation, applicant detail writes, contract list/details/printing, income creation and payment state, permission catalog/role grants, i18n, generated icon CSS, and the multi-tenancy office migration set.
- **Limitations:** the graph predates the current contract/visitor changes and no graph query CLI/skill is installed here, so no live graph traversal was claimed. Browser/print verification is limited to code and tests in this environment.

### 5. Clarifications, decisions, and assumptions

- **User decisions**
  1. “Consultant” replaces the visitor’s counselor: rename the field end to end and keep the auto-select/lock rule.
  2. “Review by consultant” is a boolean flag gated by a new `review_visitors` permission.
  3. Private application fee is a price range (Vuexy/Vuetify range control), saved, and defines the service price; a “mark paid” button appears only when the paid amount is within the range.
  4. “Paid service” is dropped; “paid fee” (academy) creates a real payment receipt.
  5. Province/English-level/acquisition-route become seeded educational catalogues.
  6. Currency/rate stay for every service contract.
  7. Scholarship keeps country and language because template versions need them.
  8. Admission employee stays for scholarship/private; consultant stays for all services.
- **Derived decisions**
  - `visitors.counselor_employee_id` is renamed to `consultant_employee_id` (same FK, same auto-lock); assignment kind `counselor` remains a position/role concept.
  - `reviewed_by_consultant` defaults false; the flag is not auto-set for counselor-created visitors.
  - Private range is stored as `applicant_contract.application_fee_min`/`application_fee_max`; `application_fee` keeps the maximum for older readers and `fee` equals max(application fee) + visa fee. `payableTotal()` therefore uses the maximum, so receipts can be accepted up to the range ceiling.
  - “Mark paid” reuses `PUT /api/incomes/applicant-contracts/{line}/payment-override` with `payment_status: paid`, gated by `override_contract_payments`; the button is private-only and range-aware.
  - Academy `paid_fee` is accepted only with `create_incomes`, creates an income of type `Service` dated today in the line currency/rate, and is not duplicated as a stored line column.
  - Academy student id lives on `academy_applicant_details.student_id`.
  - Unlisted service fields become `nullable` in `StoreServiceContractRequest` (rather than `prohibited`) where they belong to the same service family, while genuinely cross-service keys stay prohibited. The frontend stops rendering/sending them.
  - Field-set rules are shared by the service-contract dialog and the applicant create/edit forms through `ApplicantServiceFields.vue`, so stored data cannot drift by entry point.
  - Visitor → contract autofill maps name, contact, email, education level and percentage; province/English/source/age have no contract counterpart.
- **Assumptions**
  - The “Private contracts” heading is authoritative and the “Edit Scholarship contracts” line in the brief is a typo; the two field lists map to private and scholarship respectively.
  - `education` on a visitor means the education-level catalogue; percentage is the same 0–100 grade score used by admissions.
  - Acquisition routes are admin-managed, so the seeder adds none.
  - Demo/dev data may be migrated forward; new required API fields do not require rewriting existing rows.

### 6. Implementation plan

- [ ] **Step 1 — Schema, models, seeders.** Create `provinces`, `english_levels`, `acquisition_routes`; add visitor columns (`education_level_id`, `age`, `grade_score`, `province_id`, `english_level_id`, `acquisition_route_id`, `reviewed_by_consultant`) and rename `counselor_employee_id` → `consultant_employee_id` with its FK; add `academy_applicant_details.student_id`; add `applicant_contract.application_fee_min`/`application_fee_max`. Add `Province`, `EnglishLevel`, `AcquisitionRoute` models; update `Visitor`, `AcademyApplicantDetail`, `ApplicantContract`; seed Afghan provinces and English levels; update `VisitorSeeder`.
- [ ] **Step 2 — Catalogue API and permissions.** Extend `EducationalMasterDataController` (models, sorted list, validation, restore, in-use protection, singular permission map), routes, `educationalMasterData.ts`, settings page labels/tabs, `PermissionSeeder`, `RolePermissionSeeder` and the review permission.
- [ ] **Step 3 — Visitor backend.** Validation for all new fields; consultant lock on store/update; `review_visitors` gate; filters; resource relations/payload; keep follow-up and conversion behaviour.
- [ ] **Step 4 — Visitor frontend.** API types (`consultant_employee_id`, new fields, autofill map), visitor page form/sections, list columns, review gate/chip, filters, print report, i18n.
- [ ] **Step 5 — Per-service detail backend.** Academy student id in `ApplicantServiceDetails` and `ApplicantController`; align details/resource exposure.
- [ ] **Step 6 — Contract request rules.** Required/optional sets per service, private range validation, academy paid fee and student id, envelope retention, itemized total rule.
- [ ] **Step 7 — Contract generation.** Persist range, include it in snapshot/line payloads, create the academy receipt atomically, keep rollback guarantees.
- [ ] **Step 8 — Frontend contract types and dialog.** New API types/fields; per-service visible identity/service fields; visitor autofill; private range control and range display; academy paid fee (permission-aware); admission only for scholarship/private; consultant everywhere; mark-paid action; summary/report updates.
- [ ] **Step 9 — Applicant forms alignment.** `ApplicantServiceFields.vue`, `applicants.vue`, `applicant-case.vue`, `applicantServicePayload`, `serviceContractApplicantFields` strict per service; academy student id.
- [ ] **Step 10 — i18n and docs.** Add all keys to `en`, `fa-AF`, `ps-AF`; update this record.
- [ ] **Step 11 — Tests.** Update affected feature tests and add visitor-profile, catalogue, per-service contract, private-range and academy-receipt coverage.
- [ ] **Step 12 — Validate, commit main, mirror.** Run focused/full PHP tests, typecheck, i18n check; commit only this task’s main files; port the same behavior to `../educational-multi-tenancy` per the branch-mirroring procedure and record the port.

### 7. Validation plan and results

- [x] `php artisan test --filter=VisitorFollowUpTest` — **PASS** (3 tests, 32 assertions) against an isolated `visitorcheck_testing` database.
- [x] `npm run typecheck` — **PASS** (`npm run typecheck` exit 0).
- [x] `npm run i18n:check` — **PASS** (3146 used keys, exit 0; unused English keys are pre-existing).
- [x] Focused feature suite `AgencyWorkflowCoherenceTest|BuiltInContractGenerationTest|EducationalContractIntegrityTest` — **all passing** after updating the consultant assertions and giving each strict service fixture its required catalogue references and itemised charges.
- [x] Remaining service-contract test files — `AcademySlipTest`, `ApplicantContractStageTest`, `ContractDocumentDesignTest`, `EducationalOperationalReportingTest`, `EducationalWorkflowIntegrationTest`, `SimpleEducationalWorkflowTest` — updated and passing.
- [x] New focused test file `VisitorProfileAndServiceContractFieldSetsTest` — 7 tests covering visitor profile relations, review RBAC, consultant immutability, new catalogue CRUD, private range persistence/pricing, strict required fields and the academy receipt.
- [x] Combined affected suite — **87 passed, 707 assertions**.
- [x] `npm run typecheck` — **PASS**.
- [x] `npm run i18n:check` — **PASS**.
- [ ] Full `php artisan test` — the run exceeded the 600-second command limit and was terminated; no result is claimed.
- [ ] `npm run build:icons` — no new icon tokens added, so not run.
- [ ] `npm run build` — deliberately deferred on main while the parallel HR source is uncommitted (a committed bundle would include that unreviewed work); the tenant bundle is rebuilt on the multi-tenancy branch as the mirroring rules require.
- [ ] Playwright/HTTP-level checks of visitor create/edit and one contract per service — not run.
- **Results:** all affected focused suites pass on the isolated `visitorcheck_testing` database. The full suite remains unverified because of the time limit.
- **Review findings fixed:** the migration now restores the nullable employee foreign key on `visitors.consultant_employee_id`; the academy extension payload copies `student_id` from the parent applicant.

### 8. Risks and mitigations

- **Parallel HR work in the same tree** — touch only task files; never stage HR files, `AGENTS.md` or generated output that contains their uncommitted code; verify commits with `git status`/`git diff --cached`.
- **Renaming the visitor consultant column** — use a portable drop-FK/rename/add-FK migration and update every reference and test; keep the `counselor` position/role vocabulary (only the visitor field is renamed).
- **Private range vs payment math** — payable uses the maximum; the manual “mark paid” path only appears inside the range and keeps the existing override audit/permission semantics.
- **Academy receipt inside contract generation** — create it in the same transaction after the line exists; the income model recalculates the line; validation prevents paid fee above the payable total.
- **Required-field tightening** — update every call site/test that creates service contracts; keep unlisted backend fields optional rather than deleting columns.
- **Catalogue permission gaps** — add view permissions for counselor roles and manage permissions for managers; verify visitor forms load as a counselor.
- **Generated output / tenant drift** — never copy main’s bundle; rebuild on the tenant branch and never merge branches.
- **i18n drift** — add keys to all three locales and run `npm run i18n:check`.

### 9. Progress log

- 2026-10-27 — DISCOVERY: reviewed visitors (migration/model/controller/resource/page/API), educational master data, four service detail models, contract request/generation/payment/income paths, service contract and applicant forms, permissions/seeders, template version scoping, tests and both locales; inspected the multi-tenancy worktree and `docs/facts/branch-mirroring.md`.
- 2026-10-27 — CLARIFICATION: user settled consultant rename, review flag/RBAC, private fee range with range-aware mark-paid, academy paid receipt, seeded catalogues, currency for all services, scholarship country/language retention, and admission/consultant visibility.
- 2026-10-27 — PLAN: plan saved.
- 2026-10-27 — PROGRESS: implemented the migration, catalogue models/seeders/permissions, visitor backend + page + print + API types, per-service contract validation and detail persistence, private fee range + mark-paid action, academy student id and paid-fee receipt, shared applicant-service fields, i18n keys in all three locales, and the plan record. Main source currently typechecks and the new visitor follow-up tests pass.
- 2026-10-27 — VALIDATION: `npm run typecheck` and `npm run i18n:check` pass. Focused PHP suite: 34 passed / 12 failed (stale fixtures and visitor assertions).
- 2026-10-27 — FIX: restored the consultant foreign key in the migration and copied `student_id` in the academy extension payload.
- 2026-10-27 — VALIDATION: `AgencyWorkflowCoherenceTest`, `BuiltInContractGenerationTest` and `EducationalContractIntegrityTest` now pass (16 passed in the contract files, 174 assertions, plus the 9 agency tests).
- 2026-10-27 — FIX: added the scholarship/private university-country validation that the operational reporting test exercises.
- 2026-10-27 — VALIDATION: combined affected suite 87 passed (707 assertions); `npm run typecheck` and `npm run i18n:check` pass. Full suite timed out at the 600-second command limit.
- 2026-10-27 — COMMIT PREP: staged only this task's files; shared files were partially staged by hunk so the parallel HR work is excluded.

### 10. Completion summary

- Delivered: visitor profile fields and catalogues, consultant rename and review RBAC, strict per-service contract field sets, private application-fee range with range-aware mark-paid, academy student id and paid-fee receipt, visitor autofill, i18n and focused feature coverage.
- Acceptance criteria satisfied: all code-level criteria; the multi-tenancy mirror is still pending.
- Tests/checks passed: 87 affected tests (707 assertions), `npm run typecheck`, `npm run i18n:check`.
- Remaining limitations or follow-up work: full PHP suite was not completed within the command limit; the commit and multi-tenancy mirror are being finalized.
