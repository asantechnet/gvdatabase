# Progress — Task-045

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (QA and integration reviewer)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Run the full acceptance matrix after Tasks 036-044 are at Review.`

## Acceptance criteria progress
- [ ] Given the full Academy feature, when the focused backend suites run, then they pass on an isolated database and the report lists the suite names, test counts and assertion counts. — Evidence: `not yet available`
- [ ] Given the frontend, when `npm run typecheck`, `npm run i18n:check`, `npm run build:icons` and `npm run build` run, then they exit 0 and no generated-file drift is committed. — Evidence: `not yet available`
- [ ] Given the print workflows, when the slip, attendance sheet and certificate regressions run, then they pass and the A5/A4 page rules are correct; no global print selector leaks into another route. — Evidence: `not yet available`
- [ ] Given a disposable copy of working data, when the migrations and backfill run, then enrollment counts reconcile with existing academy details, unresolved links are reported, and no existing row is lost. — Evidence: `not yet available`
- [ ] Given the permission matrix (super admin, manager, finance/HR read-only, academy staff with and without manage), when each Academy page/API is exercised, then read/write access matches the declared permissions and refusals are 403/422 without data changes. — Evidence: `not yet available`
- [ ] Given 390, 768 and 1280 px in LTR/RTL and light/dark, when overview, classes, students, attendance, certificates and catalogue are reviewed, then layout, focus, labels and empty/error states pass; findings are recorded with screenshots or exact source evidence. — Evidence: `not yet available`
- [ ] Given the full review, then no acceptance criterion from Tasks 036-044 is silently skipped; any failure is recorded as a blocker/reopened task, and the final report states what was not run. — Evidence: `not yet available`

- [ ] Given test-offer and academy contracts and multiple responsible employees, when the full pass runs, then those contracts create no stage or pipeline case, the stage catalogue no longer offers those services, the responsible-employee labels/permission work in all three locales, and scholarship/private commission results are unchanged. — Evidence: `not yet available`

## Work completed with evidence
- No implementation work has started. The task contract was created from the 2026-11-07 Academy revamp planning session after repository inspection named the real paths and constraints.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Not run yet | — | — | — | Task not started. |

## Decisions and deviations
- Read-only acceptance; failures reopen the owning task; no working-data writes or destructive rollbacks.

## Blockers and open questions
- None.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the Academy revamp plan and repository inspection.
- `2026-11-07` Added Task-046 to the acceptance scope for case-free contracts and plural responsible employees.
