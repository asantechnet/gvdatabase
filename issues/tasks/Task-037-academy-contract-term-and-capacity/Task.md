# Task Task-037 — Academy contract generation: term, capacity and responsible employees

## Metadata
- Task ID: `Task-037`
- Title: `Academy contract generation: term, capacity and responsible employees`
- Initial status: `Ready`
- Type: `Backend`
- Priority: `P1`
- Owner role: `Laravel contract and domain implementer`
- Estimated size: `L`
- Source plan/version: `Academy revamp planning session 2026-11-07; client decisions: academy contracts need an end date, intake is rolling, one student may take several courses. Repository inspection 2026-11-07 of StoreServiceContractRequest, ContractGenerationService and AcademyEnrollment foundation.`
- Requirement / behavior IDs: `ACADEMY-CONTRACT-001`, `ACADEMY-CONTRACT-002`, `ACADEMY-CONTRACT-003`, `ACADEMY-CONTRACT-004`, `CASE-FREE-001`, `RESP-EMP-001`, `RESP-EMP-002`

## Objective
Every academy contract created through the existing Contracts → Academy form gets a server-computed end date and a linked enrollment in the selected class. A class at capacity refuses a new sale without writing a contract, line, enrollment or income. A second course for the same student creates a second contract and enrollment without overwriting the first. This task also owns the contract-generation boundary for the client's case-free decision: test-offer and academy contracts write a party, contract, line and money but no stage, stage history or pipeline case, while scholarship and private contracts accept plural responsible employees behind the renamed assignment permission. Commission math stays on the existing seller fields.

## Context and problem
`StoreServiceContractRequest` prohibits `start_date`, has no `end_date` rule for service contracts, and requires `student_id`, `program_id`, `time_id`, `duration_id`, `teacher_ids` and `class_id`. `ContractGenerationService` creates the contract with `start_date = now()` and never sets `end_date`. The academy class is a name-only catalogue, the duration is a name, and nothing counts seats. The contract resource already exposes `end_date`, `days_remaining` and `expiry_state`, so populating the column makes the existing register and expiry filter work without changing the resource.

The academy fee paid at signing already creates a real income and must stay exactly once. The contract snapshot preserves the issued document and must keep doing so. Only the live academy enrollment and the live contract end date change.

The client has since made test-offer and academy case-free: `ContractGenerationService::createApplicant()` still selects a `new` stage, writes `applicants.current_stage_id` and creates a stage-history row for both services. This task removes that write from the generation path; Task-046 retires the catalogue/editor/pipeline surface and owns the plural UI. The contract form's singular `admission_employee_id` also needs a plural contract-generation payload and permission gate here, because this task owns `StoreServiceContractRequest` and `ContractGenerationService`; Task-046 owns the resources, pages, tasks, notifications and placeholders.

## Scope
### Included
- Validate the selected academy class, duration and programme/time consistency in `StoreServiceContractRequest`.
- Compute the term in one service: start is the contract start date (today), end is the class `ends_on` when set, otherwise start plus the duration's `months`.
- Refuse a missing term (`business.academy_term_undefined`), an ended class (`business.academy_class_ended`) and a full class (`business.academy_class_full`) with 422 and no partial rows.
- Create exactly one enrollment per academy line, linked to the contract, line and class, with the same `started_on`/`ends_on` and `active` status.
- Set `contracts.end_date` for academy contracts and include the class, programme, time, duration, student, teachers and term in the contract snapshot.
- Recompute the term on an academy service update and allow moving to a class with a free seat; exclude the current enrollment from the seat count.
- Give an academy extension its own enrollment and term while preserving the parent contract, line and enrollment.
- For `test_offer` and `academy`, create the party and contract line with `current_stage_id = null`, write no `ApplicantStageHistory` row and perform no stage lookup; preserve scholarship/private stage creation exactly.
- Add an additive `applicant_responsible_employees` pivot with a unique applicant/employee pair, foreign keys and timestamps; backfill every non-null legacy `applicants.admission_employee_id` once and retain the legacy column.
- Accept `responsible_employee_ids` on scholarship/private contract create and update, require `assign_responsible_employees`, validate active admission-assignment employees, sync the pivot and never write the legacy column. Reject the key for test-offer/academy.
- Add focused feature tests for term calculation, capacity, multiple courses, the paid-fee income, the no-stage boundary, the plural pivot/permission and unchanged commission accruals.

### Excluded
- New API endpoints, class CRUD, overview reads, the permission catalogue/rename, UI, navigation, attendance, certificates or localization. Those belong to Task-038 through Task-044.
- Changing `CommissionService`, commission recipients or settled accruals; Task-016 and Task-046 own those, and this task only proves the seller fields still produce the same result.
- Editing `ApplicantServiceDetails`, `ContractResource`, `ContractController`, `routes/api.php` or any Vue file.
- Auto-completing a contract when the end date passes; no scheduler, no status side effect.
- Changing fee, discount, currency, rate, payment or income semantics; the existing `paid_fee` path stays the only receipt writer.
- Changing the hidden academy contract template body; Task-042 owns the printed term line.

## Preconditions and assumptions
- Task-036 has created `academy_enrollments`, the class fields and `durations.months`.
- The existing service-contract route `POST /contracts/academy` and update route remain the only creation/update entry points.
- A rolling class has `ends_on = null`; an ended class has `ends_on` before today. Capacity null means unlimited.
- The duration month count is a calendar-month count, not a fixed day count; use PHP date arithmetic that handles month-end correctly.
- The existing `paid_fee` income is created inside the same database transaction and must never be duplicated or rolled back independently.
- A contract line has at most one enrollment; the new row is created only after the line exists.
- The product owner has not asked for a manual end-date field; the date is derived and is not accepted from the client.
- Task-038 has renamed `assign_admission_employee` to `assign_responsible_employees` in place and preserved every role grant; this task consumes the new name and does not edit the permission catalogue or its seeder.
- The new pivot is additive; the legacy `admission_employee_id` column remains for rollback and history but is never read or written by the new contract-generation path.
- "No case" means no stage-bearing workflow record, stage history or pipeline card. The `applicants` party row remains because contracts, documents, payments and enrollments depend on it; if the client means no party row at all, stop for a separate counterparty-model decision.

## Dependencies
- `Task-036` — provides the enrollment table, class fields, duration months and models this task writes through.
- `Task-038` — provides the in-place `assign_responsible_employees` permission and preserved role grants this task checks; Task-038 does not depend on this task.

## Contracts and boundaries
- Input: the existing academy service-contract payload. `end_date` and `start_date` remain prohibited from the client; the server computes both from the clock, the class and the duration. Scholarship/private payloads may include `responsible_employee_ids`; test-offer/academy payloads must not.
- Output: `contracts.start_date` = today, `contracts.end_date` = resolved term, one `academy_enrollments` row linked to the new `applicant_contract` line, and a snapshot `academy` block with `class_id`, `class_name`, `program`, `time`, `duration`, `student_id`, `teachers`, `started_on` and `ends_on`.
- Case-free output: for `test_offer` and `academy`, the applicant and line keep `current_stage_id = null`, no `applicant_stage_histories` row is created and no stage is resolved or required. Scholarship/private continue to write their stage exactly as before.
- Responsibility output: for scholarship/private, one pivot row per distinct active employee in `responsible_employee_ids`; the legacy `admission_employee_id` column is not written. Without `assign_responsible_employees`, a submitted list or change is 403 before any contract, line, pivot, income or notification write.
- Capacity: count enrollments with status `active` for the target class, excluding the enrollment being updated. If `capacity` is not null and the count is greater than or equal to it, refuse the sale.
- Term precedence: class `ends_on` wins over the duration calculation; the duration calculation is `started_on + months`; if both are null, refuse. A term that starts after the class end is invalid.
- Update: moving an academy enrollment to another class or duration recomputes the end date; the issued document snapshot is not rewritten. A completed/withdrawn/cancelled enrollment does not consume a seat.
- Extension: `parent_contract_id` keeps the existing validation; the new line and enrollment are created, and the parent detail/enrollment is never updated or overwritten.
- Error behaviour: a refusal returns the specific business code, writes no contract, line, enrollment or income row, and leaves the parent record untouched.
- Files this task must not modify: `app/Http/Resources/ContractResource.php`, `app/Http/Controllers/ContractController.php`, `app/Models/Applicant.php`, `app/Services/CommissionService.php`, `routes/api.php`, `resources/ts/**`, `database/seeders/AcademyContractTemplateSeeder.php`, `app/Services/AcademySlipService.php`, `lang/**`.

## Exclusive file ownership
- `app/Services/AcademyEnrollmentService.php` — single term/capacity/write service used by create and update.
- `app/Services/Contracts/ContractGenerationService.php` — academy term wiring, case-free test/academy creation and plural responsible-employee sync/permission; preserve scholarship/private stages and seller fields.
- `app/Http/Requests/StoreServiceContractRequest.php` — plural `responsible_employee_ids` rules, service restriction and permission.
- `database/migrations/2026_11_07_000008_create_applicant_responsible_employees.php` — additive pivot and one-time legacy backfill.
- `tests/Feature/AcademyContractTermTest.php` — term precedence, month arithmetic, missing term, ended class and legacy fallback.
- `tests/Feature/AcademyEnrollmentCapacityTest.php` — full class, update exclusion, non-active statuses and transactional refusal.
- `tests/Feature/AcademyMultiCourseTest.php` — second course, extension, snapshot preservation, paid-fee income and no detail overwrite.
- `tests/Feature/AcademyCaseFreeContractTest.php` — test/academy no-stage/history, scholarship/private stage preservation and pivot backfill.
- `tests/Feature/ResponsibleEmployeeAssignmentTest.php` — plural payload, permission 403, pivot sync and seller-only commission regression.

Task-046 consumes this contract-generation boundary for stages, resources, tasks, notifications, placeholders and UI; it does not edit these files.

## Acceptance criteria
- [ ] Given an academy class with an end date and a duration with months, when an academy contract is created, then `contracts.start_date` is today, `contracts.end_date` is the earlier of the class end and start plus months, and one enrollment row with the same boundaries is linked to the new line.
- [ ] Given an open-ended class and a three-month duration, when a contract is created, then the end date is exactly three calendar months after the start and no class end date is invented.
- [ ] Given a duration with null months and a class with no end date, when creation is attempted, then it fails with 422 `business.academy_term_undefined`, and no contract, line, enrollment or income row is created.
- [ ] Given a class whose `ends_on` is in the past, when creation is attempted, then it fails with 422 `business.academy_class_ended` and writes nothing.
- [ ] Given a class at capacity with active enrollments, when another contract is attempted, then it fails with 422 `business.academy_class_full`; completed, withdrawn and cancelled enrollments do not consume a seat.
- [ ] Given a full class and an update that only edits an existing enrollment's duration or notes, when the update is saved, then the current enrollment is excluded from the seat count and the update succeeds; changing to another full class still fails.
- [ ] Given one visitor with an existing academy course, when a second academy contract is created for the same student, then a second contract, line and enrollment are created, the first enrollment and its contract snapshot are unchanged, and no academy applicant detail row is overwritten.
- [ ] Given an academy contract created with `paid_fee > 0`, when the end date and enrollment are written, then the existing income receipt is still created once, its amount and line link are unchanged, and the contract snapshot contains the class, programme, time, duration, student, teachers and start/end dates.
- [ ] Given an academy extension, when it is created, then the new line gets its own enrollment and end date, the parent contract and enrollment remain, and the extension snapshot records the new term.
- [ ] Given a test-offer or academy contract, when it is created, then the party, contract, service line and money are written, `applicant.current_stage_id` and `line.current_stage_id` are null, no `applicant_stage_histories` row exists, no stage is looked up, and no stage-bearing case is exposed.
- [ ] Given a scholarship or private contract created or updated with two responsible employees and the permission, when it succeeds, then two distinct pivot rows are stored, a rerun does not duplicate them, and the legacy `admission_employee_id` is not written; without the permission the request is 403 before any contract, line, pivot or notification write.
- [ ] Given a test-offer/academy payload containing `responsible_employee_ids`, or an id outside the active admission-assignment scope, when validated, then it is refused with a field error and no contract, line, pivot or income is written.
- [ ] Given a completed scholarship/private case with multiple responsible employees, when the contract is generated and commission sync runs, then accruals still come only from the seller/employee fields and their count and amount are identical to the single-responsibility fixture; responsible employees never become recipients.
- [ ] Given the focused suites, when `php artisan test --filter=AcademyContractTermTest`, `php artisan test --filter=AcademyEnrollmentCapacityTest`, `php artisan test --filter=AcademyMultiCourseTest`, `php artisan test --filter=AcademyCaseFreeContractTest` and `php artisan test --filter=ResponsibleEmployeeAssignmentTest` run on an isolated database, then they pass.

## Verification plan
- Automated: run the five focused feature suites above on an isolated database; assert contract/line/enrollment/income counts on every refusal path and no stage/history rows for the case-free services.
- Term fixtures: a closed class with an end before the duration would end, an open class with 1/3/12-month durations, a month-end start, a missing-months duration and an ended class.
- Capacity fixtures: capacity 1 with one active enrollment, one completed enrollment, one withdrawn enrollment and an update of the existing enrollment; assert the exact count and the refusal code.
- Multi-course fixtures: one applicant with two academy contracts, one extension and an applicant detail row captured before/after; assert the first enrollment and snapshot are byte-for-byte unchanged.
- Income check: create with `paid_fee` 20000 and assert exactly one `incomes` row with the same line; assert no second row on the update path.
- Regression: run the existing built-in contract generation, service contract start date, academy slip and `CommissionLifecycleTest` suites; confirm no start-date client rule changed and seller-only accrual amounts are unchanged.
- Evidence: test output, refusal response samples, before/after row ids and the snapshot term block.

## Implementation notes
Put the date and capacity rules in `AcademyEnrollmentService`, not in the controller or the Vue form. Recompute inside the same `DB::transaction` as the contract and line, and lock the class row (`lockForUpdate`) before counting seats so two concurrent sales cannot both take the last place. Use `CarbonImmutable` and `addMonthsNoOverflow`; do not use fixed 30-day arithmetic.

Resolve the class directly from `class_id`; validate that the submitted `program_id` and `time_id` match the class when the class has them, and keep accepting the current fields for older clients. Read `duration.months` from the selected duration; do not parse the duration name at sale time. For an update, load the line's enrollment, exclude its id from the seat count and update the same row. For an extension, create a new line and enrollment; do not call the details sync path that overwrites the parent's course fields.

Set `end_date` on the contract before the snapshot render, and add the academy block to `snapshot_data.entity` or a dedicated `academy` key only if the existing snapshot consumer tolerates it. Do not change the resource shape; `ContractResource` already exposes the column.

For the case-free path, branch on the built-in service before selecting a stage: set `current_stage_id = null` on the new applicant and line, skip `ApplicantStageHistory`, and do not call `ApplicantStageContext` for `test_offer`/`academy`. Keep the existing scholarship/private branch untouched. A stage-less party is still a counterparty, not a pipeline card.

For responsibility, authorize `assign_responsible_employees` before the transaction, validate distinct ids against the active admission assignment scope, and sync the pivot inside the same transaction as the contract/line. Use `insertOrIgnore` or a unique pair so a retry does not duplicate rows; never write `admission_employee_id` again. The case-free services must reject the key rather than silently dropping it.

## Risks and rollback
- Risk: a class has no capacity but more active enrollments than the owner expects. Mitigation: null means unlimited; do not backfill a guessed capacity.
- Risk: timezone/date drift changes the term by one day. Mitigation: use the application timezone consistently and date-only columns; assert the exact `Y-m-d` strings in tests.
- Risk: an update moves a student into a full class. Mitigation: count seats under the same lock and exclude the current enrollment.
- Risk: the paid-fee income is created twice on retry. Mitigation: keep creation inside the transaction and assert one row in tests.
- Risk: a case-free path accidentally writes a stage or history row. Mitigation: branch before stage selection, assert null refs and zero history rows for both services.
- Risk: a plural assignment changes commission recipients. Mitigation: keep commission inputs on the seller/employee fields and assert identical accruals with one and two responsible employees.
- Risk: the pivot backfill duplicates or touches the legacy column. Mitigation: unique applicant/employee pair, one-time backfill and a second-run assertion.
- Rollback: revert the request/service changes; the additive columns, pivot and enrollment rows can remain without breaking the old creation path, but new enrollments should be reviewed before rollback.
- Stop condition: if an existing class has more active enrollments than a newly entered capacity, stop and report the class rather than deleting or reassigning students.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Capacity and term refusal codes are documented for Task-043 localization.
- [ ] Task-046 consumes the case-free contract boundary and pivot; Task-038 owns the renamed permission this task checks.
- [ ] Remaining limitations and follow-up tasks are explicit.