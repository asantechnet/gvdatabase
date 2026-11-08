# Progress — Task-038

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (Laravel API implementer)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Add the academy route tree and class/enrollment/overview endpoints, then run the four API suites.`

## Acceptance criteria progress
- [ ] Given a viewer with `view_academy_contracts`, when they GET the class, enrollment and overview endpoints, then they receive paginated or filtered data with class, programme, time, teachers, dates, capacity, seats taken and balance, and the endpoint adds no N+1 query beyond the documented eager loads. — Evidence: `not yet available`
- [ ] Given a manager with `manage_academy_classes`, when they create or update a class, then validation covers name, programme/time, teachers, weekdays 1-7, start/end times, capacity, dates, duration, fee and active state; invalid values return field errors and no row. — Evidence: `not yet available`
- [ ] Given a class that already has enrollments, when a delete is attempted, then it is refused with a specific business code and no rows are removed; when capacity is reduced below the current active count, the update is refused. — Evidence: `not yet available`
- [ ] Given the enrollment filters `class_id`, `status`, `ending`, `search` and `teacher_id`, when applied, then the list returns only matching rows, respects the caller's academy scope, and keeps pagination and sorting. — Evidence: `not yet available`
- [ ] Given an enrollment update to completed, withdrawn or cancelled, then only the enrollment status, `completed_on` and notes change; the contract, line, stage, payment state and issued documents do not change; an invalid transition is refused. — Evidence: `not yet available`
- [ ] Given the overview endpoint, when the data is summed, then active students are distinct applicants with active enrollments, seats filled counts active enrollments only, ending-in-30-days uses `ends_on`, outstanding uses line payable minus paid, and completed-this-month uses `completed_on`; the numbers match focused fixtures. — Evidence: `not yet available`
- [ ] Given a viewer without `view_academy_contracts`, when any academy read endpoint is called, then the response is 403; given a viewer with read but without the relevant manage permission, when a write is attempted, then the response is 403; super admin and granted roles pass. — Evidence: `not yet available`
- [ ] Given `PATCH /academy/durations/{duration}`, when months is 1-60 or null, then it saves; zero, negative or greater-than-60 is rejected; the route is registered before any resource binding and the existing master-data endpoints are unchanged. — Evidence: `not yet available`
- [ ] Given the focused suites, when `php artisan test --filter=AcademyClassApiTest`, `php artisan test --filter=AcademyEnrollmentApiTest`, `php artisan test --filter=AcademyOverviewApiTest` and `php artisan test --filter=AcademyPermissionTest` run, then they pass. — Evidence: `not yet available`

- [ ] Given the permission catalogue and an existing database, when the migration and seeders run, then `assign_admission_employee` is renamed in place to `assign_responsible_employees`, every role that held the old permission still holds the new one, no other grant changes, and a fresh seed creates the new name. — Evidence: `not yet available`

## Work completed with evidence
- No implementation work has started. The task contract was created from the 2026-11-07 Academy revamp planning session after repository inspection named the real paths and constraints.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Not run yet | — | — | — | Task not started. |

## Decisions and deviations
- Dedicated academy controllers instead of widening EducationalMasterDataController; a small route tree lets later tasks add files without shared edits.
- `routes/api.php` adds a guarded include for `routes/crm.php` when present. Task-047 owns the CRM route file and controller; this task adds no CRM route.

## Blockers and open questions
- None.

## Handoff notes
- Not ready for handoff; implementation has not started.
- When the academy route include is implemented, add the guarded `routes/crm.php` include for Task-047 without creating the file.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the Academy revamp plan and repository inspection.
- `2026-11-07` Added the in-place responsible-employee permission rename and its role-grant preservation for Task-046.
- `2026-11-07` Task-037 now depends on this task for the renamed permission; this API no longer depends on Task-037's contract generation. This task's tests seed their own enrollment fixtures.
- `2026-11-07` Added the guarded `routes/crm.php` include seam for Task-047; the CRM route file remains Task-047-owned.
