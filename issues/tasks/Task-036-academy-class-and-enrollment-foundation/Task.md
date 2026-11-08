# Task Task-036 — Add academy class fields, duration months and enrollments

## Metadata
- Task ID: `Task-036`
- Title: `Add academy class fields, duration months and enrollments`
- Initial status: `Ready`
- Type: `Data`
- Priority: `P1`
- Owner role: `Laravel data-model implementer`
- Estimated size: `L`
- Source plan/version: `Academy revamp planning session 2026-11-07; client decisions: academy is a standalone side business fed by visitors, rolling intake is preferred, one student may take several courses, current academy catalogs and slip stay, and certificates are required. Repository inspection 2026-11-07.`
- Requirement / behavior IDs: `ACADEMY-DATA-001`, `ACADEMY-DATA-002`, `ACADEMY-DATA-003`

## Objective
Add the durable academy class fields, the duration length and the enrollment table that let one visitor hold several academy courses and let every academy contract carry a real term. The change is additive: every existing class, duration, applicant detail, contract, line, income and issued snapshot remains readable and unchanged.

## Explicit student-table requirement
- **Decision (2026-11-07):** This task must add an individual `students` table. A student is a durable Academy person record, not an applicant and not an enrollment. `applicants` remains the visitor/customer/case party, `academy_applicant_details` remains the per-case academy detail, and `academy_enrollments` remains one course for one student.
- The table must have its own primary key and a nullable `applicant_id` origin link, plus the academy student number currently held in `academy_applicant_details.student_id`. One student may have several enrollments; do not create a second student row per course.
- `academy_enrollments` must reference the student record. Do not use `applicant_id` or the enrollment row as the student identity. Preserve duplicate legacy student numbers as data-quality findings rather than merging different people.
- This requirement supersedes the earlier applicant-only enrollment identity and Task-046's blanket “second customer/student table” exclusion for an Academy student profile.
- **Before implementation:** reconcile this decision with the Scope, Preconditions, Contracts and boundaries, Exclusive file ownership, Acceptance criteria and downstream Task-037/Task-038/Task-039/Task-041 contracts below. The current applicant-only criteria must not be implemented unchanged.

## Context and problem
Academy is currently a contract with five catalogue selections. `academy_classes` is a name-only catalogue (`name`, `description`, `sort_order`, `is_active`), `durations` is a name-only catalogue, and `academy_applicant_details` is a 1:1 row per applicant case holding `student_id`, `program_id`, `time_id`, `duration_id` and `class_id`. There is no class capacity, weekday, start/end date, default fee or enrollment record, so the workspace cannot list a roster, show a term, fill seats or prove completion.

The existing detail row is the per-case course record and must not be repurposed or deleted. The enrollment is a new operational record linked to the contract line; it is the stable unit the Academy workspace, attendance and certificates will read. The hidden static academy contract template and the A5 slip are client-approved and are not changed here.

## Scope
### Included
- Extend `academy_classes` with nullable `program_id`, `time_id`, `starts_on`, `ends_on`, `weekdays` (JSON array of ISO weekdays 1-7), `starts_at`, `ends_at`, `capacity`, `duration_id` and `fee`.
- Add nullable `months` (1-60) to `durations` and backfill it from names that state a month count.
- Create `academy_enrollments` with `applicant_id`, `contract_id` (nullable for legacy), `applicant_contract_id` (nullable, unique when present), `class_id`, `student_id`, `program_id`, `time_id`, `duration_id`, `started_on`, `ends_on`, `status` (`active`, `completed`, `withdrawn`, `cancelled`), `completed_on`, `notes`, timestamps and soft deletes.
- Backfill one enrollment per existing `academy_applicant_details` row that has a class, using the matching academy contract line only when the link is unambiguous.
- Add the models, casts, relations and factories needed by the later tasks.
- Add isolated feature coverage for the schema, backfill, relations and the many-courses rule.

### Excluded
- Computing contract end dates, enforcing capacity, creating enrollments during a sale, any controller, request, route or resource. Those belong to Task-037 and Task-038.
- Attendance, certificates, navigation, UI, localization, slip or printed-contract changes. Those belong to Task-039 through Task-044.
- Dropping, renaming or repurposing `academy_applicant_details`, `academy_classes` or `durations`, or deleting any existing row.
- Syncing enrollment status to contract status, stage, payment or finance state.
- Inventing a month count for a duration name that does not state one.

## Preconditions and assumptions
- The database contains the current academy tables and, on a working installation, existing academy details and contracts.
- The test environment can run migrations on an isolated SQLite or MySQL database; no production data is changed by this task.
- `academy_classes.name` is unique today; new fields are nullable so existing name-only rows remain valid.
- A duration's `months` is a course length, not a billing period. Unknown values stay null and are reported, never guessed.
- One `applicant_contract` line represents one academy course; one line therefore has at most one enrollment.
- The `weekdays` payload is a JSON array such as `[1,3,5]`; JSON support is required in the target database.

## Dependencies
- `None` — this is the foundation task and has no upstream task.

## Contracts and boundaries
- One applicant may have many enrollments across separate contracts; the applicant detail row is not used as the enrollment table.
- `academy_enrollments.applicant_contract_id` is unique when present and nullable for legacy rows whose line cannot be resolved. A second enrollment on the same line is a data error.
- `academy_enrollments.contract_id` is nullable only for backfilled legacy rows. Task-037 always sets it for new rows.
- `academy_classes.capacity` null means unlimited. `ends_on` null means open-ended/rolling. `starts_at` and `ends_at` are nullable `H:i:s` strings. `fee` null means the class has no default fee.
- `durations.months` null means unknown. A non-null value must be between 1 and 60; no default is invented.
- The backfill must be idempotent: a second run creates no duplicate enrollment and changes no existing enrollment.
- Files this task must not modify: `app/Services/ApplicantServiceDetails.php`, `app/Services/Contracts/ContractGenerationService.php`, `app/Http/Requests/StoreServiceContractRequest.php`, `app/Http/Resources/ContractResource.php`, `resources/ts/pages/templates/academy_slip_template.vue`, `database/seeders/AcademyContractTemplateSeeder.php`, any controller, route, Vue page or locale file.

## Exclusive file ownership
- `database/migrations/2026_11_07_000003_extend_academy_classes_and_create_enrollments.php` — class fields, duration months and the enrollment table.
- `database/migrations/2026_11_07_000004_backfill_academy_enrollments.php` — idempotent detail-to-enrollment backfill and duration-month derivation.
- `app/Models/AcademyClass.php` — fillable fields, casts and enrollment relation.
- `app/Models/AcademyEnrollment.php` — new model with applicant, contract, line, class, program, time and duration relations.
- `app/Models/Duration.php` — months fillable field and cast.
- `database/factories/AcademyClassFactory.php` — realistic class fixtures.
- `database/factories/AcademyEnrollmentFactory.php` — realistic enrollment fixtures.
- `tests/Feature/AcademyEnrollmentFoundationTest.php` — schema, casts, relations and many-courses coverage.
- `tests/Feature/AcademyEnrollmentBackfillTest.php` — backfill, idempotence, unresolved links and duration-month coverage.

## Acceptance criteria
- [ ] Given a database with the existing `academy_classes`, `durations`, `academy_applicant_details` and academy contracts, when the migrations run, then the new columns and `academy_enrollments` exist, every existing row is retained, and no existing academy detail, contract, line, income or snapshot is changed.
- [ ] Given the same database, when the backfill runs, then each existing academy detail that has a class produces at most one enrollment, linked to the matching academy contract line when that link is unambiguous, with student, program, time, duration and class copied and no invented values.
- [ ] Given a detail whose academy contract or line cannot be resolved, when the backfill runs, then no enrollment is attached to an unrelated contract, the detail remains readable, and the unresolved case is reported with its applicant id.
- [ ] Given an applicant with two academy contract lines, when both enrollments are created, then both rows persist for the same applicant, the line-level unique constraint rejects a duplicate enrollment on one line, and neither course overwrites the other.
- [ ] Given durations named `3 months`, `6 Months` and `Beginner`, when the duration-month backfill runs, then the first two store 3 and 6, the unknown value stays null, and a report lists it; running the backfill again does not duplicate or change a known value.
- [ ] Given a class with weekdays, start/end times, capacity, dates and default duration, when the model is saved and reloaded, then the JSON array, date casts, integer capacity and nullable end date round-trip correctly and its `enrollments` relation returns only its own rows.
- [ ] Given a duration with `months = null`, when it is saved, then the value remains null and no default month count is invented; given a negative, zero or greater-than-60 value, then validation or the database constraint rejects it.
- [ ] Given the new feature tests, when `php artisan test --filter=AcademyEnrollmentFoundationTest` and `php artisan test --filter=AcademyEnrollmentBackfillTest` run on an isolated database, then all assertions pass, and the migrations can be rolled back and re-run without duplicate enrollment rows.

## Verification plan
- Automated: run `php artisan test --filter=AcademyEnrollmentFoundationTest` and `php artisan test --filter=AcademyEnrollmentBackfillTest` on an isolated database; assert schema columns, unique line constraint, relation counts, cast round-trips and idempotence.
- Migration check: run `php artisan migrate` and `php artisan migrate:rollback --step=2` on a disposable database seeded with at least three academy details, two classes of the same program and one unmatched detail; confirm no existing detail/contract row count changes after the forward run.
- Data check: compare `academy_applicant_details` row counts and contract/line/income counts before and after; assert one enrollment per detail with a class and a reported count for unresolved rows.
- Fixture check: create one applicant with two academy contracts/lines and confirm both enrollments persist; attempt a duplicate line and assert the database refuses it.
- Duration check: seed `3 months`, `6 Months`, `Beginner` and `12 weeks`; assert 3, 6, null and null, with the unknown rows listed in the command output.
- Regression: run the existing academy slip test and the contract generation suite to prove the additive migration changed no existing behavior.
- Evidence: migration output, before/after counts, test output and the unresolved-detail report.

## Implementation notes
Use two migrations so the schema change and the data backfill can be reviewed and re-run separately. In the schema migration, add nullable foreign keys with `nullOnDelete` or `restrictOnDelete` consistent with the existing academy catalogue style; do not add a required column to an existing table. In the backfill, query details in chunks, resolve the academy line by the applicant's contract where exactly one non-cancelled academy line exists, and skip ambiguous cases with a collected report. Derive months only from a strict pattern such as `^(\d{1,2})\s*months?$` after trimming; never parse a range or a marketing phrase.

Use `$table->unique('applicant_contract_id')`; MySQL permits several nulls, which is required for legacy rows. Cast `weekdays` to `array`, `starts_on`/`ends_on`/`completed_on` to `date:Y-m-d`, `capacity` to integer and `fee` to a decimal string. Keep the `AcademyApplicantDetail` model untouched; enrollment relations hang off the new model and, where useful, off `AcademyClass` only.

## Risks and rollback
- Risk: the backfill attaches an enrollment to the wrong line when an applicant has several academy contracts. Mitigation: only link when exactly one non-cancelled academy line is found; otherwise leave the line null and report it.
- Risk: duplicate `student_id` values or duplicate class names make the data ambiguous. Mitigation: report duplicates and never merge rows; the workspace groups by the recorded student id.
- Risk: SQLite JSON behaviour differs from MySQL. Mitigation: test the cast on the project's actual test driver and keep `weekdays` nullable.
- Rollback: `php artisan migrate:rollback --step=2` drops the enrollment table and the new columns. On a disposable database only; do not run a destructive rollback against working data without a verified backup.
- Stop condition: if the existing data contains more unresolved details than the agreed review threshold, stop before enforcing any new required link and return the report to the product owner.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Migration and backfill counts are recorded, including unresolved cases.
- [ ] Remaining limitations and follow-up tasks are explicit.
- [ ] Task is ready for Task-037 and Task-038, not merely "code complete."
