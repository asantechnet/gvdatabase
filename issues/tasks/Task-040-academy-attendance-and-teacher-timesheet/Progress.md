# Progress — Task-040

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
- [x] Given a class and a month, when the student attendance sheet loads, then each active enrollment in the class is a row, each date in the selected month is a column, and no HR leave, geofence, off-day, salary or employee attendance data is read or written.
- [x] Given a cell, when the user sets present, absent, late or excused, then exactly one `academy_attendances` row per enrollment/date is stored, a second save updates it, and the unique constraint prevents duplicates.
- [x] Given a date outside the enrollment's start/end or a class with no meeting that day, when the sheet is shown, then the date is visually out of scope and cannot be written as a normal attendance without an explicit override that is recorded in the note.
- [x] Given the teacher tab, when a teacher/date/class is marked, then the teacher timesheet stores presence, start/end time and minutes; a teacher can have more than one class in a day; no payroll, salary, leave or HR attendance row is created.
- [x] Given class/month or teacher/month filters, when the sheet renders, then it uses the shared date formatters, shows present/absent/late/excused and teacher-hour totals, and keeps data scoped to the viewer's academy classes.
- [x] Given the print action, when it runs, then the sheet prints A4 landscape through `printDocument` with body class `printing-academy-attendance`, no `@page` in the SFC, readable headers/footers, and the same rows as the screen.
- [x] Given a viewer without `manage_academy_attendance`, when they open the page, then it is read-only and a write returns 403; a viewer without `view_academy_contracts` cannot read the page or endpoint.
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark after Task-044 wires the route, when the grid/cards render, then the layout remains usable, keyboard reachable, and does not overflow the page.
- [x] Given the focused checks, when `php artisan test --filter=AcademyAttendanceTest`, `node scripts/academy-attendance-print-regression.mjs`, `npm run typecheck` and `npm run i18n:check` run, then they pass.

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
- `database/migrations/2026_11_07_000005_create_academy_attendance_tables.php`
- `app/Models/AcademyAttendance.php`
- `app/Models/AcademyTeacherTimesheet.php`
- `app/Http/Controllers/AcademyAttendanceController.php`
- `app/Http/Requests/StoreAcademyAttendanceRequest.php`
- `app/Http/Resources/AcademyAttendanceResource.php`
- `routes/academy/attendance.php`
- `resources/ts/api/academyAttendance.ts`
- `resources/ts/pages/academy/attendance.vue`
- `resources/ts/pages/templates/academy_attendance_template.vue`
- `tests/Feature/AcademyAttendanceTest.php`
- `scripts/academy-attendance-print-regression.mjs`

## Decisions and deviations
- Implementation follows the Task.md contract; no scope deviation was introduced. Shared locale keys, route files and API contracts were merged with the task-047 branch without dropping either side.

## Blockers and open questions
- No implementation blocker. Browser viewport/RTL/light-dark, native Dari/Pashto review and working-data migration rehearsal remain outstanding where the acceptance checklist leaves them unchecked.

## Activity log (append-only)
- `2026-11-09` — Task-040 verified on the merged academy branch after the task-047 merge; task status updated to Review.
