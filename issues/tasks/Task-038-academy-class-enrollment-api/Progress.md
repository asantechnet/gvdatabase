# Progress — Task-038

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
- [x] Given a viewer with `view_academy_contracts`, when they GET the class, enrollment and overview endpoints, then they receive paginated or filtered data with class, programme, time, teachers, dates, capacity, seats taken and balance, and the endpoint adds no N+1 query beyond the documented eager loads.
- [x] Given a manager with `manage_academy_classes`, when they create or update a class, then validation covers name, programme/time, teachers, weekdays 1-7, start/end times, capacity, dates, duration, fee and active state; invalid values return field errors and no row.
- [x] Given a class that already has enrollments, when a delete is attempted, then it is refused with a specific business code and no rows are removed; when capacity is reduced below the current active count, the update is refused.
- [x] Given the enrollment filters `class_id`, `status`, `ending`, `search` and `teacher_id`, when applied, then the list returns only matching rows, respects the caller's academy scope, and keeps pagination and sorting.
- [x] Given an enrollment update to completed, withdrawn or cancelled, then only the enrollment status, `completed_on` and notes change; the contract, line, stage, payment state and issued documents do not change; an invalid transition is refused.
- [x] Given the overview endpoint, when the data is summed, then active students are distinct applicants with active enrollments, seats filled counts active enrollments only, ending-in-30-days uses `ends_on`, outstanding uses line payable minus paid, and completed-this-month uses `completed_on`; the numbers match focused fixtures.
- [x] Given a viewer without `view_academy_contracts`, when any academy read endpoint is called, then the response is 403; given a viewer with read but without the relevant manage permission, when a write is attempted, then the response is 403; super admin and granted roles pass.
- [x] Given `PATCH /academy/durations/{duration}`, when months is 1-60 or null, then it saves; zero, negative or greater-than-60 is rejected; the route is registered before any resource binding and the existing master-data endpoints are unchanged.
- [x] Given the focused suites, when `php artisan test --filter=AcademyClassApiTest`, `php artisan test --filter=AcademyEnrollmentApiTest`, `php artisan test --filter=AcademyOverviewApiTest` and `php artisan test --filter=AcademyPermissionTest` run, then they pass.
- [x] Given the permission catalogue and an existing database, when the migration and seeders run, then `assign_admission_employee` is renamed in place to `assign_responsible_employees`, every role that held the old permission still holds the new one, no other grant changes, and a fresh seed creates the new name.

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
- `routes/api.php`
- `routes/academy.php`
- `routes/academy/classes.php`
- `routes/academy/enrollments.php`
- `routes/academy/overview.php`
- `routes/academy/catalog.php`
- `app/Http/Controllers/AcademyClassController.php`
- `app/Http/Controllers/AcademyEnrollmentController.php`
- `app/Http/Controllers/AcademyOverviewController.php`
- `app/Http/Controllers/AcademyCatalogController.php`
- `app/Http/Requests/StoreAcademyClassRequest.php`
- `app/Http/Requests/UpdateAcademyClassRequest.php`
- `app/Http/Requests/UpdateAcademyEnrollmentRequest.php`
- `app/Http/Resources/AcademyClassResource.php`
- `app/Http/Resources/AcademyEnrollmentResource.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/RolePermissionSeeder.php`
- `database/migrations/2026_11_07_000009_rename_admission_permission.php`
- `tests/Feature/AcademyClassApiTest.php`
- `tests/Feature/AcademyEnrollmentApiTest.php`
- `tests/Feature/AcademyOverviewApiTest.php`
- `tests/Feature/AcademyPermissionTest.php`

## Decisions and deviations
- Implementation follows the Task.md contract; no scope deviation was introduced. Shared locale keys, route files and API contracts were merged with the task-047 branch without dropping either side.

## Blockers and open questions
- No implementation blocker. Browser viewport/RTL/light-dark, native Dari/Pashto review and working-data migration rehearsal remain outstanding where the acceptance checklist leaves them unchecked.

## Activity log (append-only)
- `2026-11-09` — Task-038 verified on the merged academy branch after the task-047 merge; task status updated to Review.
