## Task: Make academy contract creation work without stale template/language/student-ID fields

- **Task ID:** academy-contract-creation-fixes
- **Created:** 2026-11-09
- **Last updated:** 2026-11-09
- **Status:** COMPLETED
- **Outcome:** An operator can create an academy contract from `/contracts/academy`; the form no longer asks for a contract version, contract language or manual student ID, and the academy student record/number is created automatically from the contract identity.

### 1. Requirements and acceptance criteria
- [x] Creating an academy contract succeeds in the browser from `/contracts/academy` with only identity, course and fee data.
- [x] The academy contract form and register do not show a contract version field, a contract language field, or a manually entered student ID.
- [x] The academy contract still resolves its hidden document server-side so the slip and historical snapshot keep working.
- [x] Saving an academy contract creates one `students` row from the applicant identity and assigns a generated student number; an existing student for the same applicant is reused.
- [x] Academy term creation has a computable duration/class term for the seeded catalogue, and a translated error when neither exists.
- [x] Non-academy service contracts keep their version/language/student-ID behaviour unchanged.
- [x] Focused backend tests and `npm run typecheck` pass; the Playwright flow confirms a created contract.

### 2. Scope and non-goals
- In scope: academy contract wizard fields and validation, academy register version column/filter, contract generation for academy, student creation/number generation, duration-month seed/backfill, relevant translations, focused tests.
- Out of scope: academy slip layout, contract template administration, other built-in services, class management UI, unrelated refactors, and the pre-existing case-free stage test mismatch.

### 3. Project findings
- Current behaviour before the change:
  - `POST /api/contracts/academy` returned `422 business.academy_term_undefined` when the selected duration had `months = null` and the class had no `ends_on`. Reproduced through Playwright at 1440 px as `admin@example.test`.
  - Seeded durations (`3 Months` / `6 Months`) had `months = null`; the original backfill ran before those seed rows existed.
  - The academy wizard showed "Matching contract version" and "Contract language", and `ApplicantServiceFields` showed a required "Student ID".
  - The register showed a "Contract version" column and filter for academy.
  - `AcademyEnrollmentService::studentFor()` created a `Student` from applicant identity but required the operator to supply `student_id`.
- Relevant paths/files:
  - `app/Http/Requests/StoreServiceContractRequest.php`
  - `app/Services/Contracts/ContractGenerationService.php`
  - `app/Services/AcademyEnrollmentService.php`
  - `database/seeders/EducationalReferenceSeeder.php`
  - `database/migrations/2026_11_09_000001_backfill_academy_duration_months.php`
  - `resources/ts/pages/service-contracts.vue`
  - `resources/ts/components/ApplicantServiceFields.vue`
  - `resources/ts/api/contracts.ts`
  - `resources/ts/plugins/i18n/locales/{en,fa-AF,ps-AF}.json`
  - `tests/Feature/AcademyContractTermTest.php`, `tests/Feature/BuiltInContractGenerationTest.php`

### 4. Graph and impact review
- Methods used: repository search and direct source inspection (no graph skill installed in this session); traced `StoreServiceContractRequest` → `ContractController::storeService` → `ContractGenerationService::create` → `AcademyEnrollmentService::createForLine` → `Student`/`AcademyEnrollment`, and `/contracts/academy` → `ApplicantServiceFields` → `ContractApi.createService`.
- Downstream considered: academy slip and certificate student number, enrollment list, contract snapshot `entity.academy.student_id`, academy register print columns, versioned-service feature tests.

### 5. Clarifications, decisions, and assumptions
- User decisions: academy has no selectable contracts/templates; only a slip is printed. Student ID should be automatic and a student record should be created from the contract.
- Decisions:
  - Academy ignores incoming version/language and resolves the hidden template's current active version on the server; `contract_language_id` stays null.
  - The wizard no longer sends version/language for academy and hides the version column/filter/print column.
  - A supplied `student_id` from older clients/tests is still accepted, but it is never required or shown in the academy contract wizard.
  - New student numbers are generated as `STU-000123` from the unique student row id; imported/legacy numbers are preserved.
  - The legacy `academy_applicant_details.student_id` is kept aligned with the student number.
  - Existing `3 Months` / `6 Months` durations are backfilled; unknown duration names stay null and the translated `academy_term_undefined` message explains the remaining gap.

### 6. Implementation plan
- [x] Make `student_id` and `contract_template_version_id` optional for academy in `StoreServiceContractRequest`.
- [x] Resolve the hidden academy template's current active version server-side and stop storing an academy contract language.
- [x] Generate/reuse the student number, create/update the `Student` from applicant identity and sync the applicant detail.
- [x] Populate duration months in `EducationalReferenceSeeder` and add an idempotent backfill migration.
- [x] Remove version/language/student-ID UI for academy and omit them from the create payload; hide the register version column/filter/print column.
- [x] Add `showStudentId` support to `ApplicantServiceFields` and remove the academy version slot; add the auto-generation hint.
- [x] Make `contract_template_version_id` and `language_id` optional in the frontend create type.
- [x] Add en/fa-AF/ps-AF translations for the hint and academy term errors.
- [x] Add the focused backend test for versionless/language-less/student-ID-less academy creation and generated student number.
- [x] Update the two `BuiltInContractGenerationTest` cases to test version validation on a versioned service (`test_offer`), since academy no longer selects a version.

### 7. Validation plan and results
- [x] `php artisan test --filter=AcademyContractTermTest` — 6 passed, 46 assertions.
- [x] `php artisan test --filter=AcademyMultiCourseTest` — 2 passed, 26 assertions.
- [x] `php artisan test --filter=BuiltInContractGenerationTest` — 16 passed, 216 assertions.
- [x] `php artisan test --filter=AcademySlipTest` — 2 passed, 20 assertions.
- [x] `php artisan test --filter=EducationalContractIntegrityTest` — 2 passed, 11 assertions.
- [x] `npm run typecheck` — exit 0.
- [x] `php artisan migrate --force` — the new duration-month backfill ran; `3 Months` → 3, `6 Months` → 6.
- [x] Playwright `/contracts/academy` creation:
  - Table headers: `["Contract number","Applicant name","Phone","Total fee","Start date","Status","Actions"]` (no Contract version).
  - Step 1 labels: `["Student full name *","Phone *","Consultant"]` (no version, language or student ID).
  - `POST /api/contracts/academy` returned **201**; no alert and the dialog closed.
- [x] Created data verified: contract version resolved (id 1), `contract_language_id = null`, enrollment term `2026-11-09 → 2027-02-09`, student `STU-000001` containing the contract identity, and `academy_applicant_details.student_id = STU-000001`.
- [ ] `npm run i18n:check` — exit 1 for pre-existing missing keys (`notifications.length` and settings/support keys); none of the newly added keys are missing.
- [ ] `npm run i18n:test` — fails on the pre-existing `notifications.length` missing English key.
- [ ] `php artisan test --filter=ApplicantContractStageTest` — 4 failed, 2 passed. Pre-existing mismatch: the test drives an academy contract through stages, while `ApplicantContractStageController` deliberately rejects case-free academy lines (`business.applicant_stage_service_not_supported`). Not caused by this change and left out of scope.

### 8. Risks and mitigations
- Generated number collision — derived from the unique student row id with a soft-deleted check.
- Existing clients still send a version/language/student ID — accepted (nullable) and ignored/honoured respectively.
- Existing durations with unknown names — left null; the translated error explains the contract cannot get a term until the class has an end date or the duration has months.
- Other services losing version selection — every register/form condition is guarded by `service === 'academy'`, and the shared versioned-service suite passes.

### 9. Progress log
- 2026-11-09 — DISCOVERY: Reproduced `422 business.academy_term_undefined` through Playwright; found null duration months and missing class end dates.
- 2026-11-09 — PLAN: Plan saved; implementation started.
- 2026-11-09 — PROGRESS: Backend version/student/term changes, seed/backfill, wizard cleanup, translations and focused tests implemented.
- 2026-11-09 — VALIDATION: Playwright creation returned 201; focused PHP suites and typecheck passed; i18n and case-free stage-suite limitations recorded.

### 10. Completion summary
- Delivered: Academy contracts are creatable again; academy no longer asks for/records contract version or language; the hidden document version is resolved server-side; a student record and generated student number are created from contract identity; duration months are seeded/backfilled; the academy register and print list no longer show contract versions.
- Acceptance criteria satisfied: all task-specific criteria above.
- Tests/checks passed: `AcademyContractTermTest`, `AcademyMultiCourseTest`, `BuiltInContractGenerationTest`, `AcademySlipTest`, `EducationalContractIntegrityTest`, `npm run typecheck`, and the Playwright creation flow.
- Remaining limitations: pre-existing `i18n:check`/`i18n:test` missing keys and the pre-existing `ApplicantContractStageTest` expectation that academy has stages.
