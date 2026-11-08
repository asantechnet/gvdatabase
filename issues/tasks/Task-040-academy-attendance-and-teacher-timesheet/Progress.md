# Progress — Task-040

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (Laravel and Vue full-stack implementer)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Add the attendance tables/API/page/print and run the attendance suite and print regression.`

## Acceptance criteria progress
- [ ] Given a class and a month, when the student attendance sheet loads, then each active enrollment in the class is a row, each date in the selected month is a column, and no HR leave, geofence, off-day, salary or employee attendance data is read or written. — Evidence: `not yet available`
- [ ] Given a cell, when the user sets present, absent, late or excused, then exactly one `academy_attendances` row per enrollment/date is stored, a second save updates it, and the unique constraint prevents duplicates. — Evidence: `not yet available`
- [ ] Given a date outside the enrollment's start/end or a class with no meeting that day, when the sheet is shown, then the date is visually out of scope and cannot be written as a normal attendance without an explicit override that is recorded in the note. — Evidence: `not yet available`
- [ ] Given the teacher tab, when a teacher/date/class is marked, then the teacher timesheet stores presence, start/end time and minutes; a teacher can have more than one class in a day; no payroll, salary, leave or HR attendance row is created. — Evidence: `not yet available`
- [ ] Given class/month or teacher/month filters, when the sheet renders, then it uses the shared date formatters, shows present/absent/late/excused and teacher-hour totals, and keeps data scoped to the viewer's academy classes. — Evidence: `not yet available`
- [ ] Given the print action, when it runs, then the sheet prints A4 landscape through `printDocument` with body class `printing-academy-attendance`, no `@page` in the SFC, readable headers/footers, and the same rows as the screen. — Evidence: `not yet available`
- [ ] Given a viewer without `manage_academy_attendance`, when they open the page, then it is read-only and a write returns 403; a viewer without `view_academy_contracts` cannot read the page or endpoint. — Evidence: `not yet available`
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark after Task-044 wires the route, when the grid/cards render, then the layout remains usable, keyboard reachable, and does not overflow the page. — Evidence: `not yet available`
- [ ] Given the focused checks, when `php artisan test --filter=AcademyAttendanceTest`, `node scripts/academy-attendance-print-regression.mjs`, `npm run typecheck` and `npm run i18n:check` run, then they pass. — Evidence: `not yet available`

## Work completed with evidence
- No implementation work has started. The task contract was created from the 2026-11-07 Academy revamp planning session after repository inspection named the real paths and constraints.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Not run yet | — | — | — | Task not started. |

## Decisions and deviations
- Attendance copies the timesheet UX, not the HR service layer; no payroll, leave, geofence or employee-attendance writes.

## Blockers and open questions
- None.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the Academy revamp plan and repository inspection.
