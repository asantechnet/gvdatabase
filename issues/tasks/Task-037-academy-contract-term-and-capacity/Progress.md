# Progress — Task-037

## Status snapshot
- Status: `Review`
- Owner: `Academy revamp implementation`
- Last updated: `2026-11-09`
- Current focus: `Delivered and verified on the merged academy branch`
- Blocker: `No code blocker. Remaining manual viewport/native-copy/migration-rehearsal checks are listed in the acceptance checklist.`
- Next action: `Review the merged UI at 390/768/1280 px in LTR/RTL and close the task.`

## Work completed with evidence
- Implemented the task contract. Shared Academy suites: 50 tests / 324 assertions passed.
- Route tree: `php artisan route:list --path=academy` exited 0 with 22 Academy routes.
- Print checks: attendance 7/7, certificate 9/9, slip regression passed.
- Typecheck: no Academy-file errors. Full `npm run typecheck` still exits 2 from pre-existing `staff_list.vue`, `support.vue` and `applicant-case.vue` errors.
- i18n: all Academy keys exist in en/fa-AF/ps-AF. `npm run i18n:check` still exits 1 for pre-existing unrelated missing keys outside Academy ownership.
- Not run: browser screenshots at 390/768/1280 px, native Dari/Pashto review, production build, working-data migration rehearsal.

## Acceptance criteria progress
- [x] Given an academy class with an end date and a duration with months, when an academy contract is created, then `contracts.start_date` is today, `contracts.end_date` is the earlier of the class end and start plus months, and one enrollment row with the same boundaries is linked to the new line.
- [x] Given an open-ended class and a three-month duration, when a contract is created, then the end date is exactly three calendar months after the start and no class end date is invented.
- [x] Given a duration with null months and a class with no end date, when creation is attempted, then it fails with 422 `business.academy_term_undefined`, and no contract, line, enrollment or income row is created.
- [x] Given a class whose `ends_on` is in the past, when creation is attempted, then it fails with 422 `business.academy_class_ended` and writes nothing.
- [x] Given a class at capacity with active enrollments, when another contract is attempted, then it fails with 422 `business.academy_class_full`; completed, withdrawn and cancelled enrollments do not consume a seat.
- [x] Given a full class and an update that only edits an existing enrollment's duration or notes, when the update is saved, then the current enrollment is excluded from the seat count and the update succeeds; changing to another full class still fails.
- [x] Given one visitor with an existing academy course, when a second academy contract is created for the same student, then a second contract, line and enrollment are created, the first enrollment and its contract snapshot are unchanged, and no academy applicant detail row is overwritten.
- [x] Given an academy contract created with `paid_fee > 0`, when the end date and enrollment are written, then the existing income receipt is still created once, its amount and line link are unchanged, and the contract snapshot contains the class, programme, time, duration, student, teachers and start/end dates.
- [x] Given an academy extension, when it is created, then the new line gets its own enrollment and end date, the parent contract and enrollment remain, and the extension snapshot records the new term.
- [x] Given a test-offer or academy contract, when it is created, then the party, contract, service line and money are written, `applicant.current_stage_id` and `line.current_stage_id` are null, no `applicant_stage_histories` row exists, no stage is looked up, and no stage-bearing case is exposed.
- [x] Given a scholarship or private contract created or updated with two responsible employees and the permission, when it succeeds, then two distinct pivot rows are stored, a rerun does not duplicate them, and the legacy `admission_employee_id` is not written; without the permission the request is 403 before any contract, line, pivot or notification write.
- [x] Given a test-offer/academy payload containing `responsible_employee_ids`, or an id outside the active admission-assignment scope, when validated, then it is refused with a field error and no contract, line, pivot or income is written.
- [x] Given a completed scholarship/private case with multiple responsible employees, when the contract is generated and commission sync runs, then accruals still come only from the seller/employee fields and their count and amount are identical to the single-responsibility fixture; responsible employees never become recipients.
- [x] Given the focused suites, when `php artisan test --filter=AcademyContractTermTest`, `php artisan test --filter=AcademyEnrollmentCapacityTest`, `php artisan test --filter=AcademyMultiCourseTest`, `php artisan test --filter=AcademyCaseFreeContractTest` and `php artisan test --filter=ResponsibleEmployeeAssignmentTest` run on an isolated database, then they pass.

## Checks run and results
| Date | Command/check | Result |
|---|---|---|
| 2026-11-08 | 15 focused Academy suites | 50 passed, 324 assertions |
| 2026-11-08 | `php artisan route:list --path=academy` | exit 0, 22 routes |
| 2026-11-08 | `node scripts/academy-attendance-print-regression.mjs` | passed 7/7 |
| 2026-11-08 | `node scripts/academy-certificate-print-regression.mjs` | passed 9/9 |
| 2026-11-08 | `node scripts/academy-slip-print-regression.mjs` | passed |
| 2026-11-08 | `npm run typecheck` | exit 2; known pre-existing files only |
| 2026-11-08 | `npm run i18n:check` | exit 1; known unrelated missing keys only |

## Handoff notes
- Route/nav wiring is delivered by Task-044. Task-045 still records the unresolved Task-046 dependency (case-free stage retirement and the remaining plural-responsibility UI/API surfaces).

## Files changed and why
- Product and test files listed under Task.md `## Exclusive file ownership` were implemented for this task.
- `app/Services/AcademyEnrollmentService.php`
- `app/Services/Contracts/ContractGenerationService.php`
- `app/Http/Requests/StoreServiceContractRequest.php`
- `database/migrations/2026_11_07_000008_create_applicant_responsible_employees.php`
- `tests/Feature/AcademyContractTermTest.php`
- `tests/Feature/AcademyEnrollmentCapacityTest.php`
- `tests/Feature/AcademyMultiCourseTest.php`
- `tests/Feature/AcademyCaseFreeContractTest.php`
- `tests/Feature/ResponsibleEmployeeAssignmentTest.php`

## Decisions and deviations
- Implementation follows the Task.md contract; no scope deviation was introduced. Shared locale keys, route files and API contracts were merged with the task-047 branch without dropping either side.

## Blockers and open questions
- No implementation blocker. Browser viewport/RTL/light-dark, native Dari/Pashto review and working-data migration rehearsal remain outstanding where the acceptance checklist leaves them unchecked.

## Activity log (append-only)
- `2026-11-09` — Task-037 verified on the merged academy branch after the task-047 merge; task status updated to Review.
