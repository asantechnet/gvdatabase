# Progress — Task-025

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned`
- Last updated: `2026-11-01`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Verify the role API already accepts and returns level, then add the seniority field and permission gating to the roles page.`

## Acceptance criteria progress
- [ ] Given a role with level 60, when `/setup/roles` loads, then the role card shows 60 in a labelled Seniority level value and the create/edit dialog shows the same value. — Evidence: not yet available
- [ ] Given an administrator with `manage_role_hierarchy`, when they create a role with level 55, then the API stores 55 and the new row shows it without a reload. — Evidence: not yet available
- [ ] Given the same administrator edits a role from 40 to 65, when they save, then the level changes, the permission set is unchanged, and the row updates. — Evidence: not yet available
- [ ] Given a user without `manage_role_hierarchy`, when the dialog opens, then the level field is disabled with a short reason and the payload omits `level`; editing the name still works. — Evidence: not yet available
- [ ] Given a protected role, when the dialog opens, then the level field is disabled with a protected-role reason and a direct API attempt to change it is rejected. — Evidence: not yet available
- [ ] Given an invalid level such as -1, a decimal or 100001, when the form is submitted, then the field shows a validation message and no request is sent; a direct API attempt returns 422. — Evidence: not yet available
- [ ] Given the level hint, when it is read, then it states that higher outranks lower and that the level is used for supervision, department-manager eligibility and assignment ceilings, not as a permission grant. — Evidence: not yet available
- [ ] Given an API failure on save, when it returns, then the page shows the shared notifier message once and the field error inline, with no duplicate page toast. — Evidence: not yet available
- [ ] Given 390, 768 and 1280 px in LTR and RTL, light and dark, when the role-card grid and dialog are used with the keyboard, then the level is readable, the field is reachable, the RTL layout does not reverse the number, and no overflow hides the edit or save action. — Evidence: not yet available

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
