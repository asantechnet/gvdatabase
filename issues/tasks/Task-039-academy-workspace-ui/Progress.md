# Progress — Task-039

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (Vue 3 frontend implementer)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Build the four Academy pages and three components against the Task-038 API.`

## Acceptance criteria progress
- [ ] Given the Academy routes are wired by Task-044, when the user opens Overview, then the KPI cards and today's classes render from the API with loading, empty and error states and no hard-coded values. — Evidence: `not yet available`
- [ ] Given the Classes page, when a class is created or edited, then the dialog captures programme, time, teachers, weekdays, start/end time, capacity, start/end date, default duration, fee and active state; it shows seats taken and keeps a capacity-floor refusal inline. — Evidence: `not yet available`
- [ ] Given the Classes page, when a row is opened, then it shows the roster with student, contact, start/end, status, balance and actions to open the student card and contract; an empty roster has a clear empty state. — Evidence: `not yet available`
- [ ] Given the Students page, when class, status, ending-soon, search and teacher filters are applied, then one row per enrollment is shown with student id, name, contact, class, teacher, start/end, status, fee/paid/balance; selecting a row opens the student card. — Evidence: `not yet available`
- [ ] Given a student with two academy courses, when the student card opens, then both enrollments appear with distinct classes, dates and contracts, and the card does not overwrite or merge their fees or statuses. — Evidence: `not yet available`
- [ ] Given the Catalogue page, when programmes, times, teachers and durations are edited, then the existing catalogue behaviour is preserved and duration months can be set 1-60 or cleared; the page is the Academy section's catalogue and does not duplicate the rich class form. — Evidence: `not yet available`
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark, when the pages render, then tables and cards do not overflow, icon actions have accessible names, focus order is preserved, and money/dates use the shared formatters. — Evidence: `not yet available`
- [ ] Given any request failure, when the page handles it, then the global API notifier owns the error toast, the page shows its local empty/error state, and no duplicate toast is emitted. — Evidence: `not yet available`
- [ ] Given the frontend checks, when `npm run typecheck` and `npm run i18n:check` run, then they pass; the task records that browser evidence waits for Task-044 to wire routes. — Evidence: `not yet available`

## Work completed with evidence
- No implementation work has started. The task contract was created from the 2026-11-07 Academy revamp planning session after repository inspection named the real paths and constraints.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Not run yet | — | — | — | Task not started. |

## Decisions and deviations
- Routes and navigation are deferred to Task-044; the old academy-data page is kept and a new catalogue page is added.

## Blockers and open questions
- None.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the Academy revamp plan and repository inspection.
