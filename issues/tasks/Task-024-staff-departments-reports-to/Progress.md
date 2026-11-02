# Progress — Task-024

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned`
- Last updated: `2026-11-01`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Confirm the Task-021 employee fields and the Task-026 disclosure copy, then update the staff form, table and payload construction.`

## Acceptance criteria progress
- [ ] Given an employee in Operations and Finance, when the staff table renders, then both department names appear as separate chips and the print row contains both names joined readably; a single-department employee still renders one chip. — Evidence: not yet available
- [ ] Given the create form, when two departments are selected and Finance is chosen as primary, then the payload contains both ids and `primary_department_id: Finance`, and the saved row shows both departments. — Evidence: not yet available
- [ ] Given the edit form, when the user removes the primary department from the selection, then the primary control clears and the form requires another primary or no departments before save. — Evidence: not yet available
- [ ] Given the Reports-to field, when it opens, then the options include active employees beyond the current table page, exclude the employee being edited, and show name, position and department. — Evidence: not yet available
- [ ] Given the user selects a different supervisor, when the selection changes, then a confirmation opens naming the supervisor and stating the shared HR scope, the rights not granted, the separate department/task rules and the effect of clearing. — Evidence: not yet available
- [ ] Given the confirmation is cancelled, when the dialog closes, then the previous supervisor remains selected and no payload field changed; given it is confirmed, then the new supervisor is applied and saved. — Evidence: not yet available
- [ ] Given the current supervisor is cleared, when the removal confirmation is confirmed, then `supervisor_id: null` is sent and the table shows the not-assigned state; cancel restores the supervisor. — Evidence: not yet available
- [ ] Given a supervisor the employee cannot be assigned to, when the API returns a 422, then the Reports-to field shows the translated rank reason and no success toast is shown. — Evidence: not yet available
- [ ] Given a user without `assign_employee_supervisor`, when the form opens, then the department and supervisor controls are read-only with a reason, those fields are absent from the payload, and the other permitted fields still save. — Evidence: not yet available
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark, when the form, chips, confirmation and table are used with the keyboard, then controls remain reachable, names are not mirrored, and no horizontal overflow hides the primary department or Reports-to actions. — Evidence: not yet available

## Work completed with evidence
- None yet. The task contract is approved for scheduling; implementation has not started.

## Files changed and why
- None yet. The exclusive file list is in `Task.md`.

## Checks run and results
| Date/time | Command/check | Result | Evidence path | Notes |
|---|---|---|---|---|
| — | Not run yet | Not run | — | — |

## Decisions and deviations
- None yet. Record the primary-department, rank and copy decisions here when they are settled.

## Blockers and open questions
- None yet. The product assumptions in `Task.md` must be settled during implementation.

## Handoff notes
- Not ready for handoff. Read `Task.md` and `INDEX.md` first; implementation has not started.

## Activity log (append-only)
- `2026-11-01 00:00 UTC` Created the task contract; implementation has not started.
