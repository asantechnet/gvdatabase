# Progress — Task-026

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned`
- Last updated: `2026-11-01`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Agree the frozen key list with the three UI tasks and add the new keys to all three frontend and backend catalogs.`

## Acceptance criteria progress
- [ ] Given the three frontend locale files, when their nested key paths are compared, then every new key added by this task exists in all three with a non-empty value and no existing key was removed. — Evidence: not yet available
- [ ] Given a UI task references a new key, when the page renders in English, Dari and Pashto, then the label is translated and no raw key or English fallback appears. — Evidence: not yet available
- [ ] Given the three PHP catalogs, when the new business codes are looked up, then each returns a specific sentence rather than `api.errors.business.default` or `api.errors.server.default`. — Evidence: not yet available
- [ ] Given `apiErrors.ts` maps a new code, when the code is translated, then the matching frontend label exists under the exact namespace and the message is not a generic fallback. — Evidence: not yet available
- [ ] Given the Reports-to disclosure, when it is read in all three languages, then it states what may be shared, what is not granted, that department membership and task assignment are separate, and what clearing does. — Evidence: not yet available
- [ ] Given the role seniority hint, when it is read in all three languages, then it states that higher outranks lower and that the level is not a permission grant. — Evidence: not yet available
- [ ] Given the department membership copy, when it is read in all three languages, then it states that one employee may belong to several departments, that the save changes only this department, that a primary member's removal promotes another membership or leaves none, and why the manager cannot be removed. — Evidence: not yet available
- [ ] Given the JSON files, when they are parsed, then they are valid JSON with no duplicate keys and no trailing comma; `npm run i18n:check` passes. — Evidence: not yet available
- [ ] Given the task diff, when it is reviewed, then it contains copy and catalogue additions only, with no component, controller or schema change. — Evidence: not yet available
- [ ] Given the longest Dari and Pashto strings, when they are measured against the dialog layout, then they fit at 390 px without truncating the confirm/cancel controls; the UI task records the actual check. — Evidence: not yet available

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
