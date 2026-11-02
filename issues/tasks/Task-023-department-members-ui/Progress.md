# Progress — Task-023

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned`
- Last updated: `2026-11-01`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Confirm the Task-022 member payload and the Task-026 key list, then build the department members dialog.`

## Acceptance criteria progress
- [ ] Given a user with `manage_departments`, when the departments table renders, then every row has a members action that shows the current member count and opens the dialog. — Evidence: not yet available
- [ ] Given a user without `manage_departments`, when the table renders, then the members action is hidden or read-only and no save control is offered; a direct API attempt still returns 403. — Evidence: not yet available
- [ ] Given a department with members, when the dialog opens, then it lists every member with name, position, role, status, primary badge and manager badge, and it shows the manager separately when the manager is not a member. — Evidence: not yet available
- [ ] Given an employee already in Finance, when they are added to Operations and the dialog saves, then the success response shows both departments and the employee is not removed from Finance. — Evidence: not yet available
- [ ] Given a non-manager member, when the user removes them and saves, then the row count decreases and the member disappears from the list without a page reload. — Evidence: not yet available
- [ ] Given the current manager, when the dialog renders that member, then removal is disabled with a short reason; if a crafted request still omits the manager, the API refusal appears inline and the dialog stays open. — Evidence: not yet available
- [ ] Given an added employee the manager cannot supervise, when the save is refused, then the specific member row shows the translated rank reason and no second toast appears. — Evidence: not yet available
- [ ] Given a primary member is removed and the employee has another department, when the save succeeds, then the dialog or success copy states that the other department became primary; when no other department exists, it states that the employee now has no primary department. — Evidence: not yet available
- [ ] Given a department with no members, when the dialog opens, then an empty state explains how to add the first employee; given no active employees are available, the add control shows a distinct empty state. — Evidence: not yet available
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark, when the dialog is used with the keyboard, then the member list scrolls, the save/cancel controls remain reachable, names are not mirrored, and focus returns to the trigger on close. — Evidence: not yet available

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
