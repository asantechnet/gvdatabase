# Progress — Task-042

## Status snapshot
- Status: `Ready`
- Owner: `Unassigned (Laravel and Vue document-print implementer)`
- Last updated: `2026-11-07`
- Current focus: `Not started`
- Blocker: `None`
- Next action: `Add contract.end_date and the slip term fields, then run the slip and contract-document suites.`

## Acceptance criteria progress
- [ ] Given the placeholder registry, when `contract.end_date` is requested for an academy/customer template, then it is declared exactly once with a human label and resolved to the contract's `Y-m-d` end date; an unknown key is still refused. — Evidence: `not yet available`
- [ ] Given the hidden academy template seeder, when it runs on a fresh or existing installation, then the template has a term line that names the start and end dates, the active version remains valid, and an issued contract's stored snapshot and reprint are unchanged. — Evidence: `not yet available`
- [ ] Given an academy contract with an end date, when its document is generated, then the rendered content includes the term start/end and no unresolved placeholder; a contract without an end date renders a clear fallback rather than an empty token. — Evidence: `not yet available`
- [ ] Given the academy slip payload, when it is fetched, then it includes student id, class, start/end dates and the existing programme/time/duration/teacher/fee fields; the A5 template shows the dates without breaking the two-copy layout. — Evidence: `not yet available`
- [ ] Given the slip reprint, when the applicant/class/duration changed after issue, then the slip reflects the current live record as before while the contract snapshot remains the historical document; no new snapshot is invented for the slip. — Evidence: `not yet available`
- [ ] Given the existing print workflow, when the slip and contract are printed, then they use their existing body classes and page sizes, no `@page` is added to a route or SFC, and the slip remains A5. — Evidence: `not yet available`
- [ ] Given the focused checks, when `php artisan test --filter=AcademySlipTest`, `php artisan test --filter=AcademyContractDocumentTest`, `node scripts/academy-slip-print-regression.mjs`, `npm run typecheck` and `npm run i18n:check` run, then they pass and print evidence is recorded. — Evidence: `not yet available`
- [ ] Given a legacy academy contract with no end date, when the slip or contract document is read, then the output shows an explicit not-set state and does not fabricate a date or a negative remaining term. — Evidence: `not yet available`

## Work completed with evidence
- No implementation work has started. The task contract was created from the 2026-11-07 Academy revamp planning session after repository inspection named the real paths and constraints.

## Files changed and why
- None yet.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-07 | Not run yet | — | — | — | Task not started. |

## Decisions and deviations
- The contract document stays snapshot-based; the slip stays live; legacy null end dates show an explicit not-set state.

## Blockers and open questions
- None.

## Handoff notes
- Not ready for handoff; implementation has not started.

## Activity log (append-only)
- `2026-11-07` Created the task contract from the Academy revamp plan and repository inspection.
- `2026-11-07` Forward ownership of the paths needed for Task-046 moved to Task-046; the delivered behaviour remains historical evidence.
