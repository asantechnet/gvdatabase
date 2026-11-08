# Task Task-040 — Add academy student attendance and the teacher timesheet

## Metadata
- Task ID: `Task-040`
- Title: `Add academy student attendance and the teacher timesheet`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Laravel and Vue full-stack implementer`
- Estimated size: `L`
- Source plan/version: `Academy revamp planning session 2026-11-07; client employee decision: a shallow copy of the current timesheet for teachers and students is good. Repository inspection of timesheet.vue, timesheet_template.vue, Attendance and the academy enrollment foundation on 2026-11-07.`
- Requirement / behavior IDs: `ACADEMY-ATTENDANCE-001`, `ACADEMY-ATTENDANCE-002`, `ACADEMY-ATTENDANCE-003`

## Objective
Give the Academy section a monthly attendance sheet for students and a shallow teacher timesheet. Teachers and office staff can mark presence for a class roster and record teacher hours without touching HR attendance, leave, payroll, geofencing or the employee timesheet. The sheet prints A4 landscape through the existing print workflow.

## Context and problem
The HR timesheet is an employee-only monthly calendar with check-in/out, leave, off-days, geofencing, overtime and payroll implications. Academy has no attendance model at all: the enrollment table records a course, not a day. The client asked for "a shallow copy of the current timesheet for teachers/students", meaning the same monthly grid/print experience, not the HR rules. A full copy would wrongly create salary, leave and off-day side effects.

This task adds separate academy tables and a separate page. It reuses the visual language and `printDocument`, not the HR service layer. Student attendance belongs to an enrollment and date; teacher time belongs to a teacher, date and class. Neither writes an `attendances`, `salaries`, `leaves` or `off_days` row.

## Scope
### Included
- Migration for `academy_attendances` (enrollment, class, date, status `present`/`absent`/`late`/`excused`, minutes, note, recorded by) with a unique enrollment/date pair.
- Migration for `academy_teacher_timesheets` (teacher, class, date, status, start/end time, minutes, note, recorded by) with a unique teacher/class/date triple.
- Models, controller, request, resource and route file for monthly reads, batch cell updates, and teacher rows.
- Student attendance page with month/class filters, roster rows, day columns, status cycling, bulk mark and totals.
- Teacher tab with month/teacher filters, class/date rows, presence and hours totals.
- A4 landscape print template using `printDocument` with `printing-academy-attendance`; no `@page` in the SFC.
- Permission checks: read with `view_academy_contracts`, write with `manage_academy_attendance`.
- Feature tests for uniqueness, scope, status validation, teacher multi-class days and print payloads.
- A print regression script for the new sheet.

### Excluded
- HR attendance, leave, off-days, check-in/out, geofencing, overtime, payroll, salary deductions or daily reports.
- A timetable generator or automatic session materialization. A class weekday is a display hint, not a scheduler.
- Student self-service attendance, teacher login/portal, notifications or SMS.
- Certificate eligibility or grades. Task-041 owns certificates; attendance is not a gate unless the product owner later asks.
- Routes and navigation wiring. Task-044 owns `routes.ts` and the top nav; this task owns only its API route file and page.
- Locale file edits. Task-043 owns the copy.

## Preconditions and assumptions
- Task-036 has created `academy_enrollments` and the class fields; Task-037 has active enrollments with start/end dates.
- Task-038 has created the academy API route tree and the `manage_academy_attendance` permission.
- Task-039 has created the Academy pages and the `academy.*` key namespace; this page follows the same layout conventions.
- A class can meet on several weekdays; a student can be enrolled after the class start and can complete before the class end.
- A teacher is a `teachers` catalogue row, not an `employees` row. Teacher time is informational, not payroll.
- The print host is rendered on the page and hidden on screen; the print body class owns its `@media print` selectors.
- A month grid can be large; the API returns only the selected class/month (students) or teacher/month (teachers), not a full history.

## Dependencies
- `Task-036` — enrollment and class data.
- `Task-038` — academy route tree, permissions and API conventions.
- `Task-039` — Academy page conventions and the API client namespace; the attendance page is a sibling, not an edit to those pages.

## Contracts and boundaries
- Student attendance: `academy_attendances` unique (`academy_enrollment_id`, `date`); status one of `present`, `absent`, `late`, `excused`; `minutes` nullable integer 0-1440; `note` nullable max 500; `recorded_by` the authenticated employee/user id. Only active/completed enrollments in the selected class and within `started_on`..`ends_on` are rows.
- Teacher timesheet: `academy_teacher_timesheets` unique (`teacher_id`, `class_id`, `date`); status `present` or `absent`; `starts_at`/`ends_at` nullable `H:i`; `minutes` derived or validated 0-1440; a teacher may appear in several classes on one date.
- Read endpoint: `GET /academy/attendance?class_id=&month=YYYY-MM` returns the class, its active enrollments, the month dates and the existing rows. `GET /academy/teacher-timesheets?teacher_id=&month=YYYY-MM` returns teacher rows.
- Write endpoint: one `PUT /academy/attendance` batch payload with rows `{enrollment_id, date, status, minutes?, note?}` and one `PUT /academy/teacher-timesheets` batch; reject dates outside the enrollment/class range unless an explicit `override` flag is supplied and recorded in the note. No silent writes outside the term.
- Permission/data scope: read `view_academy_contracts`; write `manage_academy_attendance`; a viewer outside the academy scope gets the same 403 as Task-038. The page hides write controls without the permission.
- Print contract: `printDocument` with `bodyClass: 'printing-academy-attendance'`, A4 landscape, zero or 10 mm margins per the existing timesheet; every feature selector is prefixed with that body class; no `@page` in the SFC.
- Compatibility: no HR attendance, salary, leave, off-day, employee or payroll row is created or updated; the HR timesheet page is unchanged.
- Files this task must not modify: `resources/ts/pages/timesheet.vue`, `resources/ts/pages/templates/timesheet_template.vue`, `app/Http/Controllers/AttendanceController.php`, `app/Models/Attendance.php`, `resources/ts/plugins/1.router/routes.ts`, `resources/ts/navigation/**`, `resources/ts/plugins/i18n/locales/**`, `lang/**`, `app/Http/Controllers/EducationalMasterDataController.php`.

## Exclusive file ownership
- `database/migrations/2026_11_07_000005_create_academy_attendance_tables.php` — both academy attendance tables and indexes.
- `app/Models/AcademyAttendance.php` — student attendance model, casts and relations.
- `app/Models/AcademyTeacherTimesheet.php` — teacher time model, casts and relations.
- `app/Http/Controllers/AcademyAttendanceController.php` — monthly reads, batch writes, validation and scope.
- `app/Http/Requests/StoreAcademyAttendanceRequest.php` — student and teacher batch validation.
- `app/Http/Resources/AcademyAttendanceResource.php` — month payload for the page.
- `routes/academy/attendance.php` — academy attendance and teacher-timesheet routes inside the Task-038 tree.
- `resources/ts/api/academyAttendance.ts` — typed month reads and batch writes.
- `resources/ts/pages/academy/attendance.vue` — student/teacher tabs, grid, totals and print host.
- `resources/ts/pages/templates/academy_attendance_template.vue` — A4 landscape print layout.
- `tests/Feature/AcademyAttendanceTest.php` — uniqueness, scope, range, teacher multi-class and payload coverage.
- `scripts/academy-attendance-print-regression.mjs` — print layout regression.

## Acceptance criteria
- [ ] Given a class and a month, when the student attendance sheet loads, then each active enrollment in the class is a row, each date in the selected month is a column, and no HR leave, geofence, off-day, salary or employee attendance data is read or written.
- [ ] Given a cell, when the user sets present, absent, late or excused, then exactly one `academy_attendances` row per enrollment/date is stored, a second save updates it, and the unique constraint prevents duplicates.
- [ ] Given a date outside the enrollment's start/end or a class with no meeting that day, when the sheet is shown, then the date is visually out of scope and cannot be written as a normal attendance without an explicit override that is recorded in the note.
- [ ] Given the teacher tab, when a teacher/date/class is marked, then the teacher timesheet stores presence, start/end time and minutes; a teacher can have more than one class in a day; no payroll, salary, leave or HR attendance row is created.
- [ ] Given class/month or teacher/month filters, when the sheet renders, then it uses the shared date formatters, shows present/absent/late/excused and teacher-hour totals, and keeps data scoped to the viewer's academy classes.
- [ ] Given the print action, when it runs, then the sheet prints A4 landscape through `printDocument` with body class `printing-academy-attendance`, no `@page` in the SFC, readable headers/footers, and the same rows as the screen.
- [ ] Given a viewer without `manage_academy_attendance`, when they open the page, then it is read-only and a write returns 403; a viewer without `view_academy_contracts` cannot read the page or endpoint.
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark after Task-044 wires the route, when the grid/cards render, then the layout remains usable, keyboard reachable, and does not overflow the page.
- [ ] Given the focused checks, when `php artisan test --filter=AcademyAttendanceTest`, `node scripts/academy-attendance-print-regression.mjs`, `npm run typecheck` and `npm run i18n:check` run, then they pass.

## Verification plan
- Automated: run `php artisan test --filter=AcademyAttendanceTest` on an isolated database; assert unique constraints, range refusals, permission 403s, teacher multi-class rows and month payload shape.
- Write check: save the same cell twice and assert one row with the second value; batch 30 rows and assert the transaction writes all or none on a validation failure.
- Scope check: one class with three students, one completed enrollment and one withdrawn enrollment; assert only active/completed in-range rows appear.
- Print check: `node scripts/academy-attendance-print-regression.mjs`; assert the body class, A4 landscape page rule, header/footer, row count and no dangling `@page` in the SFC.
- Manual after Task-044: mark a week of attendance, switch to the teacher tab, add two classes for one teacher on one day, print both tabs at 390/768/1280 px in LTR/RTL and light/dark.
- Regression: HR timesheet, payroll and leave tests; confirm no HR table row counts change after an academy write.
- Evidence: test output, print script output, before/after HR counts and the post-wiring screenshots.

## Implementation notes
Keep the grid as a page-local component or a small child component; do not fork the shared table for a calendar. Use a month selector and a class/teacher selector; generate dates with the same `formatDateKey`/UTC pattern as the HR template to avoid timezone shifts. Cycle a cell through the four statuses with a click and a context menu, or use a compact select; make the status accessible by text, not colour alone.

Batch writes in one transaction with `updateOrCreate` keyed by the unique pair/triple. Validate every row before writing; on a single invalid row, write nothing and return field errors. Compute teacher minutes from start/end when both are present; reject an end before a start. Do not import or call `AttendanceController`, `Attendance`, `LeaveRequest`, `OffDay` or payroll services.

For print, follow the namespaced pattern in `docs/facts` and AGENTS.md: render a hidden `.academy-attendance-print-host` on the page, put the A4 landscape rule only in the `printDocument` call, prefix every global print selector with `.printing-academy-attendance`, and keep the on-screen grid out of the print host. Use `pageStyle: 'size: A4 landscape; margin: 8mm;'` unless the existing timesheet establishes a better margin.

## Risks and rollback
- Risk: the page becomes a second HR timesheet with payroll implications. Mitigation: separate tables/models and no HR service calls; assert HR row counts unchanged in tests.
- Risk: timezone shifts move a date by one day. Mitigation: use date-only strings and UTC date keys; assert exact keys.
- Risk: a large month/class grid is slow. Mitigation: one month/class read, indices on class/date and enrollment/date, and no per-cell request.
- Risk: a teacher is paid from these hours without an agreed rate. Mitigation: the task states the timesheet is informational; payroll integration is explicitly excluded.
- Rollback: remove the route file, page, controller and models; drop the two additive tables only on a disposable database. Do not touch HR data.
- Stop condition: if the product owner expects teacher pay from these hours, stop and create a separate payroll task with rates and approval before writing the table.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Frozen locale key names and any missing keys are listed for Task-043.
- [ ] Route and nav wiring is explicitly deferred to Task-044.
- [ ] Remaining limitations and follow-up tasks are explicit.
