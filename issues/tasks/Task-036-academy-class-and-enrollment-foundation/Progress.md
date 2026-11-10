# Progress — Task-036

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
- [x] Given a database with the existing `academy_classes`, `durations`, `academy_applicant_details` and academy contracts, when the migrations run, then the new columns and `academy_enrollments` exist, every existing row is retained, and no existing academy detail, contract, line, income or snapshot is changed.
- [x] Given the same database, when the backfill runs, then each existing academy detail that has a class produces at most one enrollment, linked to the matching academy contract line when that link is unambiguous, with student, program, time, duration and class copied and no invented values.
- [x] Given a detail whose academy contract or line cannot be resolved, when the backfill runs, then no enrollment is attached to an unrelated contract, the detail remains readable, and the unresolved case is reported with its applicant id.
- [x] Given an applicant with two academy contract lines, when both enrollments are created, then both rows persist for the same applicant, the line-level unique constraint rejects a duplicate enrollment on one line, and neither course overwrites the other.
- [x] Given durations named `3 months`, `6 Months` and `Beginner`, when the duration-month backfill runs, then the first two store 3 and 6, the unknown value stays null, and a report lists it; running the backfill again does not duplicate or change a known value.
- [x] Given a class with weekdays, start/end times, capacity, dates and default duration, when the model is saved and reloaded, then the JSON array, date casts, integer capacity and nullable end date round-trip correctly and its `enrollments` relation returns only its own rows.
- [x] Given a duration with `months = null`, when it is saved, then the value remains null and no default month count is invented; given a negative, zero or greater-than-60 value, then validation or the database constraint rejects it.
- [x] Given the new feature tests, when `php artisan test --filter=AcademyEnrollmentFoundationTest` and `php artisan test --filter=AcademyEnrollmentBackfillTest` run on an isolated database, then all assertions pass, and the migrations can be rolled back and re-run without duplicate enrollment rows.

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
- `database/migrations/2026_11_07_000003_extend_academy_classes_and_create_enrollments.php`
- `database/migrations/2026_11_07_000004_backfill_academy_enrollments.php`
- `app/Models/AcademyClass.php`
- `app/Models/AcademyEnrollment.php`
- `app/Models/Duration.php`
- `database/factories/AcademyClassFactory.php`
- `database/factories/AcademyEnrollmentFactory.php`
- `tests/Feature/AcademyEnrollmentFoundationTest.php`
- `tests/Feature/AcademyEnrollmentBackfillTest.php`

## Decisions and deviations
- Implementation follows the Task.md contract; no scope deviation was introduced. Shared locale keys, route files and API contracts were merged with the task-047 branch without dropping either side.

## Blockers and open questions
- No implementation blocker. Browser viewport/RTL/light-dark, native Dari/Pashto review and working-data migration rehearsal remain outstanding where the acceptance checklist leaves them unchecked.

## Activity log (append-only)
- `2026-11-09` — Task-036 verified on the merged academy branch after the task-047 merge; task status updated to Review.
