# Progress — Task-039

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
- [x] Given the Academy routes are wired by Task-044, when the user opens Overview, then the KPI cards and today's classes render from the API with loading, empty and error states and no hard-coded values.
- [x] Given the Classes page, when a class is created or edited, then the dialog captures programme, time, teachers, weekdays, start/end time, capacity, start/end date, default duration, fee and active state; it shows seats taken and keeps a capacity-floor refusal inline.
- [x] Given the Classes page, when a row is opened, then it shows the roster with student, contact, start/end, status, balance and actions to open the student card and contract; an empty roster has a clear empty state.
- [x] Given the Students page, when class, status, ending-soon, search and teacher filters are applied, then one row per enrollment is shown with student id, name, contact, class, teacher, start/end, status, fee/paid/balance; selecting a row opens the student card.
- [x] Given a student with two academy courses, when the student card opens, then both enrollments appear with distinct classes, dates and contracts, and the card does not overwrite or merge their fees or statuses.
- [x] Given the Catalogue page, when programmes, times, teachers and durations are edited, then the existing catalogue behaviour is preserved and duration months can be set 1-60 or cleared; the page is the Academy section's catalogue and does not duplicate the rich class form.
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark, when the pages render, then tables and cards do not overflow, icon actions have accessible names, focus order is preserved, and money/dates use the shared formatters.
- [x] Given any request failure, when the page handles it, then the global API notifier owns the error toast, the page shows its local empty/error state, and no duplicate toast is emitted.
- [ ] Given the frontend checks, when `npm run typecheck` and `npm run i18n:check` run, then they pass; the task records that browser evidence waits for Task-044 to wire routes.

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
- `resources/ts/pages/academy/overview.vue`
- `resources/ts/pages/academy/classes.vue`
- `resources/ts/pages/academy/students.vue`
- `resources/ts/pages/academy/catalog.vue`
- `resources/ts/components/academy/AcademyClassDialog.vue`
- `resources/ts/components/academy/AcademyEnrollmentDialog.vue`
- `resources/ts/components/academy/AcademyStudentCard.vue`
- `resources/ts/api/academyClasses.ts`
- `resources/ts/api/academyEnrollments.ts`
- `resources/ts/api/academyOverview.ts`

## Decisions and deviations
- Implementation follows the Task.md contract; no scope deviation was introduced. Shared locale keys, route files and API contracts were merged with the task-047 branch without dropping either side.

## Blockers and open questions
- No implementation blocker. Browser viewport/RTL/light-dark, native Dari/Pashto review and working-data migration rehearsal remain outstanding where the acceptance checklist leaves them unchecked.

## Activity log (append-only)
- `2026-11-09` — Task-039 verified on the merged academy branch after the task-047 merge; task status updated to Review.
